<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Exports\AffiliateExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AffiliateExportController extends Controller
{
    public function __invoke(Request $request, AffiliateExportService $exporter): BinaryFileResponse
    {
        return response()
            ->download($exporter->excelPath($request), 'affiliate-list-'.now()->format('Y-m-d').'.xls', [
                'Content-Type' => 'application/vnd.ms-excel',
            ])
            ->deleteFileAfterSend();
    }
}
