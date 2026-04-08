<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\LineSpacingRule;

class GenerateCertificateTemplate extends Command
{
    protected $signature = 'app:generate-certificate-template';

    protected $description = 'Generate the DOCX template for Barangay Certification of Residency';

    public function handle(): int
    {
        $phpWord = new PhpWord;

        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        $this->buildHeader($section);
        $this->buildTitle($section);
        $this->buildBody($section);
        $this->buildSignature($section);
        $this->buildFooter($section);

        $outputPath = storage_path('word_templates/BARANGAY_RESIDENCY_TEMPLATE.docx');

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $phpWord->save($outputPath);

        $this->info("Template generated at: {$outputPath}");

        return self::SUCCESS;
    }

    private function buildHeader(Section $section): void
    {
        $headerStyle = ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'spaceBefore' => 0];

        $section->addText('Republic of the Philippines', ['size' => 11, 'italic' => true], $headerStyle);
        $section->addText('${province_name}', ['size' => 11, 'bold' => true], $headerStyle);
        $section->addText('Municipality of ${municipality_name}', ['size' => 11, 'italic' => true], $headerStyle);
        $section->addText('${barangay_name}', ['size' => 11, 'bold' => true, 'allCaps' => true], $headerStyle);

        $section->addText('', [], ['spaceAfter' => 0, 'spaceBefore' => 0]);

        $lineRun = $section->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
        $lineRun->addText('OFFICE OF THE PUNONG BARANGAY', [
            'size' => 11,
            'bold' => true,
            'underline' => 'single',
        ]);
    }

    private function buildTitle(Section $section): void
    {
        $section->addText('', [], ['spaceAfter' => 200]);

        $titleRun = $section->addTextRun(['alignment' => Jc::CENTER, 'spaceAfter' => 300]);
        $titleRun->addText('BARANGAY CERTIFICATION for RESIDENCY', [
            'size' => 16,
            'bold' => true,
            'italic' => true,
            'color' => 'FF8C00',
        ]);
    }

    private function buildBody(Section $section): void
    {
        $bodyStyle = [
            'alignment' => Jc::BOTH,
            'spaceAfter' => 120,
            'lineSpacingRule' => LineSpacingRule::AUTO,
            'lineSpacing' => 276,
        ];
        $indentedStyle = array_merge($bodyStyle, ['indentation' => ['firstLine' => 720]]);
        $normal = ['size' => 12];
        $italic = ['size' => 12, 'italic' => true];
        $bold = ['size' => 12, 'bold' => true];

        $section->addText('TO WHOM IT MAY CONCERN!', ['size' => 12, 'bold' => true], $bodyStyle);

        $p1 = $section->addTextRun($indentedStyle);
        $p1->addText('THIS IS TO CERTIFY that ', $normal);
        $p1->addText('${resident_name}', $italic);
        $p1->addText(' the following name mentioned below is residing at ', $normal);
        $p1->addText('${resident_address}', $italic);
        $p1->addText('.', $normal);

        $p2 = $section->addTextRun($indentedStyle);
        $p2->addText('THIS IS TO CERTIFY FURTHER that THE NAME MENTION ABOVE WAS TRULY BONA-FIED RESIDENT of ', $normal);
        $p2->addText('${barangay_name}', $italic);
        $p2->addText(', ', $normal);
        $p2->addText('${municipality_name}', $italic);
        $p2->addText(', ', $normal);
        $p2->addText('${province_name}', $italic);
        $p2->addText('.', $normal);

        $p3 = $section->addTextRun($indentedStyle);
        $p3->addText('THIS CERTIFICATION is issued upon the request of the interested person for any legal purposes may serve them best.', $normal);

        $p4 = $section->addTextRun($indentedStyle);
        $p4->addText('ISSUED and given this ', $normal);
        $p4->addText('${issued_full_date}', $bold);
        $p4->addText(' at ', $normal);
        $p4->addText('${barangay_name}', $italic);
        $p4->addText(', ', $normal);
        $p4->addText('${municipality_name}', $italic);
        $p4->addText(', ', $normal);
        $p4->addText('${province_name}', $italic);
        $p4->addText(', Philippines.', $normal);
    }

    private function buildSignature(Section $section): void
    {
        $section->addText('', [], ['spaceAfter' => 0, 'spaceBefore' => 0]);
        $section->addText('', [], ['spaceAfter' => 0, 'spaceBefore' => 0]);

        $section->addText('${punong_barangay_name}', [
            'size' => 12,
            'bold' => true,
            'underline' => 'single',
        ], [
            'alignment' => Jc::START,
            'spaceAfter' => 0,
        ]);

        $section->addText('Punong Barangay', [
            'size' => 12,
            'italic' => true,
        ], [
            'alignment' => Jc::START,
            'indentation' => ['firstLine' => 720],
            'spaceAfter' => 0,
        ]);
    }

    private function buildFooter(Section $section): void
    {
        $section->addText('', [], ['spaceAfter' => 0, 'spaceBefore' => 0]);
        $section->addText('', [], ['spaceAfter' => 0, 'spaceBefore' => 0]);
        $section->addText('', [], ['spaceAfter' => 0, 'spaceBefore' => 0]);

        $section->addText('Not Valid Without Official Seal', [
            'size' => 11,
            'italic' => true,
        ], [
            'alignment' => Jc::END,
        ]);
    }
}
