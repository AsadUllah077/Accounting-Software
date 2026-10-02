<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditRequest;
use App\Models\BankAccount;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\Invoicing\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CreditController extends Controller
{
    public function index()
    {
        Gate::authorize('permission', 'invoice.view');

        return Inertia::render('Documents/Credits', ['notes' => CreditNote::with(['invoice.customer', 'items', 'refunds'])->latest('id')->paginate(20), 'banks' => BankAccount::get(['id', 'name'])]);
    }

    public function store(CreditRequest $request, int $invoice, CreditService $service)
    {
        $record = Invoice::findOrFail($invoice);
        Gate::authorize('approve', $record);
        $service->issue($record, $request->validated(), $request->user()->id);

        return back()->with('success', 'Credit note posted.');
    }

    public function refund(Request $request, int $id, CreditService $service)
    {
        Gate::authorize('permission', 'invoice.pay');
        $data = $request->validate(['amount' => 'required|decimal:0,2|gt:0', 'bank_account_id' => 'required|integer', 'date' => 'required|date_format:Y-m-d', 'idempotency_key' => 'required|string|max:100']);
        $service->refund(CreditNote::findOrFail($id), $data, $request->user()->id);

        return back()->with('success','Customer refund recorded.');
    }
}
