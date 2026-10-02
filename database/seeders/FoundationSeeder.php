<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['USD', 'US Dollar', '$'], ['GBP', 'British Pound', '£'], ['EUR', 'Euro', '€'], ['PKR', 'Pakistani Rupee', 'Rs']] as [$code,$name,$symbol]) {
            Currency::firstOrCreate(['code' => $code], ['name' => $name, 'symbol' => $symbol]);
        }
        $names = ['contact.view', 'contact.create', 'contact.update', 'contact.delete', 'invoice.view', 'invoice.create', 'invoice.update', 'invoice.delete', 'invoice.approve', 'invoice.send', 'invoice.void', 'invoice.pay', 'bill.view', 'bill.create', 'bill.approve', 'bill.pay', 'expense.view', 'expense.create', 'expense.update', 'expense.delete', 'accounting.view', 'accounting.post', 'accounting.reverse', 'banking.view', 'banking.reconcile', 'report.view', 'settings.manage', 'users.manage'];
        $names = array_merge($names, ['bill.update', 'bill.delete', 'bill.void']);
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
        $roles = ['Owner' => $names, 'Administrator' => $names,
            'Accountant' => array_values(array_diff($names, ['settings.manage', 'users.manage'])),
            'Sales Manager' => ['contact.view', 'contact.create', 'contact.update', 'invoice.view', 'invoice.create', 'invoice.update', 'invoice.approve', 'invoice.send'],
            'Employee' => ['contact.view', 'expense.view', 'expense.create'],
            'Read-only User' => array_values(array_filter($names, fn ($name) => str_ends_with($name, '.view')))];
        foreach ($roles as $name => $permissions) {
            Role::firstOrCreate(['name' => $name])->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));
        }
        SubscriptionPlan::firstOrCreate(['name' => 'Essential'], ['price' => '29.00', 'features' => ['users' => 5, 'invoices_per_month' => 100]]);
        SubscriptionPlan::firstOrCreate(['name' => 'Growth'], ['price' => '79.00', 'features' => ['users' => 20, 'invoices_per_month' => 1000]]);
    }
}
