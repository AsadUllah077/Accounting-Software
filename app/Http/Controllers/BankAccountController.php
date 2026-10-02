<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankAccount;
use App\Services\Audit\Audit;
use App\Services\Tenancy\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BankAccountController extends Controller
{
    public function store(Request $request)
    {
        Gate::authorize('permission', 'settings.manage');
        $data = $request->validate(['name' => 'required|string|max:255', 'bank_name' => 'nullable|string|max:255', 'account_number' => 'nullable|string|max:100', 'account_id' => 'required|integer']);
        DB::transaction(function () use ($data) {
            $account = Account::lockForUpdate()->findOrFail($data['account_id']);
            if ($account->type !== 'asset' || ! $account->is_active || $account->system_key && $account->system_key !== 'bank' || BankAccount::where('account_id', $account->id)->exists()) {
                throw ValidationException::withMessages(['account_id' => 'Select an active asset account that is not already linked to a bank or used as a control account.']);
            }$bank = BankAccount::create($data + ['currency' => app(CompanyContext::class)->company()->currency]);
            Audit::record('bank_account.created', $bank);
        });

        return back()->with('success', 'Bank account created.');
    }
}
