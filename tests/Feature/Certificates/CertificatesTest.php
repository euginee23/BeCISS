<?php

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->staff()->create();
    $this->residentUser = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $this->residentUser->id]);
});

describe('certificates index', function () {
    it('can be accessed by admin', function () {
        $this->actingAs($this->admin)
            ->get(route('certificates.index'))
            ->assertSuccessful();
    });

    it('can be accessed by staff', function () {
        $this->actingAs($this->staff)
            ->get(route('certificates.index'))
            ->assertSuccessful();
    });

    it('cannot be accessed by resident users', function () {
        $this->actingAs($this->residentUser)
            ->get(route('certificates.index'))
            ->assertForbidden();
    });

    it('cannot be accessed by guests', function () {
        $this->get(route('certificates.index'))
            ->assertRedirect(route('login'));
    });

    it('displays certificates in the table', function () {
        $certificate = Certificate::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('certificates.index'))
            ->assertSee($certificate->certificate_number);
    });
});

describe('certificates create', function () {
    it('can be accessed by admin', function () {
        $this->actingAs($this->admin)
            ->get(route('certificates.create'))
            ->assertSuccessful();
    });

    it('can create a new certificate request', function () {
        $resident = Resident::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.create')
            ->set('resident_id', $resident->id)
            ->set('type', 'barangay_clearance')
            ->set('purpose', 'Employment / Job Application')
            ->call('save')
            ->assertRedirect(route('certificates.index'));

        $this->assertDatabaseHas('certificates', [
            'resident_id' => $resident->id,
            'type' => 'barangay_clearance',
            'purpose' => 'Employment / Job Application',
            'status' => 'pending',
        ]);
    });

    it('can create a barangay certification request', function () {
        $resident = Resident::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.create')
            ->set('resident_id', $resident->id)
            ->set('type', 'barangay_certification')
            ->set('purpose', 'Legal / Court Purposes')
            ->call('save')
            ->assertRedirect(route('certificates.index'));

        $this->assertDatabaseHas('certificates', [
            'resident_id' => $resident->id,
            'type' => 'barangay_certification',
            'purpose' => 'Legal / Court Purposes',
            'status' => 'pending',
        ]);
    });

    it('requires a registered resident', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::certificates.create')
            ->set('resident_id', null)
            ->set('type', 'barangay_clearance')
            ->set('purpose', 'Employment / Job Application')
            ->call('save')
            ->assertHasErrors(['resident_id']);
    });

    it('validates required fields', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::certificates.create')
            ->set('resident_id', '')
            ->set('type', '')
            ->call('save')
            ->assertHasErrors(['resident_id', 'type', 'purpose']);
    });

    it('requires purpose_other when purpose is Other', function () {
        $resident = Resident::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.create')
            ->set('resident_id', $resident->id)
            ->set('type', 'barangay_certification')
            ->set('purpose', 'Other')
            ->set('purpose_other', '')
            ->call('save')
            ->assertHasErrors(['purpose_other']);
    });
});

describe('certificates show', function () {
    it('can view a certificate', function () {
        $certificate = Certificate::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('certificates.show', $certificate))
            ->assertSuccessful()
            ->assertSee($certificate->certificate_number);
    });
});

describe('certificates workflow', function () {
    it('approves a paid certificate into awaiting payment', function () {
        $certificate = Certificate::factory()->barangayClearance()->create(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->call('approve');

        expect($certificate->fresh())
            ->status->toBe('awaiting_payment')
            ->approved_at->not->toBeNull();
    });

    it('approves a free certificate straight into processing', function () {
        $certificate = Certificate::factory()->indigency()->create(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->call('approve');

        $certificate->refresh();
        expect($certificate->status)->toBe('processing')
            ->and($certificate->processed_by)->toBe($this->admin->id);
    });

    it('records payment and starts processing', function () {
        $certificate = Certificate::factory()->awaitingPayment()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->call('openPaymentModal')
            ->set('orNumber', 'OR-2024-0001')
            ->call('recordPayment')
            ->assertHasNoErrors();

        $certificate->refresh();
        expect($certificate->status)->toBe('processing')
            ->and($certificate->is_paid)->toBeTrue()
            ->and($certificate->or_number)->toBe('OR-2024-0001')
            ->and($certificate->latestPayment)
            ->amount->toBe('50.00')
            ->received_by->toBe($this->admin->id);
    });

    it('can mark certificate ready for pickup with issuance details', function () {
        $certificate = Certificate::factory()->residency()->processing()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->call('openReadyModal')
            ->set('issuedAt', '2026-10-01')
            ->set('ctcNumber', '12345678')
            ->set('ctcPlaceIssued', 'Municipality of Sample')
            ->set('ctcDateIssued', '2026-01-15')
            ->call('markReady')
            ->assertHasNoErrors();

        $certificate->refresh();
        expect($certificate->status)->toBe('ready_for_pickup')
            ->and($certificate->issued_at->toDateString())->toBe('2026-10-01')
            ->and($certificate->ctc_number)->toBe('12345678');
    });

    it('requires ctc fields only for types that need them', function () {
        $certificate = Certificate::factory()->residency()->processing()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->call('openReadyModal')
            ->set('ctcNumber', '')
            ->set('ctcPlaceIssued', '')
            ->set('ctcDateIssued', '')
            ->call('markReady')
            ->assertHasErrors(['ctcNumber', 'ctcPlaceIssued', 'ctcDateIssued']);

        CertificateType::where('slug', 'certificate_of_residency')->update(['requires_ctc' => false]);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->call('openReadyModal')
            ->set('ctcNumber', '')
            ->set('ctcPlaceIssued', '')
            ->set('ctcDateIssued', '')
            ->call('markReady')
            ->assertHasNoErrors();

        expect($certificate->fresh()->status)->toBe('ready_for_pickup');
    });

    it('can release a certificate', function () {
        $certificate = Certificate::factory()->readyForPickup()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->call('release');

        $certificate->refresh();
        expect($certificate->status)->toBe('completed')
            ->and($certificate->completed_at)->not->toBeNull();
    });

    it('can reject a certificate', function () {
        $certificate = Certificate::factory()->create(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->set('rejectionReason', 'Incomplete requirements')
            ->call('rejectCertificate');

        $certificate->refresh();
        expect($certificate->status)->toBe('rejected')
            ->and($certificate->rejection_reason)->toBe('Incomplete requirements');
    });

    it('can cancel from the index without deleting the record', function () {
        $certificate = Certificate::factory()->create(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.index')
            ->call('confirmCancel', $certificate->id)
            ->set('cancellationReason', 'Duplicate request')
            ->call('cancelCertificate')
            ->assertHasNoErrors();

        expect($certificate->fresh())
            ->status->toBe('cancelled')
            ->cancelled_at->not->toBeNull();
    });
});

describe('certificate edit', function () {
    it('can update a pending certificate', function () {
        $certificate = Certificate::factory()->create(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.edit', ['certificate' => $certificate])
            ->set('purpose', 'Other')
            ->set('purpose_other', 'School enrollment requirement')
            ->call('save')
            ->assertRedirect(route('certificates.show', $certificate));

        $this->assertDatabaseHas('certificates', [
            'id' => $certificate->id,
            'purpose' => 'Other',
            'purpose_other' => 'School enrollment requirement',
        ]);
    });

    it('can reassign a pending certificate to another resident', function () {
        $certificate = Certificate::factory()->create(['status' => 'pending']);
        $other = Resident::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.edit', ['certificate' => $certificate])
            ->set('resident_id', $other->id)
            ->set('type', 'barangay_clearance')
            ->set('purpose', 'Employment / Job Application')
            ->call('save')
            ->assertRedirect(route('certificates.show', $certificate));

        $this->assertDatabaseHas('certificates', [
            'id' => $certificate->id,
            'resident_id' => $other->id,
        ]);
    });

    it('rejects an edit that clears the resident', function () {
        $certificate = Certificate::factory()->create(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.edit', ['certificate' => $certificate])
            ->set('resident_id', null)
            ->call('save')
            ->assertHasErrors(['resident_id']);
    });
});
