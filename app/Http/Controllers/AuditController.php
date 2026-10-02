<?php

namespace App\Http\Controllers;

use App\Services\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index()
    {
        Gate::authorize('permission', 'settings.manage');

        return Inertia::render('Settings/Audit', ['logs' => DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->where('audit_logs.company_id', app(CompanyContext::class)->id())->select('audit_logs.*', 'users.name as user_name')->orderByDesc('audit_logs.id')->paginate(30)]);
    }
}
