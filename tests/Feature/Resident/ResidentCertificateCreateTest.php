<?php

use App\Models\Certificate;
use App\Models\Resident;
use App\Models\ServiceFee;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->staff()->create();
    $this->residentUser = User::factory()->resident()->create();
    $this->residentRecord = Resident::factory()->create(['user_id' => $this->residentUser->id]);

    ServiceFee::updateOrCreate(['service_type' => 'barangay_clearance'], ['label' => 'Barangay Clearance', 'fee' => 50.00, 'is_active' => true]);
    ServiceFee::updateOrCreate(['service_type' => 'certificate_of_residency'], ['label' => 'Certificate of Residency', 'fee' => 30.00, 'is_active' => true]);
    ServiceFee::updateOrCreate(['service_type' => 'certificate_of_indigency'], ['label' => 'Certificate of Indigency', 'fee' => 0.00, 'is_active' => true]);
});

describe('page access', function () {
    it('can be accessed by resident', function () {
        $this->actingAs($this->residentUser)
            ->get(route('resident.certificates.create'))
            ->assertSuccessful();
    });

    it('cannot be accessed by admin', function () {
        $this->actingAs($this->admin)
            ->get(route('resident.certificates.create'))
            ->assertForbidden();
    });

    it('cannot be accessed by staff', function () {
        $this->actingAs($this->staff)
            ->get(route('resident.certificates.create'))
            ->assertForbidden();
    });

    it('cannot be accessed by guests', function () {
        $this->get(route('resident.certificates.create'))
            ->assertRedirect(route('login'));
    });
});

describe('certificate request submission', function () {
    it('can submit a certificate request', function () {
        $resident = $this->residentRecord;

        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'barangay_clearance')
            ->set('purpose', 'Employment / Job Application')
            ->call('save')
            ->assertRedirect(route('resident.certificates.index'));

        $this->assertDatabaseHas('certificates', [
            'resident_id' => $resident->id,
            'type' => 'barangay_clearance',
            'purpose' => 'Employment / Job Application',
            'status' => 'pending',
            'fee' => 50.00,
        ]);
    });

    it('sets correct fee for certificate of residency', function () {
        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'certificate_of_residency')
            ->set('purpose', 'Other')
            ->call('save')
            ->assertRedirect(route('resident.certificates.index'));

        $this->assertDatabaseHas('certificates', [
            'type' => 'certificate_of_residency',
            'fee' => 30.00,
        ]);
    });

    it('sets zero fee for certificate of indigency', function () {
        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'certificate_of_indigency')
            ->set('purpose', 'Government Benefits (SSS, PhilHealth, GSIS)')
            ->call('save')
            ->assertRedirect(route('resident.certificates.index'));

        $this->assertDatabaseHas('certificates', [
            'type' => 'certificate_of_indigency',
            'fee' => 0.00,
        ]);
    });

    it('generates a certificate number', function () {
        $resident = $this->residentRecord;

        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'barangay_clearance')
            ->set('purpose', 'Employment / Job Application')
            ->call('save');

        $certificate = Certificate::where('resident_id', $resident->id)->first();
        expect($certificate->certificate_number)->not->toBeEmpty();
    });

    it('saves optional remarks', function () {
        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'barangay_clearance')
            ->set('purpose', 'Employment / Job Application')
            ->set('remarks', 'Please rush this request')
            ->call('save')
            ->assertRedirect(route('resident.certificates.index'));

        $this->assertDatabaseHas('certificates', [
            'remarks' => 'Please rush this request',
        ]);
    });
});

describe('validation', function () {
    it('requires type and purpose', function () {
        Resident::factory()->create(['user_id' => $this->residentUser->id]);

        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', '')
            ->set('purpose', '')
            ->call('save')
            ->assertHasErrors(['type', 'purpose']);
    });

    it('rejects disallowed certificate types', function () {
        Resident::factory()->create(['user_id' => $this->residentUser->id]);

        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'business_permit')
            ->set('purpose', 'I want a business permit')
            ->call('save')
            ->assertHasErrors(['type']);
    });
});
