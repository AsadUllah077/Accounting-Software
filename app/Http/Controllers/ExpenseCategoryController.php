<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Services\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryController extends Controller
{
    public function store(Request $request)
    {
        Gate::authorize('permission', 'settings.manage');
        $data = $request->validate(['name' => 'required|string|max:255', 'account_id' => 'required|integer', 'parent_id' => 'nullable|integer']);
        $account = Account::where('is_active', true)->findOrFail($data['account_id']);
        if ($account->type !== 'expense') {
            throw ValidationException::withMessages(['account_id' => 'Choose an expense account.']);
        }
        if (! empty($data['parent_id'])) {
            ExpenseCategory::findOrFail($data['parent_id']);
        }DB::transaction(function () use ($data) {
            $category = ExpenseCategory::create($data);
            Audit::record('expense_category.created', $category);
        });

        return back()->with('success', 'Expense category created.');
    }
}
