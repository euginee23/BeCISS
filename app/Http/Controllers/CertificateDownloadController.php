<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateDownloadController extends Controller
{
    public function __invoke(Request $request, Certificate $certificate, CertificateDocumentService $service): BinaryFileResponse
    {
        abort_unless($service->hasTemplate($certificate->type), 404, 'No template available for this certificate type.');

        $user = $request->user();
        $isStaffOrAdmin = $user->hasRole(['admin', 'staff']);
        $isOwner = $user->resident && $user->resident->id === $certificate->resident_id;

        abort_unless($isStaffOrAdmin || $isOwner, 403);

        $validated = $request->validate([
            'format' => ['required', 'in:docx,pdf'],
            'date_of_issuance' => ['required', 'date'],
            'ctc_no' => ['nullable', 'string', 'max:100'],
            'ctc_place_issued' => ['nullable', 'string', 'max:200'],
            'ctc_date_issued' => ['nullable', 'date'],
        ]);

        $certificate->load('resident');

        $docxPath = $service->generate($certificate, $validated);

        if ($validated['format'] === 'pdf') {
            $pdfPath = $service->convertToPdf($docxPath);
            $service->cleanup($docxPath);

            $filename = "Certificate_of_Residency_{$certificate->certificate_number}.pdf";

            return response()
                ->download($pdfPath, $filename, ['Content-Type' => 'application/pdf'])
                ->deleteFileAfterSend(true);
        }

        $filename = "Certificate_of_Residency_{$certificate->certificate_number}.docx";

        return response()
            ->download($docxPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend(true);
    }
}
