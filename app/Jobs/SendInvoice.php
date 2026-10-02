<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Audit\Audit;
use App\Services\Tenancy\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // Mail providers are not necessarily idempotent; retry manually after verifying delivery.

    public function __construct(public int $companyId, public int $invoiceId, public int $userId) {}

    public function handle(): void
    {
        $company = Company::findOrFail($this->companyId);
        app(CompanyContext::class)->run($company, function () use ($company) {
            $user = User::findOrFail($this->userId);
            if (! $user->hasCompanyPermission('invoice.send')) {
                return;
            }
            Cache::lock('invoice-email:'.$company->id.':'.$this->invoiceId, 120)->block(5, function () use ($company) {
                $invoice = Invoice::with(['customer', 'items'])->findOrFail($this->invoiceId);
                if ($invoice->status !== 'approved' || ! $invoice->customer->email) {
                    return;
                }
                $pdf = Pdf::loadView('pdf.document', ['document' => $invoice, 'company' => $company, 'kind' => 'invoices'])->setOption('isRemoteEnabled', false)->output();
                Mail::send('emails.invoice', ['invoice' => $invoice, 'company' => $company], function ($message) use ($invoice, $pdf, $company) {
                    $message->to($invoice->customer->email)->subject($company->name.' · '.$invoice->invoice_number)->attachData($pdf, $invoice->invoice_number.'.pdf', ['mime' => 'application/pdf']);
                });
                $invoice->update(['status' => 'sent']);
                Audit::record('invoice.sent', $invoice, [], ['recipient' => $invoice->customer->email]);
            });
        });
    }
}
