<?php

namespace App\Http\Controllers;

use App\Models\Blotter;
use App\Services\CertificateDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BlotterDownloadController extends Controller
{
    public function __invoke(Request $request, Blotter $blotter, CertificateDocumentService $service): BinaryFileResponse
    {
        abort_unless($service->hasTemplate('blotter'), 404, 'No template available for blotter reports.');

        $user = $request->user();
        $isStaffOrAdmin = $user->hasRole(['admin', 'staff']);
        $isOwner = $user->resident && $user->resident->id === $blotter->resident_id;

        abort_unless($isStaffOrAdmin || $isOwner, 403);

        $validated = $request->validate([
            'format' => ['required', 'in:docx,pdf'],
            'date_of_issuance' => ['required', 'date'],
        ]);

        $blotter->load('resident');

        $docxPath = $service->generateBlotter($blotter, $validated);

        if ($validated['format'] === 'pdf') {
            $pdfPath = $service->convertToPdf($docxPath);
            $service->cleanup($docxPath);

            $filename = "Blotter_Report_{$blotter->blotter_number}.pdf";

            return response()
                ->download($pdfPath, $filename, ['Content-Type' => 'application/pdf'])
                ->deleteFileAfterSend(true);
        }

        $filename = "Blotter_Report_{$blotter->blotter_number}.docx";

        return response()
            ->download($docxPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend(true);
    }
}
