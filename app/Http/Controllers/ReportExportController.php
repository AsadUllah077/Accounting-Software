<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReportExportController extends Controller
{
    public function index()
    {
        Gate::authorize('permission', 'report.view');

        return Inertia::render('Reports/Exports', ['exports' => ReportExport::where('user_id', auth()->id())->latest('id')->paginate(20)]);
    }

    public function download(int $id)
    {
        Gate::authorize('permission', 'report.view');
        $export = ReportExport::where('user_id', auth()->id())->where('status', 'ready')->findOrFail($id);

        return Storage::disk($export->disk)->download($export->path, $export->report.'.'.$export->format, ['X-Content-Type-Options' => 'nosniff']);
    }
}
