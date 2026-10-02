<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\Audit;
use App\Services\Tenancy\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PermissionController extends Controller
{
    public function update(Request $request, int $roleId)
    {
        Gate::authorize('permission', 'users.manage');
        $role = Role::findOrFail($roleId);
        if ($role->name === 'Owner') {
            throw ValidationException::withMessages(['permissions' => 'Owner permissions cannot be reduced.']);
        }
        $data = $request->validate(['permissions' => 'present|array', 'permissions.*' => 'integer|distinct|exists:permissions,id']);
        DB::transaction(function () use ($data, $role) {
            $company = app(CompanyContext::class)->id();
            DB::table('company_permission_overrides')->where('company_id', $company)->where('role_id', $role->id)->delete();
            foreach (Permission::all() as $permission) {
                DB::table('company_permission_overrides')->insert(['company_id' => $company, 'role_id' => $role->id, 'permission_id' => $permission->id, 'allowed' => in_array($permission->id, $data['permissions']), 'created_at' => now(), 'updated_at' => now()]);
            }Audit::record('role.permissions_changed', $role, [], ['permissions' => $data['permissions']]);
        });

        return back()->with('success', 'Role permissions updated for this company.');
    }
}
