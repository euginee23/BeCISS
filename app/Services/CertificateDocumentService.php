<?php

namespace App\Services;

use App\Models\BarangayProfile;
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

        $resident = $certificate->resident;
        $barangay = BarangayProfile::get();

        $residentAddress = collect([
            $resident->purok,
            $resident->address,
        ])->filter()->implode(', ');

        $template->setValues([
            'resident_name' => $resident->full_name,
            'resident_address' => $residentAddress,
            'barangay_name' => $barangay->barangay_name,
            'municipality_name' => $barangay->municipality ?? '',
            'province_name' => $barangay->province ?? '',
            'issued_full_date' => Carbon::parse($formData['date_of_issuance'])->format('F j, Y'),
            'punong_barangay_name' => $barangay->captain_name ?? '',
        ]);

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
