<?php

namespace App\Jobs;

use App\Exports\LedgerExport;
use App\Exports\RowsExport;
use App\Models\Company;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Reporting\ReportData;
use App\Services\Tenancy\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $companyId, public int $exportId) {}

    public function handle(): void
    {
        $company = Company::findOrFail($this->companyId);
        app(CompanyContext::class)->run($company, function () {
            $export = ReportExport::findOrFail($this->exportId);
            if ($export->status === 'ready') {
                return;
            }
            $user = User::findOrFail($export->user_id);
            if (! $user->hasCompanyPermission('report.view')) {
                $export->update(['status' => 'failed', 'error' => 'Report permission was revoked.']);

                return;
            }
            $export->update(['status' => 'processing']);
            $filters = $export->filters;
            $path = 'companies/'.$export->company_id.'/exports/'.$export->id.'.'.$export->format;
            try {
                if (in_array($export->report, ['general-ledger', 'account-transactions']) && $export->format === 'xlsx') {
                    Excel::store(new LedgerExport($filters['from'], $filters['to'], $filters['account_id'] ?? null), $path, $export->disk);
                } else {
                    $data = app(ReportData::class)->build($export->report, $filters['from'], $filters['to'], $filters['contact_id'] ?? null, $filters['account_id'] ?? null);
                    if ($data instanceof LengthAwarePaginator) {
                        $all = [];
                        for ($page = 1; $page <= $data->lastPage(); $page++) {
                            $batch = app(ReportData::class)->build($export->report, $filters['from'], $filters['to'], null, $filters['account_id'] ?? null, $page);
                            $all = array_merge($all, app(ReportData::class)->flatten($batch));
                        }$rows = $all;
                    } else {
                        $rows = app(ReportData::class)->flatten($data);
                    }
                    $headers = empty($rows) ? [] : array_keys($rows[0]);
                    if ($export->format === 'xlsx') {
                        Excel::store(new RowsExport(array_map('array_values', $rows), $headers), $path, $export->disk);
                    } else {
                        $report = $export->report;
                        $from = $filters['from'];
                        $to = $filters['to'];
                        $pdf = Pdf::loadView('pdf.report', compact('report', 'rows', 'headers', 'from', 'to'))->setOption('isRemoteEnabled', false)->output();
                        Storage::disk($export->disk)->put($path, $pdf);
                    }
                }
                $export->update(['status' => 'ready', 'path' => $path, 'error' => null]);
            } catch (\Throwable $error) {
                $export->update(['status' => 'failed', 'error' => 'Export generation failed. Please retry or contact the workspace administrator.']);
                throw $error;
            }
        });
    }
}
