<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionRun;
use App\Services\Exports\CommissionReportExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CommissionExportController extends Controller
{
    public function pdf(Request $request, CommissionRun $commission, CommissionReportExportService $exporter): Response
    {
        $filename = sprintf('commission-report-%04d-%02d.pdf', $commission->year, $commission->month);

        return response($exporter->pdf($commission, $request), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function excel(Request $request, CommissionRun $commission, CommissionReportExportService $exporter): BinaryFileResponse
    {
        $filename = sprintf('commission-report-%04d-%02d.xls', $commission->year, $commission->month);

        return response()
            ->download($exporter->excelPath($commission, $request), $filename, [
                'Content-Type' => 'application/vnd.ms-excel',
            ])
            ->deleteFileAfterSend();
    }
}
