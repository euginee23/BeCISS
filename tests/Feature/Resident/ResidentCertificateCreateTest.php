<?php

use App\Models\Certificate;
use App\Models\CertificatePurpose;
use App\Models\CertificateType;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->staff()->create();
    $this->residentUser = User::factory()->resident()->create();
    $this->residentRecord = Resident::factory()->create(['user_id' => $this->residentUser->id]);
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
            ->set('purpose_other', 'Water connection')
            ->call('save')
            ->assertRedirect(route('resident.certificates.index'));

        $this->assertDatabaseHas('certificates', [
            'type' => 'certificate_of_residency',
            'fee' => 30.00,
        ]);
    });

    it('sets correct fee for barangay certification', function () {
        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'barangay_certification')
            ->set('purpose', 'Other')
            ->set('purpose_other', 'Water connection')
            ->call('save')
            ->assertRedirect(route('resident.certificates.index'));

        $this->assertDatabaseHas('certificates', [
            'type' => 'barangay_certification',
            'fee' => 50.00,
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

    it('uses the fee configured on the certificate type', function () {
        CertificateType::where('slug', 'barangay_clearance')->update(['fee' => 75]);

        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'barangay_clearance')
            ->set('purpose', 'Loan Application')
            ->call('save');

        $this->assertDatabaseHas('certificates', [
            'type' => 'barangay_clearance',
            'fee' => 75.00,
        ]);
    });

    it('stores the specified purpose when other is chosen', function () {
        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'barangay_clearance')
            ->set('purpose', 'Other')
            ->set('purpose_other', 'Water connection')
            ->call('save');

        expect(Certificate::first()->purpose_label)->toBe('Water connection');
    });

    it('can request an admin-created type', function () {
        CertificateType::factory()->create(['slug' => 'first_time_jobseeker', 'name' => 'First Time Jobseeker', 'fee' => 0]);

        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'first_time_jobseeker')
            ->set('purpose', 'Employment / Job Application')
            ->call('save')
            ->assertHasNoErrors();

        expect(Certificate::first()->type_label)->toBe('First Time Jobseeker');
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
            ->set('type', 'cedula')
            ->set('purpose', 'I need a cedula')
            ->call('save')
            ->assertHasErrors(['type']);
    });

    it('requires details when other is chosen', function () {
        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'barangay_clearance')
            ->set('purpose', 'Other')
            ->call('save')
            ->assertHasErrors(['purpose_other']);
    });

    it('rejects staff-only and inactive types', function () {
        CertificateType::factory()->staffOnly()->create(['slug' => 'staff_only_type']);
        CertificateType::factory()->inactive()->create(['slug' => 'retired_type']);

        foreach (['staff_only_type', 'retired_type'] as $slug) {
            Livewire::actingAs($this->residentUser)
                ->test('pages::resident.certificates.create')
                ->set('type', $slug)
                ->set('purpose', 'Loan Application')
                ->call('save')
                ->assertHasErrors(['type']);
        }
    });

    it('rejects inactive purposes', function () {
        CertificatePurpose::where('name', 'Loan Application')->update(['is_active' => false]);

        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.certificates.create')
            ->set('type', 'barangay_clearance')
            ->set('purpose', 'Loan Application')
            ->call('save')
            ->assertHasErrors(['purpose']);
    });
});
