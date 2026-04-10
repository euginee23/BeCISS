<?php

use App\Models\Certificate;
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

    it('validates required fields', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::certificates.create')
            ->set('resident_id', '')
            ->set('type', '')
            ->call('save')
            ->assertHasErrors(['resident_id', 'type', 'purpose']);
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
    it('can start processing a pending certificate', function () {
        $certificate = Certificate::factory()->create(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->call('startProcessing');

        $certificate->refresh();
        expect($certificate->status)->toBe('processing')
            ->and($certificate->processed_by)->toBe($this->admin->id);
    });

    it('can mark certificate ready for pickup', function () {
        $certificate = Certificate::factory()->processing()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->call('markReadyForPickup');

        $certificate->refresh();
        expect($certificate->status)->toBe('ready_for_pickup');
    });

    it('can complete a certificate', function () {
        $certificate = Certificate::factory()->readyForPickup()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->set('orNumber', 'OR-2024-0001')
            ->call('completeCertificate');

        $certificate->refresh();
        expect($certificate->status)->toBe('completed')
            ->and($certificate->is_paid)->toBeTrue()
            ->and($certificate->or_number)->toBe('OR-2024-0001');
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
});

describe('certificate edit', function () {
    it('can update a pending certificate', function () {
        $certificate = Certificate::factory()->create(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.edit', ['certificate' => $certificate])
            ->set('purpose', 'Other')
            ->call('save')
            ->assertRedirect(route('certificates.show', $certificate));

        $this->assertDatabaseHas('certificates', [
            'id' => $certificate->id,
            'purpose' => 'Other',
        ]);
    });
});
