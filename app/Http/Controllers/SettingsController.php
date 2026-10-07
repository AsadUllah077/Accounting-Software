<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingsRequest;
use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TaxRate;
use App\Services\Audit\Audit;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CompanyHome;
use App\Services\Tenancy\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        Gate::authorize('permission', 'settings.manage');
        $company = app(CompanyContext::class)->company();

        return Inertia::render('Settings/Index', ['profile' => $company, 'members' => $company->users()->get(['users.id', 'users.name', 'users.email']), 'roles' => Role::with('permissions')->get(), 'taxRates' => TaxRate::get(), 'years' => FinancialYear::orderByDesc('start_date')->get(), 'invitationUrl' => session('invitation_url'), 'allPermissions' => Permission::all(), 'permissionOverrides' => DB::table('company_permission_overrides')->where('company_id', $company->id)->get()]);
    }

    public function update(SettingsRequest $request)
    {
        DB::transaction(function () use ($request) {
            $company = Company::lockForUpdate()->findOrFail(app(CompanyContext::class)->id());
            $data = $request->validated();
            if ($company->locked_through && (empty($data['locked_through']) || $data['locked_through'] < $company->locked_through->toDateString())) {
                throw ValidationException::withMessages(['locked_through' => 'Lock dates can only advance.']);
            }
            $old = $company->only(array_keys($data));
            $company->fill(collect($data)->except('locked_through')->all());
            $company->locked_through = $data['locked_through'] ?? null;
            $company->save();
            Audit::record('company.settings_updated', $company, $old, $data);
        });

        return back()->with('success', 'Company settings saved.');
    }

    public function invite(Request $request, MembershipService $service)
    {
        Gate::authorize('permission', 'users.manage');
        $data = $request->validate(['email' => 'required|email|max:255', 'role_id' => 'required|integer|exists:roles,id']);
        $token = $service->invite($data);

        return back()->with('invitation_url', url('/invitations/'.$token))->with('success', 'Invitation created. Share the secure link with the invited user.');
    }

    public function role(Request $request, int $id, MembershipService $service)
    {
        Gate::authorize('permission', 'users.manage');
        $data = $request->validate(['role_id' => 'required|integer|exists:roles,id', 'is_active' => 'required|boolean']);
        $service->assign($id, (int) $data['role_id'], (bool) $data['is_active']);

        return back()->with('success', 'Membership updated.');
    }

    public function accept(Request $request, string $token, MembershipService $service)
    {
        $company = $service->accept($request->user(), $token);
        $request->session()->put('company_id', $company);

        return redirect(app(CompanyHome::class)->path($request->user(), $company))->with('success', 'Invitation accepted.');
    }

    public function tax(Request $request)
    {
        Gate::authorize('permission', 'settings.manage');
        $data = $request->validate(['name' => 'required|string|max:100', 'rate' => 'required|decimal:0,4|min:0|max:100', 'type' => 'required|in:sales,purchases']);
        DB::transaction(function () use ($data) {
            $tax = TaxRate::create($data);
            Audit::record('tax.created', $tax, [], $data);
        });

        return back()->with('success', 'Tax rate created.');
    }

    public function year(Request $request)
    {
        Gate::authorize('permission', 'settings.manage');
        $data = $request->validate(['name' => 'required|string|max:50', 'start_date' => 'required|date_format:Y-m-d', 'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date']);
        DB::transaction(function () use ($data) {
            Company::lockForUpdate()->findOrFail(app(CompanyContext::class)->id());
            if (FinancialYear::where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])->exists()) {
                throw ValidationException::withMessages(['start_date' => 'Financial years cannot overlap.']);
            }$year = FinancialYear::create($data);
            Audit::record('financial_year.created', $year);
        });

        return back()->with('success', 'Financial year created.');
    }

    public function closeYear(int $id)
    {
        Gate::authorize('permission', 'settings.manage');
        DB::transaction(function () use ($id) {
            $company = Company::lockForUpdate()->findOrFail(app(CompanyContext::class)->id());
            $year = FinancialYear::lockForUpdate()->findOrFail($id);
            if ($year->end_date > now()->toDateString()) {
                throw ValidationException::withMessages(['year' => 'A future financial year cannot be closed.']);
            }$year->update(['is_closed' => true]);
            if (! $company->locked_through || $company->locked_through->toDateString() < $year->end_date) {
                $company->locked_through = $year->end_date;
                $company->save();
            }Audit::record('financial_year.closed', $year);
        });

        return back()->with('success', 'Financial year locked.');
    }
}
