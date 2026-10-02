<?php

namespace App\Http\Controllers;

use App\Exports\BusinessRecordsExport;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class BusinessExportController extends Controller
{
    public function download(string $kind)
    {
        abort_unless(in_array($kind, ['invoices', 'bills', 'customers', 'suppliers']), 404);
        Gate::authorize('permission', match ($kind) {
            'invoices' => 'invoice.view','bills' => 'bill.view',default => 'contact.view'
        });

        return Excel::download(new BusinessRecordsExport($kind), $kind.'.xlsx');
    }
}
