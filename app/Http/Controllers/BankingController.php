<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatementRequest;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\JournalLine;
use App\Models\Reconciliation;
use App\Models\ReconciliationSession;
use App\Services\Banking\ReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BankingController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('permission', 'banking.view');
        $banks = BankAccount::get();
        $selected = (int) $request->query('bank', $banks->first()?->id);
        $bank = BankAccount::findOrFail($selected);
        $reconciled = Reconciliation::pluck('bank_transaction_id');
        $transactions = BankTransaction::where('bank_account_id', $bank->id)->orderByDesc('date')->paginate(20)->withQueryString();
        $lines = JournalLine::with('entry')->where('account_id', $bank->account_id)->whereHas('entry', fn ($q) => $q->where('status', 'posted'))->whereNotIn('id', Reconciliation::pluck('journal_line_id'))->latest('id')->limit(200)->get();

        $sessions = ReconciliationSession::where('bank_account_id', $bank->id)->latest('id')->get();
        $accounts = Account::where('type', 'asset')->where('is_active', true)->whereNotIn('id', BankAccount::select('account_id'))->get(['id', 'name', 'code']);

        return Inertia::render('Banking/Index', compact('banks', 'bank', 'transactions', 'reconciled', 'lines', 'sessions', 'accounts'));
    }

    public function import(StatementRequest $request, ReconciliationService $service)
    {
        $bank = BankAccount::findOrFail($request->integer('bank_account_id'));
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if ($headers !== ['date', 'description', 'amount', 'reference']) {
                throw ValidationException::withMessages(['file' => 'CSV headers must be date,description,amount,reference.']);
            }
            $rows = [];
            while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($cells === [null]) {
                    continue;
                }
                if (count($cells) !== 4 || count($rows) >= 10000) {
                    throw ValidationException::withMessages(['file' => 'Invalid statement row or more than 10,000 lines.']);
                }
                $row = array_combine($headers, $cells);
                Validator::make($row, ['date' => 'required|date_format:Y-m-d', 'description' => 'required|string|max:2000', 'amount' => 'required|decimal:0,2', 'reference' => 'nullable|string|max:255'])->validate();
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }
        $count = $service->import($bank, $rows);

        return back()->with('success', "Imported {$count} new transactions. Duplicate lines were skipped.");
    }

    public function match(Request $request, int $id, ReconciliationService $service)
    {
        Gate::authorize('permission', 'banking.reconcile');
        $data = $request->validate(['journal_line_id' => 'required|integer']);
        $service->match(BankTransaction::findOrFail($id), $data['journal_line_id']);

        return back()->with('success', 'Transaction reconciled.');
    }
}
