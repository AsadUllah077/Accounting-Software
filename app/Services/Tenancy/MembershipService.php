<?php

namespace App\Services\Tenancy;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\Audit;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MembershipService
{
    public function invite(array $data): string
    {
        return DB::transaction(function () use ($data) {
            $company = Company::lockForUpdate()->findOrFail(app(CompanyContext::class)->id());
            $pending = CompanyInvitation::whereNull('accepted_at')->where('expires_at', '>', now())->count();
            app(SubscriptionService::class)->assertLimit('users', $company->users()->wherePivot('is_active', true)->count() + $pending);
            $role = Role::findOrFail($data['role_id']);
            $token = Str::random(64);
            $invitation = CompanyInvitation::create(['email' => strtolower($data['email']), 'role_id' => $role->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
            Audit::record('company.invited', $invitation, [], ['email' => $invitation->email, 'role' => $role->name]);

            return $token;
        });
    }

    public function accept(User $user, string $token): int
    {
        return DB::transaction(function () use ($user, $token) {
            $row = DB::table('company_invitations')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $row || $row->accepted_at || now()->greaterThan($row->expires_at) || strcasecmp($row->email, $user->email) !== 0) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is expired, used, or belongs to a different email address.']);
            }
            $company = Company::lockForUpdate()->findOrFail($row->company_id);
            app(CompanyContext::class)->run($company, fn () => app(SubscriptionService::class)->assertLimit('users', $company->users()->wherePivot('is_active', true)->count()));
            if ($company->users()->where('users.id', $user->id)->exists()) {
                throw ValidationException::withMessages(['invitation' => 'You already belong to this company.']);
            }
            $company->users()->attach($user->id, ['role_id' => $row->role_id, 'is_active' => true]);
            DB::table('company_invitations')->where('id', $row->id)->update(['accepted_at' => now(), 'updated_at' => now()]);
            app(CompanyContext::class)->run($company, fn () => Audit::record('company.invitation_accepted', $user));

            return $company->id;
        });
    }

    public function assign(int $userId, int $roleId, bool $active): void
    {
        DB::transaction(function () use ($userId, $roleId, $active) {
            $company = app(CompanyContext::class)->company();
            $company->newQuery()->lockForUpdate()->findOrFail($company->id);
            $member = $company->users()->findOrFail($userId);
            $role = Role::findOrFail($roleId);
            $owner = Role::where('name', 'Owner')->firstOrFail();
            $ownerCount = $company->users()->wherePivot('role_id', $owner->id)->wherePivot('is_active', true)->count();
            if ($member->pivot->role_id === $owner->id && $member->pivot->is_active && $ownerCount <= 1 && (! $active || $roleId !== $owner->id)) {
                throw ValidationException::withMessages(['role_id' => 'A company must retain an active owner.']);
            }
            $company->users()->updateExistingPivot($userId, ['role_id' => $roleId, 'is_active' => $active]);
            Audit::record('permission.changed', $member, ['role_id' => $member->pivot->role_id], ['role_id' => $roleId, 'is_active' => $active]);
        });
    }
}
