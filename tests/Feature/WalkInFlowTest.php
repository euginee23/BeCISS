<?php

use App\Models\Appointment;
use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\ServiceFee;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    ServiceFee::sync();
    ServiceFee::where('service_type', 'blotter')->update(['fee' => 300]);

    $this->admin = User::factory()->admin()->create();
    $this->resident = Resident::factory()->create();
});

function fileWalkInBlotter(User $staff, Resident $resident, string $orNumber = ''): Testable
{
    return Livewire::actingAs($staff)
        ->test('pages::blotters.create')
        ->set('resident_id', $resident->id)
        ->set('incident_type', 'theft')
        ->set('incident_datetime', now()->subDay()->format('Y-m-d\TH:i'))
        ->set('narrative', 'Wallet taken at the market.')
        ->set('or_number', $orNumber)
        ->call('save');
}

test('a walk-in blotter skips pending and starts in processing', function () {
    fileWalkInBlotter($this->admin, $this->resident)->assertHasNoErrors();

    $blotter = Blotter::sole();

    expect($blotter->is_walk_in)->toBeTrue()
        ->and($blotter->status)->toBe('processing')
        ->and($blotter->processed_by)->toBe($this->admin->id)
        ->and($blotter->is_paid)->toBeFalse();
});

test('an OR number entered when filing a walk-in blotter records the payment', function () {
    fileWalkInBlotter($this->admin, $this->resident, ' OR-5001 ')->assertHasNoErrors();

    $blotter = Blotter::sole();
    $payment = Payment::sole();

    expect($blotter->is_paid)->toBeTrue()
        ->and($blotter->or_number)->toBe('OR-5001')
        ->and((float) $payment->amount)->toBe(300.0)
        ->and($payment->payable->is($blotter))->toBeTrue();
});

test('a used OR number is rejected when filing a walk-in blotter', function () {
    Payment::factory()->create(['or_number' => 'OR-5002']);

    fileWalkInBlotter($this->admin, $this->resident, 'OR-5002')->assertHasErrors(['or_number']);

    expect(Blotter::count())->toBe(0);
});

test('a walk-in blotter cannot be rejected', function () {
    $blotter = Blotter::factory()->processing()->create(['is_walk_in' => true]);

    Livewire::actingAs($this->admin)
        ->test('pages::blotters.show', ['blotter' => $blotter])
        ->assertSee('Walk-in')
        ->assertDontSeeHtml('wire:click="openRejectModal"')
        ->set('rejectionReason', 'Not valid.')
        ->call('rejectBlotter')
        ->assertForbidden();

    expect($blotter->refresh()->status)->toBe('processing');
});

test('an online blotter can still be rejected', function () {
    $blotter = Blotter::factory()->create(['status' => 'pending']);

    Livewire::actingAs($this->admin)
        ->test('pages::blotters.show', ['blotter' => $blotter])
        ->assertSeeHtml('wire:click="openRejectModal"')
        ->set('rejectionReason', 'Not valid.')
        ->call('rejectBlotter')
        ->assertHasNoErrors();

    expect($blotter->refresh()->status)->toBe('rejected');
});

test('a paid walk-in certificate goes straight to awaiting payment', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::certificates.create')
        ->set('resident_id', $this->resident->id)
        ->set('type', 'barangay_clearance')
        ->set('purpose', 'Employment / Job Application')
        ->call('save')
        ->assertHasNoErrors();

    $certificate = Certificate::sole();

    expect($certificate->is_walk_in)->toBeTrue()
        ->and($certificate->status)->toBe('awaiting_payment')
        ->and($certificate->approved_at)->not->toBeNull();
});

test('a free walk-in certificate goes straight to processing', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::certificates.create')
        ->set('resident_id', $this->resident->id)
        ->set('type', 'certificate_of_indigency')
        ->set('purpose', 'Employment / Job Application')
        ->call('save')
        ->assertHasNoErrors();

    expect(Certificate::sole()->status)->toBe('processing');
});

test('a walk-in certificate cannot be rejected', function () {
    $certificate = Certificate::factory()->processing()->create(['is_walk_in' => true]);

    expect($certificate->canTransitionTo('rejected'))->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->assertSee('Walk-in')
        ->assertDontSeeHtml('wire:click="openRejectModal"');

    expect(Certificate::factory()->processing()->create()->canTransitionTo('rejected'))->toBeTrue();
});

test('a walk-in appointment is confirmed on booking', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::appointments.create')
        ->set('resident_id', $this->resident->id)
        ->set('service_type', 'consultation')
        ->set('description', 'Walk-in consultation at the hall.')
        ->set('appointment_date', now()->toDateString())
        ->set('appointment_time', '10:00')
        ->call('save')
        ->assertHasNoErrors();

    $appointment = Appointment::sole();

    expect($appointment->is_walk_in)->toBeTrue()
        ->and($appointment->status)->toBe('confirmed')
        ->and($appointment->handled_by)->toBe($this->admin->id);
});
