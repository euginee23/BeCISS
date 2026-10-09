<?php

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\User;
use App\Services\CertificateDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function clearanceTemplateUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'custom-clearance.docx',
        file_get_contents(storage_path('word_templates/BARANGAY_CLEARANCE_TEMPLATE.docx')),
    );
}

describe('page access', function () {
    it('is available to admins', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.certificate-types'))
            ->assertSuccessful()
            ->assertSee('Barangay Clearance');
    });

    it('is not available to staff', function () {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.settings.certificate-types'))
            ->assertForbidden();
    });
});

describe('managing types', function () {
    it('creates a type with a generated slug', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::admin.settings.certificate-types')
            ->call('openCreateModal')
            ->set('name', 'First Time Jobseeker')
            ->set('fee', '0')
            ->set('requirements', "Valid ID\nBarangay ID")
            ->set('available_to_residents', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('certificate_types', [
            'slug' => 'first_time_jobseeker',
            'name' => 'First Time Jobseeker',
            'available_to_residents' => false,
            'requirements' => "Valid ID\nBarangay ID",
        ]);
        $this->assertDatabaseHas('activity_logs', ['module' => 'certificate_types', 'action' => 'created']);
    });

    it('keeps the slug when a type is renamed', function () {
        $type = CertificateType::where('slug', 'barangay_clearance')->first();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.settings.certificate-types')
            ->call('openEditModal', $type->id)
            ->set('name', 'Barangay Clearance (Standard)')
            ->set('fee', '80')
            ->call('save')
            ->assertHasNoErrors();

        expect($type->fresh())
            ->slug->toBe('barangay_clearance')
            ->name->toBe('Barangay Clearance (Standard)')
            ->fee->toBe('80.00');
    });

    it('validates the fee', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::admin.settings.certificate-types')
            ->call('openCreateModal')
            ->set('name', 'Bad Fee')
            ->set('fee', '-1')
            ->call('save')
            ->assertHasErrors(['fee']);
    });

    it('stores an uploaded template and detects its placeholders', function () {
        Storage::fake('local');
        $type = CertificateType::where('slug', 'barangay_clearance')->first();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.settings.certificate-types')
            ->call('openEditModal', $type->id)
            ->set('template', clearanceTemplateUpload())
            ->call('save')
            ->assertHasNoErrors();

        $type->refresh();

        expect($type->template_original_name)->toBe('custom-clearance.docx')
            ->and($type->template_placeholders)->toContain('resident_name')
            ->and($type->hasTemplate())->toBeTrue()
            ->and(app(CertificateDocumentService::class)->templatePathFor('barangay_clearance'))
            ->toBe($type->templateFullPath());
    });

    it('rejects non-docx templates', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::admin.settings.certificate-types')
            ->call('openCreateModal')
            ->set('name', 'Test')
            ->set('template', UploadedFile::fake()->create('template.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertHasErrors(['template']);
    });

    it('does not delete a type that certificates use', function () {
        $type = CertificateType::where('slug', 'barangay_clearance')->first();
        Certificate::factory()->barangayClearance()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.settings.certificate-types')
            ->call('confirmDelete', $type->id)
            ->call('deleteType')
            ->assertHasErrors(['delete']);

        expect($type->fresh()->trashed())->toBeFalse();
    });

    it('soft deletes an unused type', function () {
        $type = CertificateType::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.settings.certificate-types')
            ->call('confirmDelete', $type->id)
            ->call('deleteType')
            ->assertHasNoErrors();

        $this->assertSoftDeleted($type);
    });
});

describe('labels', function () {
    it('resolves labels for retired types', function () {
        $type = CertificateType::factory()->create(['slug' => 'old_type', 'name' => 'Old Type']);
        $certificate = Certificate::factory()->create(['type' => 'old_type']);
        $type->delete();

        expect($certificate->fresh()->type_label)->toBe('Old Type');
    });

    it('charges nothing for inactive types', function () {
        CertificateType::factory()->inactive()->create(['slug' => 'inactive_type', 'fee' => 99]);

        expect(CertificateType::feeFor('inactive_type'))->toBe(0.00)
            ->and(CertificateType::feeFor('barangay_clearance'))->toBe(50.00);
    });
});
