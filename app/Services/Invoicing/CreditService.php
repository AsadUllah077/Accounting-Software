<?php

namespace App\Services\Invoicing;

use App\Models\BankAccount;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\CustomerRefund;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Accounting\JournalService;
use App\Services\Audit\Audit;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreditService
{
    public function issue(Invoice $invoice, array $data, int $userId): CreditNote
    {
        return DB::transaction(function () use ($invoice, $data, $userId) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if ($data['date'] < $invoice->issue_date) {
                $this->fail('Credit date cannot precede the invoice date.');
            }
            if (! in_array($invoice->status, ['approved', 'sent', 'partially_paid', 'paid'])) {
                $this->fail('Only posted invoices can receive credit notes.');
            }
            $sequence = DocumentSequence::where('type', 'credit')->lockForUpdate()->firstOrFail();
            $number = 'CN-'.str_pad((string) $sequence->next_number, 6, '0', STR_PAD_LEFT);
            $sequence->increment('next_number');
            $items = [];
            $journalLines = [];
            $total = '0.00';
            $taxTotal = '0.00';
            $seen = [];
            foreach ($data['items'] as $requested) {
                $item = InvoiceItem::where('invoice_id', $invoice->id)->findOrFail($requested['invoice_item_id']);
                if (in_array($item->id, $seen)) {
                    $this->fail('Each invoice line can appear only once.');
                }$seen[] = $item->id;
                $amount = Decimal::round((string) $requested['amount']);
                $prior = CreditNoteItem::where('invoice_item_id', $item->id)->get();
                $remaining = bcsub((string) $item->subtotal, Decimal::sum($prior->pluck('amount')), 2);
                if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $remaining, 2) > 0) {
                    $this->fail('Credit amounts cannot exceed the uncredited line amount.');
                }
                $tax = bccomp($amount, $remaining, 2) === 0 ? bcsub((string) $item->tax_amount, Decimal::sum($prior->pluck('tax_amount')), 2) : Decimal::round(bcdiv(bcmul($amount, (string) $item->tax_rate, 8), '100', 8));
                $items[] = ['invoice_item_id' => $item->id, 'account_id' => $item->account_id, 'description' => $item->description, 'amount' => $amount, 'tax_amount' => $tax];
                $journalLines[] = ['account_id' => $item->account_id, 'debit' => $amount];
                $taxTotal = bcadd($taxTotal, $tax, 2);
                $total = bcadd($total, bcadd($amount, $tax, 2), 2);
            }
            if (! $items) {
                $this->fail('Credit notes require at least one line.');
            }
            $note = CreditNote::create(['invoice_id' => $invoice->id, 'credit_number' => $number, 'date' => $data['date'], 'reason' => $data['reason'], 'amount' => $total]);
            $note->items()->createMany($items);
            $documents = app(DocumentService::class);
            if (bccomp($taxTotal, '0', 2) > 0) {
                $journalLines[] = ['account_id' => $documents->account('output_tax')->id, 'debit' => $taxTotal];
            }
            $journalLines[] = ['account_id' => $documents->account('receivables')->id, 'credit' => $total];
            $entry = app(JournalService::class)->post($data['date'], $number, $journalLines, $userId, 'credit_note', $note->id);
            $note->update(['status' => 'posted', 'journal_entry_id' => $entry->id]);
            $credited = bcadd($invoice->credited_total, $total, 2);
            $invoice->update(['credited_total' => $credited, 'status' => bccomp(bcadd($invoice->paid_total, $credited, 2), $invoice->total, 2) >= 0 ? 'paid' : ($invoice->paid_total !== '0.00' ? 'partially_paid' : 'approved')]);
            Audit::record('credit_note.posted', $note, [], ['amount' => $total]);

            return $note;
        });
    }

    public function refund(CreditNote $note, array $data, int $userId): CustomerRefund
    {
        return DB::transaction(function () use ($note, $data, $userId) {
            $note = CreditNote::findOrFail($note->id);
            if ($data['date'] < $note->date) {
                $this->fail('Refund date cannot precede the credit note date.');
            }
            $invoice = Invoice::lockForUpdate()->findOrFail($note->invoice_id);
            $amount = Decimal::round((string) $data['amount']);
            $hash = hash('sha256', json_encode([$note->id, $amount, (int) $data['bank_account_id'], $data['date']]));
            $existing = CustomerRefund::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ($existing->request_hash !== $hash) {
                    $this->fail('Refund key already used with a different request.');
                }

                return $existing;
            }
            $remaining = bcsub((string) $note->amount, Decimal::sum($note->refunds()->pluck('amount')), 2);
            if ($note->status !== 'posted' || bccomp($amount, '0', 2) <= 0 || bccomp($amount, $remaining, 2) > 0 || bccomp($amount, bcsub('0', $invoice->outstanding, 2), 2) > 0) {
                $this->fail('Refund cannot exceed the customer credit balance or the unrefunded credit note.');
            }
            $bank = BankAccount::findOrFail($data['bank_account_id']);
            if ($bank->currency !== $invoice->currency) {
                $this->fail('Foreign-currency refunds are not enabled.');
            }
            $entry = app(JournalService::class)->post($data['date'], 'REFUND-'.$note->credit_number, [['account_id' => app(DocumentService::class)->account('receivables')->id, 'debit' => $amount], ['account_id' => $bank->account_id, 'credit' => $amount]], $userId, 'customer_refund');
            $refund = CustomerRefund::create(['credit_note_id' => $note->id, 'bank_account_id' => $bank->id, 'journal_entry_id' => $entry->id, 'amount' => $amount, 'date' => $data['date'], 'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash]);
            $invoice->update(['paid_total' => bcsub($invoice->paid_total, $amount, 2)]);
            Audit::record('customer.refunded', $refund, [], ['amount' => $amount]);

            return $refund;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['credit' => $message]);
    }
}
