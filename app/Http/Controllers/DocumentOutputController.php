<?php

namespace App\Http\Controllers;

use App\Jobs\SendInvoice;
use App\Models\Bill;
use App\Models\Invoice;
use App\Services\Tenancy\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DocumentOutputController extends Controller
{
    public function pdf(string $kind, int $id)
    {
        $document = ($kind === 'invoices' ? Invoice::class : Bill::class)::with(['items', $kind === 'invoices' ? 'customer' : 'supplier'])->findOrFail($id);
        Gate::authorize('view', $document);

        return Pdf::loadView('pdf.document', ['document' => $document, 'company' => app(CompanyContext::class)->company(), 'kind' => $kind])->setOption('isRemoteEnabled', false)->download(($document->invoice_number ?? $document->bill_number).'.pdf');
    }

    public function send(int $id)
    {
        Gate::authorize('permission', 'invoice.send');
        $invoice = Invoice::with('customer')->findOrFail($id);
        if ($invoice->status !== 'approved' || ! $invoice->customer->email) {
            throw ValidationException::withMessages(['email' => 'Only approved invoices with a customer email address can be sent.']);
        }
        SendInvoice::dispatch(app(CompanyContext::class)->id(), $invoice->id, auth()->id())->afterCommit();

        return back()->with('success', 'Invoice email queued for delivery.');
    }
}
