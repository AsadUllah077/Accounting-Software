<?php

namespace App\Http\Controllers;

use App\Models\ReconciliationSession;
use App\Services\Banking\SessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReconciliationSessionController extends Controller
{
    public function store(Request $request, SessionService $service)
    {
        Gate::authorize('permission', 'banking.reconcile');
        $data = $request->validate(['bank_account_id' => 'required|integer', 'statement_date' => 'required|date_format:Y-m-d', 'closing_balance' => 'required|decimal:0,2']);
        $service->open($data);

        return back()->with('success', 'Reconciliation session opened.');
    }

    public function close(int $id, SessionService $service)
    {
        Gate::authorize('permission', 'banking.reconcile');
        $service->close(ReconciliationSession::findOrFail($id));

        return back()->with('success', 'Statement reconciled and session closed.');
    }
}
