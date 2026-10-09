<?php

use App\Models\BarangayProfile;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Resident;
use App\Models\User;
use App\Services\CertificateDocumentService;

beforeEach(function () {
    BarangayProfile::factory()->create();
});

/**
 * Swap LibreOffice for a copy so download tests stay fast; one test below
 * still runs the real conversion.
 */
function fakePdfConversion(): void
{
    test()->partialMock(CertificateDocumentService::class, function ($mock): void {
        $mock->shouldReceive('convertToPdf')->andReturnUsing(function (string $docxPath): string {
            $pdfPath = preg_replace('/\.docx$/i', '.pdf', $docxPath);
            copy($docxPath, $pdfPath);

            return $pdfPath;
        });
    });
}

/**
 * Read the main XML part of the DOCX generated for a certificate.
 */
function generatedDocumentXml(Certificate $certificate): string
{
    $service = app(CertificateDocumentService::class);
    $path = $service->generate($certificate->load('resident'));

    $zip = new ZipArchive;
    $zip->open($path);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    $service->cleanup($path);

    return $xml;
}

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

describe('authorization', function () {
    beforeEach(fn () => fakePdfConversion());

    test('admin can download a completed certificate', function () {
        $certificate = Certificate::factory()->residency()->completed()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('certificates.download', $certificate))
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    });

    test('staff can download a paid certificate that is being processed', function () {
        $certificate = Certificate::factory()->residency()->processing()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('certificates.download', $certificate))
            ->assertSuccessful();
    });

    test('resident can download their own completed certificate', function () {
        $user = User::factory()->resident()->create();
        $resident = Resident::factory()->create(['user_id' => $user->id]);
        $certificate = Certificate::factory()->residency()->completed()->create([
            'resident_id' => $resident->id,
        ]);

        $this->actingAs($user)
            ->get(route('certificates.download', $certificate))
            ->assertSuccessful();
    });

    test('resident cannot download another residents certificate', function () {
        $user = User::factory()->resident()->create();
        Resident::factory()->create(['user_id' => $user->id]);
        $certificate = Certificate::factory()->residency()->completed()->create();

        $this->actingAs($user)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    });

    test('unauthenticated user cannot download certificate', function () {
        $certificate = Certificate::factory()->residency()->completed()->create();

        $this->get(route('certificates.download', $certificate))
            ->assertRedirect(route('login'));
    });

    test('authorization is checked before the template lookup', function () {
        $user = User::factory()->resident()->create();
        Resident::factory()->create(['user_id' => $user->id]);
        CertificateType::factory()->create(['slug' => 'no_template_type']);
        $certificate = Certificate::factory()->completed()->create(['type' => 'no_template_type']);

        $this->actingAs($user)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    });

    test('staff get not found for a type without a template', function () {
        CertificateType::factory()->create(['slug' => 'no_template_type']);
        $certificate = Certificate::factory()->completed()->create(['type' => 'no_template_type']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('certificates.download', $certificate))
            ->assertNotFound();
    });
});

/*
|--------------------------------------------------------------------------
| Status Guards
|--------------------------------------------------------------------------
*/

describe('status guards', function () {
    beforeEach(fn () => fakePdfConversion());

    test('staff cannot print before approval or payment', function (string $status, bool $paid) {
        $certificate = Certificate::factory()->residency()->create([
            'status' => $status,
            'is_paid' => $paid,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    })->with([
        'pending' => ['pending', false],
        'awaiting payment' => ['awaiting_payment', false],
        'processing but unpaid' => ['processing', false],
        'rejected' => ['rejected', true],
        'cancelled' => ['cancelled', false],
    ]);

    test('staff can print free certificates without a payment', function () {
        $certificate = Certificate::factory()->indigency()->create([
            'status' => 'processing',
            'is_paid' => false,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('certificates.download', $certificate))
            ->assertSuccessful();
    });

    test('residents can only download once released', function () {
        $user = User::factory()->resident()->create();
        $resident = Resident::factory()->create(['user_id' => $user->id]);
        $certificate = Certificate::factory()->residency()->readyForPickup()->create([
            'resident_id' => $resident->id,
        ]);

        $this->actingAs($user)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    });
});

/*
|--------------------------------------------------------------------------
| Output
|--------------------------------------------------------------------------
*/

describe('output', function () {
    beforeEach(fn () => fakePdfConversion());

    test('filename is a pdf named after the type and certificate number', function () {
        $certificate = Certificate::factory()->barangayClearance()->completed()->create();

        $disposition = $this->actingAs(User::factory()->admin()->create())
            ->get(route('certificates.download', $certificate))
            ->headers->get('content-disposition');

        expect($disposition)
            ->toContain('Barangay_Clearance')
            ->toContain($certificate->certificate_number)
            ->toContain('.pdf');
    });

    test('ignores any requested format', function () {
        $certificate = Certificate::factory()->residency()->completed()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('certificates.download', $certificate).'?format=docx')
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    });

    test('leaves no intermediate docx behind', function () {
        $certificate = Certificate::factory()->residency()->completed()->create();
        $before = glob(storage_path('app/private/certificates/*.docx'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('certificates.download', $certificate))
            ->assertSuccessful();

        expect(glob(storage_path('app/private/certificates/*.docx')))->toEqual($before);
    });
});

test('converts to a real pdf with libreoffice', function () {
    $certificate = Certificate::factory()->residency()->completed()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('certificates.download', $certificate));

    $response->assertSuccessful();

    expect(substr(file_get_contents($response->getFile()->getPathname()), 0, 4))->toBe('%PDF');
})->skip(fn () => trim((string) shell_exec('command -v libreoffice')) === '', 'LibreOffice is not installed.');

/*
|--------------------------------------------------------------------------
| Placeholders
|--------------------------------------------------------------------------
*/

describe('placeholders', function () {
    test('barangay clearance is filled from the stored issuance details', function () {
        $resident = Resident::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'house_number' => '12',
            'street' => 'Some Street',
            'purok' => 'Purok 3',
        ]);
        $certificate = Certificate::factory()->barangayClearance()->completed()->create([
            'resident_id' => $resident->id,
            'purpose' => 'Employment / Job Application',
            'issued_at' => '2026-04-01',
        ]);

        expect(generatedDocumentXml($certificate))
            ->toContain('Juan')
            ->toContain('Dela Cruz')
            ->toContain('12, Some Street, Purok 3')
            ->not->toContain('Purok Purok')
            ->toContain('April 1, 2026');
    });

    test('barangay indigency keeps its month and year format', function () {
        $resident = Resident::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'house_number' => '8',
            'street' => 'Main Road',
            'purok' => 'Purok 1',
        ]);
        $certificate = Certificate::factory()->indigency()->completed()->create([
            'resident_id' => $resident->id,
            'issued_at' => '2026-04-10',
        ]);

        expect(generatedDocumentXml($certificate))
            ->toContain('Maria')
            ->toContain('Santos')
            ->toContain('8, Main Road, Purok 1')
            ->not->toContain('Purok Purok')
            ->toContain('April 2026')
            ->toContain('April 10, 2026');
    });

    test('barangay certification prints the official receipt', function () {
        $certificate = Certificate::factory()->barangayCertification()->completed()->create([
            'or_number' => 'OR-777123',
            'issued_at' => '2026-04-10',
        ]);

        expect(generatedDocumentXml($certificate))
            ->toContain('OR-777123')
            ->toContain('50.00');
    });
});
