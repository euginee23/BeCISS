<?php

use App\Mail\CertificateApproved;
use App\Mail\CertificateReadyForPickup;
use App\Mail\CertificateRejected;
use App\Models\BarangayProfile;
use App\Models\Certificate;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\User;
use App\Services\CertificateDocumentService;
use App\Services\CertificateWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->workflow = app(CertificateWorkflow::class);
});

describe('certificates from the old pay-at-release flow', function () {
    it('can still be paid and finished when they were processing unpaid', function () {
        Mail::fake();
        $certificate = Certificate::factory()->barangayClearance()->create(['status' => 'processing', 'is_paid' => false]);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $certificate])
            ->assertSee('Record Payment')
            ->set('orNumber', 'OR-LEGACY-1')
            ->call('recordPayment')
            ->assertHasNoErrors()
            ->call('openReadyModal')
            ->set('ctcNumber', '1')
            ->set('ctcPlaceIssued', 'Town')
            ->call('markReady')
            ->assertHasNoErrors();

        expect($certificate->fresh())
            ->status->toBe('ready_for_pickup')
            ->is_paid->toBeTrue();
    });

    it('can still be paid and released when they were ready for pickup unpaid', function () {
        Mail::fake();
        $certificate = Certificate::factory()->barangayClearance()->create(['status' => 'ready_for_pickup', 'is_paid' => false]);

        $this->actingAs($this->admin);
        $this->workflow->recordPayment($certificate, 'OR-LEGACY-2', $this->admin);

        expect($certificate->fresh()->status)->toBe('ready_for_pickup');

        $this->workflow->release($certificate->fresh());

        expect($certificate->fresh()->status)->toBe('completed');
    });
});

it('records a payment only once even when submitted twice', function () {
    $certificate = Certificate::factory()->awaitingPayment()->create();

    $this->workflow->recordPayment($certificate, 'OR-A', $this->admin);

    expect(fn () => $this->workflow->recordPayment($certificate, 'OR-B', $this->admin))
        ->toThrow(ValidationException::class)
        ->and(Payment::count())->toBe(1);
});

it('keeps the status change when the mail server is down', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused'));

    $residentUser = User::factory()->resident()->create();
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);
    $certificate = Certificate::factory()->barangayClearance()->create(['resident_id' => $resident->id]);

    Livewire::actingAs($this->admin)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->call('approve')
        ->assertHasNoErrors();

    expect($certificate->fresh()->status)->toBe('awaiting_payment')
        ->and($residentUser->notifications()->count())->toBe(1);
});

it('shows a friendly error when the pdf cannot be generated', function () {
    BarangayProfile::factory()->create();
    $this->partialMock(CertificateDocumentService::class, function ($mock): void {
        $mock->shouldReceive('convertToPdf')->andThrow(new RuntimeException('libreoffice: command not found'));
    });

    $certificate = Certificate::factory()->residency()->completed()->create();
    $before = glob(storage_path('app/private/certificates/*'));

    $this->actingAs($this->admin)
        ->get(route('certificates.download', $certificate))
        ->assertStatus(503);

    expect(glob(storage_path('app/private/certificates/*')))->toEqual($before);
});

it('ignores invalid dates in the report urls', function (string $route) {
    $this->actingAs($this->admin)
        ->get(route($route, ['from' => 'not-a-date', 'to' => '2026-02-31']))
        ->assertSuccessful();
})->with(['reports.collections', 'reports.index']);

it('rejects a docx upload that is not really a word document', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings.certificate-types')
        ->call('openCreateModal')
        ->set('name', 'Broken Template')
        ->set('template', UploadedFile::fake()->createWithContent('broken.docx', 'not a zip file'))
        ->call('save')
        ->assertHasErrors(['template']);

    $this->assertDatabaseMissing('certificate_types', ['name' => 'Broken Template']);
});

it('reports whole years of residency', function () {
    $resident = Resident::factory()->create(['residency_start_date' => now()->subYears(3)->subMonths(5)]);

    expect($resident->years_of_residency)->toBe(3);
});

it('renders every certificate email with real data', function (string $mailable) {
    $residentUser = User::factory()->resident()->create();
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);
    $certificate = Certificate::factory()->barangayClearance()->paid()->create([
        'resident_id' => $resident->id,
        'purpose' => 'Other',
        'purpose_other' => 'Water connection',
        'rejection_reason' => 'Missing ID',
    ]);

    $html = (new $mailable($residentUser, $certificate))->render();

    expect($html)->toContain($certificate->certificate_number)
        ->toContain('Water connection');
})->with([
    CertificateApproved::class,
    CertificateReadyForPickup::class,
    CertificateRejected::class,
]);
