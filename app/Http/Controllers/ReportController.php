<?php

namespace App\Http\Controllers;

use App\Exports\RowsExport;
use App\Http\Requests\ReportRequest;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Services\Reporting\ReportData;
use App\Services\Tenancy\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function show(ReportRequest $request, string $report, ReportData $service)
    {
        [$from,$to] = $request->dates();
        if ($request->filled('format') && in_array($report, ['general-ledger', 'account-transactions'])) {
            $export = ReportExport::create(['user_id' => $request->user()->id, 'report' => $report, 'format' => $request->query('format'), 'filters' => ['from' => $from, 'to' => $to, 'account_id' => $request->integer('account_id') ?: null], 'disk' => config('filesystems.attachments_disk')]);
            GenerateReportExport::dispatch(app(CompanyContext::class)->id(), $export->id)->afterCommit();

            return redirect('/exports')->with('success', 'Your complete report is being generated in the background.');
        }
        $data = $service->build($report, $from, $to, $request->integer('contact_id') ?: null, $request->integer('account_id') ?: null);
        $rows = $service->flatten($data);
        $headers = empty($rows) ? [] : array_keys($rows[0]);
        if ($request->query('format') === 'xlsx') {
            return Excel::download(new RowsExport(array_map('array_values', $rows), $headers), $report.'.xlsx');
        }
        if ($request->query('format') === 'pdf') {
            return Pdf::loadView('pdf.report', compact('report', 'rows', 'headers', 'from', 'to'))->setOption('isRemoteEnabled', false)->download($report.'.pdf');
        }

        return Inertia::render('Reports/Show', compact('report', 'data', 'rows', 'headers', 'from', 'to'));
    }
}
