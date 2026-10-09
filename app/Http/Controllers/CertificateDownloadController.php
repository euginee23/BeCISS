<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateDownloadController extends Controller
{
    /**
     * Certificates are only ever issued as PDF, using the issuance details
     * saved on the record so every reprint matches the original.
     */
    public function __invoke(Request $request, Certificate $certificate, CertificateDocumentService $service): BinaryFileResponse
    {
        $user = $request->user();
        $isStaffOrAdmin = $user->hasRole(['admin', 'staff']);
        $isOwner = $user->resident && $user->resident->id === $certificate->resident_id;

        abort_unless($isStaffOrAdmin || $isOwner, 403);

        $mayDownload = $isStaffOrAdmin
            ? $certificate->isPrintable()
            : $certificate->status === 'completed';

        abort_unless($mayDownload, 403, 'This certificate is not available for download yet.');
        abort_unless($service->hasTemplate($certificate->type), 404, 'No template available for this certificate type.');

        $certificate->load('resident', 'certificateType');

        $pdfPath = $service->generatePdf($certificate);

        $typeLabel = str_replace(' ', '_', $certificate->type_label);

        return response()
            ->download($pdfPath, "{$typeLabel}_{$certificate->certificate_number}.pdf", ['Content-Type' => 'application/pdf'])
            ->deleteFileAfterSend(true);
    }
}
