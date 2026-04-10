<?php

namespace App\Services;

use App\Models\BarangayProfile;
use App\Models\Blotter;
use App\Models\Certificate;
use Carbon\Carbon;
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
     * Generate a filled DOCX document for the given certificate.
     *
     * @param  array{date_of_issuance: string, ctc_no: string, ctc_place_issued: string, ctc_date_issued: string}  $formData
     */
    public function generate(Certificate $certificate, array $formData): string
    {
        $templateFile = self::TEMPLATES[$certificate->type]
            ?? throw new \InvalidArgumentException("No template available for certificate type: {$certificate->type}");

        $templatePath = storage_path("word_templates/{$templateFile}");

        $template = new TemplateProcessor($templatePath);

        $template->setValues($this->getPlaceholderValues($certificate, $formData));

        $outputDir = storage_path('app/private/certificates');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filename = "certificate_{$certificate->id}_".time().'.docx';
        $outputPath = "{$outputDir}/{$filename}";

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

        $outputDir = storage_path('app/private/blotters');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filename = "blotter_{$blotter->id}_".time().'.docx';
        $outputPath = "{$outputDir}/{$filename}";

        $template->saveAs($outputPath);

        return $outputPath;
    }

    /**
     * Get the placeholder values for the given certificate type.
     *
     * @param  array{date_of_issuance: string, ctc_no: string, ctc_place_issued: string, ctc_date_issued: string}  $formData
     * @return array<string, string>
     */
    private function getPlaceholderValues(Certificate $certificate, array $formData): array
    {
        $resident = $certificate->resident;
        $barangay = BarangayProfile::get();

        $residentAddress = collect([
            $resident->purok ? "Purok {$resident->purok}." : null,
            $resident->address,
        ])->filter()->implode(', ');

        $issuanceDate = Carbon::parse($formData['date_of_issuance']);

        return match ($certificate->type) {
            'barangay_clearance' => [
                'resident_name' => $resident->full_name,
                'resident_address' => $residentAddress,
                'barangay_name' => $barangay->barangay_name,
                'municipality_name' => $barangay->municipality ?? '',
                'province_name' => $barangay->province ?? '',
                'issued_full_date' => $issuanceDate->format('F j, Y'),
                'punong_barangay_name' => $barangay->captain_name ?? '',
            ],
            'certificate_of_indigency' => [
                'resident_name' => $resident->full_name,
                'resident_address' => $residentAddress,
                'barangay_name' => $barangay->barangay_name,
                'municipality_name' => $barangay->municipality ?? '',
                'province_name' => $barangay->province ?? '',
                'issue_day' => $issuanceDate->format('j'),
                'issue_month' => $issuanceDate->format('F Y'),
                'date_issued' => $issuanceDate->format('F j, Y'),
                'punong_barangay_name' => $barangay->captain_name ?? '',
            ],
            'barangay_certification' => [
                'resident_name' => $resident->full_name,
                'resident_address' => $residentAddress,
                'barangay_name' => $barangay->barangay_name,
                'municipality_name' => $barangay->municipality ?? '',
                'province_name' => $barangay->province ?? '',
                'issue_day' => $issuanceDate->format('j'),
                'issue_month' => $issuanceDate->format('F'),
                'issue_year' => $issuanceDate->format('Y'),
                'date_issued' => $issuanceDate->format('F j, Y'),
                'or_number' => $certificate->or_number ?? '',
                'amount_paid' => number_format((float) $certificate->fee, 2),
                'punong_barangay_name' => $barangay->captain_name ?? '',
            ],
            default => [
                'resident_name' => $resident->full_name,
                'resident_address' => $residentAddress,
                'barangay_name' => $barangay->barangay_name,
                'municipality_name' => $barangay->municipality ?? '',
                'province_name' => $barangay->province ?? '',
                'issued_full_date' => $issuanceDate->format('F j, Y'),
                'punong_barangay_name' => $barangay->captain_name ?? '',
            ],
        };
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

        if ($blotter->is_walkin) {
            $complainantName = $blotter->complainant_name ?? '';
            $complainantAddress = collect([
                $blotter->complainant_purok ? "Purok {$blotter->complainant_purok}" : null,
                $blotter->complainant_house_number,
                $blotter->complainant_street,
            ])->filter()->implode(', ');
            $purokName = $blotter->complainant_purok ?? '';
        } else {
            $resident = $blotter->resident;
            $complainantName = $resident->full_name;
            $complainantAddress = collect([
                $resident->purok ? "Purok {$resident->purok}." : null,
                $resident->address,
            ])->filter()->implode(', ');
            $purokName = $resident->purok ?? '';
        }

        return [
            'resident_name' => $complainantName,
            'resident_address' => $complainantAddress,
            'incident_type' => $blotter->type_label,
            'purok_name' => $purokName,
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

        $command = sprintf(
            'libreoffice --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($outputDir),
            escapeshellarg($docxPath),
        );

        exec($command, $output, $exitCode);

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
        $template = self::TEMPLATES[$type] ?? null;

        return $template && file_exists(storage_path("word_templates/{$template}"));
    }
}
