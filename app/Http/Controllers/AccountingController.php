<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Http\Requests\JournalRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\JournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AccountingController extends Controller
{
    public function accounts()
    {
        Gate::authorize('permission', 'accounting.view');

        return Inertia::render('Accounting/Accounts', ['accounts' => Account::orderBy('code')->get()]);
    }

    public function saveAccount(AccountRequest $request, AccountService $service, ?int $id = null)
    {
        $service->save($request->validated(), $id ? Account::findOrFail($id) : null);

        return back()->with('success', 'Account saved.');
    }

    public function journals()
    {
        Gate::authorize('permission', 'accounting.view');

        return Inertia::render('Accounting/Journals', ['journals' => JournalEntry::with('lines.account')->latest('id')->paginate(20), 'accounts' => Account::where('is_active', true)->orderBy('code')->get(['id', 'name', 'code'])]);
    }

    public function store(JournalRequest $request, JournalService $service)
    {
        $data = $request->validated();
        $service->post($data['date'], $data['reference'], $data['lines'], $request->user()->id);

        return back()->with('success', 'Journal posted.');
    }

    public function reverse(Request $request, int $id, JournalService $service)
    {
        Gate::authorize('permission', 'accounting.reverse');
        $data = $request->validate(['date' => 'required|date_format:Y-m-d']);
        $service->reverse(JournalEntry::findOrFail($id), $data['date'], $request->user()->id);

        return back()->with('success','Reversal posted.');
    }
}
