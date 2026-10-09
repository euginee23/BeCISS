<?php

namespace App\Services;

use App\Models\BarangayProfile;
use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\CertificateType;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

class CertificateDocumentService
{
    /**
     * Template file mapping by certificate type.
     *
     * @var array<string, string>
     */
    private const array TEMPLATES = [
        'certificate_of_residency' => 'BARANGAY_RESIDENCY_TEMPLATE.docx',
        'barangay_clearance' => 'BARANGAY_CLEARANCE_TEMPLATE.docx',
        'barangay_certification' => 'BARANGAY_CERTIFICATION_TEMPLATE.docx',
        'certificate_of_indigency' => 'BARANGAY_IDIGENCY_TEMPLATE.docx',
        'blotter' => 'BARANGAY_BLOTTER_TEMPLATE.docx',
    ];

    /**
     * Placeholders every certificate template may use, written as ${name} in Word.
     *
     * @var array<string, string>
     */
    public const array PLACEHOLDERS = [
        'resident_name' => 'Full name of the resident',
        'resident_address' => 'House no., street and purok',
        'resident_age' => 'Age in years',
        'resident_birthdate' => 'Birthdate, e.g. January 5, 1990',
        'resident_civil_status' => 'Civil status',
        'resident_gender' => 'Gender',
        'years_of_residency' => 'Years living in the barangay',
        'certificate_type' => 'Certificate type name',
        'certificate_number' => 'Certificate number',
        'purpose' => 'Purpose of the request',
        'barangay_name' => 'Barangay name',
        'municipality_name' => 'Municipality / city',
        'province_name' => 'Province',
        'punong_barangay_name' => 'Punong Barangay name',
        'issued_full_date' => 'Issuance date, e.g. October 9, 2026',
        'date_issued' => 'Issuance date, e.g. October 9, 2026',
        'issue_day' => 'Day of issuance, e.g. 9',
        'issue_day_ordinal' => 'Day of issuance, e.g. 9th',
        'issue_month' => 'Month of issuance, e.g. October',
        'issue_year' => 'Year of issuance, e.g. 2026',
        'or_number' => 'Official receipt number',
        'amount_paid' => 'Amount paid, e.g. 50.00',
        'ctc_number' => 'Community Tax Certificate no.',
        'ctc_place_issued' => 'CTC place of issue',
        'ctc_date_issued' => 'CTC date of issue',
    ];

    /**
     * Per-type values that differ from the shared set, kept so the shipped
     * templates render exactly as they did before.
     *
     * @var array<string, array<string, string>>
     */
    private const array LEGACY_DATE_FORMATS = [
        'certificate_of_indigency' => ['issue_month' => 'F Y'],
    ];

    /**
     * Generate the certificate as a PDF, always cleaning up the intermediate DOCX.
     */
    public function generatePdf(Certificate $certificate): string
    {
        $docxPath = $this->generate($certificate);

        try {
            return $this->convertToPdf($docxPath);
        } finally {
            $this->cleanup($docxPath);
        }
    }

    /**
     * Generate a filled DOCX document for the given certificate, using the
     * issuance details stored on it.
     */
    public function generate(Certificate $certificate): string
    {
        $templatePath = $this->templatePathFor($certificate->type)
            ?? throw new \InvalidArgumentException("No template available for certificate type: {$certificate->type}");

        $template = new TemplateProcessor($templatePath);

        $template->setValues($this->getPlaceholderValues($certificate));

        $outputPath = $this->outputPath('certificates', "certificate_{$certificate->id}");

        $template->saveAs($outputPath);

        return $outputPath;
    }

    /**
     * Generate a filled DOCX document for the given blotter.
     *
     * @param  array{date_of_issuance: string}  $formData
     */
    public function generateBlotter(Blotter $blotter, array $formData): string
    {
        $templateFile = self::TEMPLATES['blotter']
            ?? throw new \InvalidArgumentException('No template available for blotter.');

        $templatePath = storage_path("word_templates/{$templateFile}");

        $template = new TemplateProcessor($templatePath);

        $template->setValues($this->getBlotterPlaceholderValues($blotter, $formData));

        $outputPath = $this->outputPath('blotters', "blotter_{$blotter->id}");

        $template->saveAs($outputPath);

        return $outputPath;
    }

    /**
     * A unique path for a generated file, so concurrent downloads never collide.
     */
    private function outputPath(string $folder, string $prefix): string
    {
        $outputDir = storage_path("app/private/{$folder}");

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        return "{$outputDir}/{$prefix}_".Str::uuid().'.docx';
    }

    /**
     * Get the placeholder values shared by every certificate template.
     *
     * @return array<string, string>
     */
    private function getPlaceholderValues(Certificate $certificate): array
    {
        $resident = $certificate->resident;
        $barangay = BarangayProfile::get();
        $issuanceDate = Carbon::parse($certificate->issued_at ?? now());
        $ctcDate = $certificate->ctc_date_issued;

        $values = [
            'resident_name' => $resident?->full_name ?? 'Unknown Resident',
            'resident_address' => $resident?->address ?? '',
            'resident_age' => (string) ($resident?->age ?? ''),
            'resident_birthdate' => $resident?->birthdate?->format('F j, Y') ?? '',
            'resident_civil_status' => ucfirst((string) $resident?->civil_status),
            'resident_gender' => ucfirst((string) $resident?->gender),
            'years_of_residency' => (string) ($resident?->years_of_residency ?? ''),
            'certificate_type' => $certificate->type_label,
            'certificate_number' => $certificate->certificate_number,
            'purpose' => $certificate->purpose_label ?? '',
            'barangay_name' => $barangay->barangay_name,
            'municipality_name' => $barangay->municipality ?? '',
            'province_name' => $barangay->province ?? '',
            'punong_barangay_name' => $barangay->captain_name ?? '',
            'issued_full_date' => $issuanceDate->format('F j, Y'),
            'date_issued' => $issuanceDate->format('F j, Y'),
            'issue_day' => $issuanceDate->format('j'),
            'issue_day_ordinal' => $issuanceDate->format('jS'),
            'issue_month' => $issuanceDate->format('F'),
            'issue_year' => $issuanceDate->format('Y'),
            'or_number' => $certificate->or_number ?? '',
            'amount_paid' => number_format((float) $certificate->fee, 2),
            'ctc_number' => $certificate->ctc_number ?? '',
            'ctc_place_issued' => $certificate->ctc_place_issued ?? '',
            'ctc_date_issued' => $ctcDate?->format('F j, Y') ?? '',
        ];

        foreach (self::LEGACY_DATE_FORMATS[$certificate->type] ?? [] as $placeholder => $format) {
            $values[$placeholder] = $issuanceDate->format($format);
        }

        return $values;
    }

    /**
     * Resolve the template for a type: the admin upload first, then the shipped file.
     */
    public function templatePathFor(string $type): ?string
    {
        $uploaded = CertificateType::withTrashed()->where('slug', $type)->first()?->templateFullPath();

        if ($uploaded) {
            return $uploaded;
        }

        $shipped = self::TEMPLATES[$type] ?? null;

        return $shipped && file_exists(storage_path("word_templates/{$shipped}"))
            ? storage_path("word_templates/{$shipped}")
            : null;
    }

    /**
     * Read the ${placeholder} names used in a DOCX template.
     *
     * @return list<string>
     */
    public function placeholdersIn(string $templatePath): array
    {
        return array_values(array_unique((new TemplateProcessor($templatePath))->getVariables()));
    }

    /**
     * Get the placeholder values for the given blotter.
     *
     * @param  array{date_of_issuance: string}  $formData
     * @return array<string, string>
     */
    private function getBlotterPlaceholderValues(Blotter $blotter, array $formData): array
    {
        $barangay = BarangayProfile::get();
        $issuanceDate = Carbon::parse($formData['date_of_issuance']);

        $resident = $blotter->resident;

        return [
            'resident_name' => $resident?->full_name ?? '',
            'resident_address' => $resident?->address ?? '',
            'incident_type' => $blotter->type_label,
            // The template renders "Purok ${purok_name}", so pass the bare number.
            'purok_name' => $resident?->purok_number ?? '',
            'barangay_name' => $barangay->barangay_name,
            'municipality_name' => $barangay->municipality ?? '',
            'province_name' => $barangay->province ?? '',
            'incident_datetime' => $blotter->incident_datetime->format('F j, Y g:i A'),
            'incident_location' => $blotter->incident_location ?? '',
            'owner_name' => $blotter->owner_name ?? '',
            'issue_day' => $issuanceDate->format('j'),
            'issue_month' => $issuanceDate->format('F'),
            'issue_year' => $issuanceDate->format('Y'),
            'date_issued' => $issuanceDate->format('F j, Y'),
            'or_number' => $blotter->or_number ?? '',
            'amount_paid' => number_format($blotter->fee, 2),
            'punong_barangay_name' => $barangay->captain_name ?? '',
        ];
    }

    /**
     * Convert a DOCX file to PDF using LibreOffice.
     */
    public function convertToPdf(string $docxPath): string
    {
        $outputDir = dirname($docxPath);

        /**
         * A private profile per run lets concurrent conversions proceed;
         * LibreOffice refuses to start while another instance holds the default one.
         */
        $profileDir = sys_get_temp_dir().'/lo_profile_'.Str::uuid();

        $command = sprintf(
            'libreoffice %s --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg('-env:UserInstallation=file://'.$profileDir),
            escapeshellarg($outputDir),
            escapeshellarg($docxPath),
        );

        exec($command, $output, $exitCode);

        File::deleteDirectory($profileDir);

        if ($exitCode !== 0) {
            throw new \RuntimeException('PDF conversion failed. Ensure LibreOffice is installed. Output: '.implode("\n", $output));
        }

        $pdfPath = preg_replace('/\.docx$/i', '.pdf', $docxPath);

        if (! file_exists($pdfPath)) {
            throw new \RuntimeException('PDF file was not generated at expected path: '.$pdfPath);
        }

        return $pdfPath;
    }

    /**
     * Remove temporary generated files.
     */
    public function cleanup(string ...$paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Check if a template exists for the given certificate type.
     */
    public function hasTemplate(string $type): bool
    {
        return $this->templatePathFor($type) !== null;
    }
}
