<?php

namespace App\Services\Tenancy;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CompanyPermissions
{
    public function names(User $user, int $companyId): array
    {
        $membership = DB::table('company_users')->where('company_id', $companyId)->where('user_id', $user->id)->where('is_active', true)->first();
        if (! $membership) {
            return [];
        }
        $role = Role::with('permissions')->findOrFail($membership->role_id);
        $names = $role->permissions->pluck('name')->all();
        if ($role->name === 'Owner') {
            return $names;
        }
        $overrides = DB::table('company_permission_overrides')->join('permissions', 'permissions.id', '=', 'company_permission_overrides.permission_id')->where('company_id', $companyId)->where('role_id', $role->id)->get(['permissions.name', 'allowed']);
        foreach ($overrides as $override) {
            if ($override->allowed) {
                $names[] = $override->name;
            } else {
                $names = array_diff($names, [$override->name]);
            }
        }

        return array_values(array_unique($names));
    }
}
