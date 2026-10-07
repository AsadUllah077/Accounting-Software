<?php

namespace App\Services\Tenancy;

use App\Models\User;

final class CompanyHome
{
    public function path(User $user, int $companyId): string
    {
        $permissions = app(CompanyPermissions::class)->names($user, $companyId);
        foreach (['report.view' => '/dashboard', 'invoice.view' => '/documents/invoices', 'bill.view' => '/documents/bills', 'expense.view' => '/expenses', 'contact.view' => '/contacts/customers', 'accounting.view' => '/accounting/accounts', 'banking.view' => '/banking', 'users.manage' => '/settings/users', 'settings.manage' => '/settings'] as $permission => $path) {
            if (in_array($permission, $permissions, true)) {
                return $path;
            }
        }

        return '/profile';
    }
}
