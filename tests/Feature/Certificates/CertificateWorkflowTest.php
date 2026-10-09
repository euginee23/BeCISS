<?php

use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\ResidentNotification;
use App\Services\CertificateWorkflow;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->admin = User::factory()->admin()->create();
    $this->workflow = app(CertificateWorkflow::class);
});

describe('transition guards', function () {
    it('refuses to release a pending certificate', function () {
        $certificate = Certificate::factory()->create(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->call('release')
            ->assertHasErrors(['status']);

        expect($certificate->fresh()->status)->toBe('pending');
    });

    it('refuses to mark an unpaid certificate ready', function () {
        $certificate = Certificate::factory()->create(['status' => 'processing', 'fee' => 50, 'is_paid' => false]);

        expect(fn () => $this->workflow->markReady($certificate, ['issued_at' => now()->toDateString()]))
            ->toThrow(ValidationException::class);
    });

    it('refuses to approve twice', function () {
        $certificate = Certificate::factory()->awaitingPayment()->create();

        expect(fn () => $this->workflow->approve($certificate, $this->admin))
            ->toThrow(ValidationException::class);
    });

    it('does not allow cancelling once paid', function () {
        $certificate = Certificate::factory()->processing()->create();

        expect($certificate->isCancellable())->toBeFalse()
            ->and(fn () => $this->workflow->cancel($certificate, $this->admin))
            ->toThrow(ValidationException::class);
    });

    it('can reject a certificate that is ready for pickup', function () {
        $certificate = Certificate::factory()->readyForPickup()->create();

        $this->workflow->reject($certificate, 'Wrong details');

        expect($certificate->fresh()->status)->toBe('rejected');
    });
});

describe('payments', function () {
    it('rejects an OR number that was already used', function () {
        Payment::factory()->create(['or_number' => 'OR-1000']);
        $certificate = Certificate::factory()->awaitingPayment()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->set('orNumber', 'OR-1000')
            ->call('recordPayment')
            ->assertHasErrors(['orNumber']);

        expect($certificate->fresh()->status)->toBe('awaiting_payment');
    });

    it('requires the payments permission', function () {
        $staff = User::factory()->staff(['certificates'])->create();
        $certificate = Certificate::factory()->awaitingPayment()->create();

        Livewire::actingAs($staff)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->set('orNumber', 'OR-2000')
            ->call('recordPayment')
            ->assertForbidden();
    });

    it('never asks for an OR on free certificates', function () {
        $certificate = Certificate::factory()->indigency()->create(['status' => 'pending']);

        $this->workflow->approve($certificate, $this->admin);
        $this->workflow->markReady($certificate->fresh(), ['issued_at' => now()->toDateString()]);
        $this->workflow->release($certificate->fresh());

        expect($certificate->fresh())
            ->status->toBe('completed')
            ->is_paid->toBeFalse()
            ->and(Payment::count())->toBe(0);
    });
});

describe('residents', function () {
    beforeEach(function () {
        $this->residentUser = User::factory()->resident()->create();
        $this->resident = Resident::factory()->create(['user_id' => $this->residentUser->id]);
    });

    it('notifies certificate staff when a resident files a request', function () {
        Notification::fake();
        $certificateStaff = User::factory()->staff(['certificates'])->create();
        $otherStaff = User::factory()->staff(['blotters'])->create();

        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'barangay_clearance')
            ->set('purpose', 'Loan Application')
            ->call('save');

        Notification::assertSentTo($certificateStaff, ResidentNotification::class, fn ($n) => $n->type === 'certificate_requested');
        Notification::assertNotSentTo($otherStaff, ResidentNotification::class);
    });

    it('lets a resident cancel their own unpaid request', function () {
        $certificate = Certificate::factory()->awaitingPayment()->create(['resident_id' => $this->resident->id]);

        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.index')
            ->call('openCancelModal', $certificate->id)
            ->set('cancellationReason', 'No longer needed')
            ->call('cancelCertificate')
            ->assertHasNoErrors();

        expect($certificate->fresh()->status)->toBe('cancelled');
    });

    it('does not let a resident cancel someone else\'s request', function () {
        $certificate = Certificate::factory()->create(['status' => 'pending']);

        expect(fn () => Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.index')
            ->call('openCancelModal', $certificate->id)
            ->call('cancelCertificate'))
            ->toThrow(ModelNotFoundException::class);

        expect($certificate->fresh()->status)->toBe('pending');
    });

    it('shows the payment instructions and schedule button when awaiting payment', function () {
        Certificate::factory()->awaitingPayment()->create(['resident_id' => $this->resident->id]);

        $this->actingAs($this->residentUser)
            ->get(route('resident.certificates.index'))
            ->assertSuccessful()
            ->assertSee('Awaiting Payment')
            ->assertSee('Schedule Visit');
    });
});

describe('certificate numbers', function () {
    it('continues from the highest number this year', function () {
        Certificate::factory()->create(['certificate_number' => 'CERT-'.now()->year.'-00041']);
        Certificate::factory()->create(['certificate_number' => 'CERT-'.now()->year.'-00007']);

        expect(Certificate::generateCertificateNumber())->toBe('CERT-'.now()->year.'-00042');
    });

    it('starts a new sequence each year', function () {
        Certificate::factory()->create(['certificate_number' => 'CERT-'.(now()->year - 1).'-00099']);

        expect(Certificate::generateCertificateNumber())->toBe('CERT-'.now()->year.'-00001');
    });

    it('creates certificates with unique numbers', function () {
        $resident = Resident::factory()->create();

        $numbers = collect(range(1, 5))->map(fn () => Certificate::createWithNumber([
            'resident_id' => $resident->id,
            'type' => 'barangay_clearance',
            'purpose' => 'Loan Application',
            'fee' => 50,
        ])->certificate_number);

        expect($numbers->unique())->toHaveCount(5);
    });
});

describe('linked visits', function () {
    it('completes the linked appointment on release', function () {
        $certificate = Certificate::factory()->readyForPickup()->create();
        $appointment = Appointment::factory()->confirmed()->create([
            'resident_id' => $certificate->resident_id,
            'certificate_id' => $certificate->id,
            'service_type' => 'certificate_request',
        ]);

        $this->actingAs($this->admin);
        $this->workflow->release($certificate);

        expect($appointment->fresh()->status)->toBe('completed');
    });

    it('cancels the linked appointment on rejection', function () {
        $certificate = Certificate::factory()->awaitingPayment()->create();
        $appointment = Appointment::factory()->create([
            'resident_id' => $certificate->resident_id,
            'certificate_id' => $certificate->id,
            'service_type' => 'certificate_request',
        ]);

        $this->workflow->reject($certificate, 'Invalid ID');

        expect($appointment->fresh()->status)->toBe('cancelled');
    });
});
