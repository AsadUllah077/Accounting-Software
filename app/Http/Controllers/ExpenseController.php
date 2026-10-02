<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\TaxRate;
use App\Services\Accounting\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('permission', 'expense.view');

        $search = mb_substr((string) $request->query('search', ''), 0, 100);

        return Inertia::render('Purchases/Expenses', ['search' => $search, 'expenses' => Expense::with(['category', 'bankAccount'])->when($search, fn ($query) => $query->where('description', 'like', '%'.$search.'%'))->latest('id')->paginate(20)->withQueryString(), 'categories' => ExpenseCategory::get(['id', 'name']), 'banks' => BankAccount::get(['id', 'name']), 'taxRates' => TaxRate::where('is_active', true)->get(['id', 'name'])]);
    }

    public function store(ExpenseRequest $request, ExpenseService $service)
    {
        $service->create($request->validated(), $request->user()->id);

        return back()->with('success', 'Expense recorded and posted.');
    }

    public function show(int $id)
    {
        Gate::authorize('permission', 'expense.view');
        $expense = Expense::with(['category', 'bankAccount', 'attachments'])->findOrFail($id);
        if (Gate::allows('permission', 'accounting.view')) {
            $expense->load('journal.lines.account');
        }

        return Inertia::render('Purchases/ExpenseDetail', ['expense' => $expense]);
    }
}
