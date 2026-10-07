<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCompanyUserRequest;
use App\Models\CompanyInvitation;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\Audit;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CompanyUserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('permission', 'users.manage');
        $company = app(CompanyContext::class)->company();
        $search = mb_substr((string) $request->query('search', ''), 0, 100);

        return Inertia::render('Settings/Users', [
            'members' => $company->users()->when($search, fn ($query) => $query->where(fn ($query) => $query->whereLike('users.name', '%'.$search.'%')->orWhereLike('users.email', '%'.$search.'%')))->orderBy('users.name')->paginate(15, ['users.id', 'users.name', 'users.email', 'users.email_verified_at'])->withQueryString(),
            'roles' => Role::with('permissions')->get(), 'allPermissions' => Permission::orderBy('name')->get(),
            'permissionOverrides' => DB::table('company_permission_overrides')->where('company_id', $company->id)->get(),
            'invitations' => CompanyInvitation::with('role')->whereNull('accepted_at')->where('expires_at', '>', now())->latest('id')->get(['id', 'email', 'role_id', 'expires_at']),
            'invitationUrl' => session('invitation_url'), 'search' => $search,
        ]);
    }

    public function store(CreateCompanyUserRequest $request, MembershipService $service)
    {
        $service->createUser($request->validated());

        return back()->with('success', 'User created. They must verify their email before accessing the company.');
    }

    public function revokeInvitation(int $id)
    {
        Gate::authorize('permission', 'users.manage');
        DB::transaction(function () use ($id) {
            $invitation = CompanyInvitation::whereNull('accepted_at')->lockForUpdate()->findOrFail($id);
            Audit::record('company.invitation_revoked', $invitation);
            $invitation->delete();
        });

        return back()->with('success', 'Invitation revoked.');
    }
}
