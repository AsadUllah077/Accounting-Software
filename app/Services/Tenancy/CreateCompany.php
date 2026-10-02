<?php

namespace App\Services\Tenancy;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\ExpenseCategory;
use App\Models\FinancialYear;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Support\Facades\DB;

final class CreateCompany
{
    public function execute(User $user, array $data): Company
    {
        return DB::transaction(function () use ($user, $data) {
            $company = Company::create($data);
            $company->users()->attach($user->id, ['role_id' => Role::where('name', 'Owner')->firstOrFail()->id, 'is_active' => true]);
            app(CompanyContext::class)->run($company, function () use ($company) {
                foreach ([['1000', 'Operating bank', 'asset', 'bank'], ['1100', 'Accounts receivable', 'asset', 'receivables'], ['1200', 'Input tax', 'asset', 'input_tax'], ['2000', 'Accounts payable', 'liability', 'payables'], ['2100', 'Output tax', 'liability', 'output_tax'], ['3000', 'Owner equity', 'equity', 'equity'], ['4000', 'Sales revenue', 'revenue', 'sales'], ['5000', 'General expenses', 'expense', 'expenses']] as [$code,$name,$type,$system]) {
                    Account::create(['code' => $code, 'name' => $name, 'type' => $type, 'system_key' => $system, 'cash_flow_class' => $type === 'equity' ? 'financing' : 'operating']);
                }
                BankAccount::create(['name' => 'Operating bank', 'currency' => $company->currency, 'account_id' => Account::where('system_key', 'bank')->firstOrFail()->id]);
                ExpenseCategory::create(['name' => 'General expenses', 'account_id' => Account::where('system_key', 'expenses')->firstOrFail()->id]);
                TaxRate::create(['name' => 'No tax', 'rate' => '0.0000', 'is_default' => true]);
                foreach (['invoice', 'bill', 'credit'] as $type) {
                    DocumentSequence::create(['type' => $type]);
                }
                $start = $company->financial_year_start_date;
                FinancialYear::create(['name' => $start->year.' / '.$start->copy()->addYear()->year, 'start_date' => $start->toDateString(), 'end_date' => $start->copy()->addYear()->subDay()->toDateString()]);
                $plan = SubscriptionPlan::where('is_active', true)->orderBy('id')->first();
                if ($plan) {
                    app(SubscriptionService::class)->choose($plan->id);
                }
            });

            return $company;
        });
    }
}
