<?php

namespace App\Services\Invoicing;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Services\Accounting\JournalService;
use App\Services\Audit\Audit;
use App\Services\Subscriptions\SubscriptionService;
use App\Services\Tenancy\CompanyContext;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DocumentService
{
    public function __construct(private JournalService $journals) {}

    public function create(string $kind, array $data): Document
    {
        return DB::transaction(function () use ($kind, $data) {
            $sales = $kind === 'invoice';
            // Serialize plan-limit checks with other company-level financial operations.
            Company::whereKey(app(CompanyContext::class)->id())->lockForUpdate()->firstOrFail();
            if ($sales) {
                app(SubscriptionService::class)->assertLimit('invoices_per_month', Invoice::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count());
            }
            $contactKey = $sales ? 'customer_id' : 'supplier_id';
            ($sales ? Customer::class : Supplier::class)::findOrFail($data[$contactKey]);
            $sequence = DocumentSequence::where('type', $kind)->lockForUpdate()->firstOrFail();
            $number = ($sales ? 'INV-' : 'BILL-').str_pad((string) $sequence->next_number, 6, '0', STR_PAD_LEFT);
            $sequence->increment('next_number');
            $items = $this->calculate($data['items'], $sales);
            $class = $sales ? Invoice::class : Bill::class;
            $document = $class::create([
                $contactKey => $data[$contactKey], $sales ? 'invoice_number' : 'bill_number' => $number,
                $sales ? 'issue_date' : 'bill_date' => $data['date'], 'due_date' => $data['due_date'],
                'currency' => app(CompanyContext::class)->company()->currency, 'notes' => $data['notes'] ?? null,
                'subtotal' => Decimal::sum(array_column($items, 'subtotal')), 'tax_total' => Decimal::sum(array_column($items, 'tax_amount')),
                'total' => Decimal::sum(array_column($items, 'total')),
            ]);
            $document->items()->createMany($items);
            Audit::record($kind.'.created', $document, [], ['number' => $number, 'total' => $document->total]);

            return $document;
        });
    }

    private function calculate(array $items, bool $sales): array
    {
        if (! $items) {
            $this->fail('At least one item is required.');
        }
        $result = [];
        foreach ($items as $item) {
            $account = Account::findOrFail($item['account_id']);
            if (! $account->is_active || ! in_array($account->type, $sales ? ['revenue'] : ['expense', 'asset']) || (! $sales && in_array($account->system_key, ['bank', 'receivables', 'input_tax']))) {
                $this->fail('Select an active account with an appropriate type.');
            }
            $quantity = (string) $item['quantity'];
            $price = (string) $item['unit_price'];
            $discount = Decimal::round((string) ($item['discount'] ?? '0'));
            if (bccomp($quantity, '0', 4) <= 0 || bccomp($price, '0', 4) < 0 || bccomp($discount, '0', 2) < 0) {
                $this->fail('Quantity, price or discount is invalid.');
            }
            $subtotal = Decimal::round(bcsub(bcmul($quantity, $price, 8), $discount, 8));
            if (bccomp($subtotal, '0', 2) < 0) {
                $this->fail('Discount cannot exceed the line amount.');
            }
            $tax = empty($item['tax_rate_id']) ? null : TaxRate::findOrFail($item['tax_rate_id']);
            if ($tax && ! $tax->is_active) {
                $this->fail('Inactive tax rates cannot be used.');
            }
            $rate = (string) ($tax?->rate ?? '0');
            $taxAmount = Decimal::round(bcdiv(bcmul($subtotal, $rate, 8), '100', 8));
            $result[] = ['description' => $item['description'], 'account_id' => $account->id, 'tax_rate_id' => $tax?->id, 'quantity' => $quantity, 'unit_price' => $price, 'discount' => $discount, 'tax_rate' => $rate, 'subtotal' => $subtotal, 'tax_amount' => $taxAmount, 'total' => bcadd($subtotal, $taxAmount, 2)];
        }

        return $result;
    }

    public function approve(Document $document, int $userId): Document
    {
        return DB::transaction(function () use ($document, $userId) {
            $document = $document->newQuery()->lockForUpdate()->findOrFail($document->id);
            if ($document->status !== 'draft' || bccomp($document->total, '0', 2) <= 0) {
                $this->fail('Only a positive draft can be approved.');
            }
            $sales = $document instanceof Invoice;
            $control = $this->account($sales ? 'receivables' : 'payables');
            $lines = [['account_id' => $control->id, $sales ? 'debit' : 'credit' => $document->total]];
            foreach ($document->items as $item) {
                if (bccomp((string) $item->subtotal, '0', 2) > 0) {
                    $lines[] = ['account_id' => $item->account_id, $sales ? 'credit' : 'debit' => (string) $item->subtotal];
                }
            }
            if (bccomp($document->tax_total, '0', 2) > 0) {
                $lines[] = ['account_id' => $this->account($sales ? 'output_tax' : 'input_tax')->id, $sales ? 'credit' : 'debit' => $document->tax_total];
            }
            $entry = $this->journals->post($sales ? $document->issue_date : $document->bill_date, $sales ? $document->invoice_number : $document->bill_number, $lines, $userId, $sales ? 'invoice' : 'bill', $document->id);
            $document->update(['status' => 'approved', 'journal_entry_id' => $entry->id]);
            Audit::record($sales ? 'invoice.approved' : 'bill.posted', $document);

            return $document;
        });
    }

    public function pay(Document $document, array $data, int $userId): Payment|BillPayment
    {
        return DB::transaction(function () use ($document, $data, $userId) {
            $document = $document->newQuery()->lockForUpdate()->findOrFail($document->id);
            $sales = $document instanceof Invoice;
            $class = $sales ? Payment::class : BillPayment::class;
            if ($data['paid_at'] < ($sales ? $document->issue_date : $document->bill_date)) {
                $this->fail('Payment date cannot precede the document date.');
            }
            $amount = Decimal::round((string) $data['amount']);
            $hash = hash('sha256', json_encode([$document->id, $amount, (int) $data['bank_account_id'], $data['paid_at'], $data['reference'] ?? null]));
            $existing = $class::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ($existing->request_hash !== $hash) {
                    $this->fail('This payment key was already used for a different request.');
                }

                return $existing;
            }
            if (! in_array($document->status, ['approved', 'sent', 'partially_paid']) || bccomp($amount, '0', 2) <= 0 || bccomp($amount, $document->outstanding, 2) > 0) {
                $this->fail('Payment must be positive and cannot exceed the outstanding amount.');
            }
            $bank = BankAccount::findOrFail($data['bank_account_id']);
            if ($bank->currency !== $document->currency) {
                $this->fail('Foreign-currency settlements are not enabled.');
            }
            $entry = $this->journals->post($data['paid_at'], 'PAY-'.($sales ? $document->invoice_number : $document->bill_number), [
                ['account_id' => $bank->account_id, $sales ? 'debit' : 'credit' => $amount],
                ['account_id' => $this->account($sales ? 'receivables' : 'payables')->id, $sales ? 'credit' : 'debit' => $amount],
            ], $userId, $sales ? 'invoice_payment' : 'bill_payment');
            $payment = $class::create([$sales ? 'invoice_id' : 'bill_id' => $document->id, 'amount' => $amount, 'bank_account_id' => $bank->id, 'paid_at' => $data['paid_at'], 'reference' => $data['reference'] ?? null, 'journal_entry_id' => $entry->id, 'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash]);
            $paid = bcadd($document->paid_total, $amount, 2);
            $document->update(['paid_total' => $paid, 'status' => bccomp(bcadd($paid, $document->credited_total, 2), $document->total, 2) === 0 ? 'paid' : 'partially_paid']);
            Audit::record($sales ? 'payment.created' : 'bill.payment_created', $payment, [], ['amount' => $amount]);

            return $payment;
        });
    }

    public function updateDraft(Document $document, array $data): Document
    {
        return DB::transaction(function () use ($document, $data) {
            $document = $document->newQuery()->lockForUpdate()->findOrFail($document->id);
            if ($document->status !== 'draft') {
                $this->fail('Only drafts can be edited.');
            }
            $sales = $document instanceof Invoice;
            $key = $sales ? 'customer_id' : 'supplier_id';
            ($sales ? Customer::class : Supplier::class)::findOrFail($data[$key]);
            $items = $this->calculate($data['items'], $sales);
            foreach ($document->items as $item) {
                $item->delete();
            }
            $document->update([$key => $data[$key], $sales ? 'issue_date' : 'bill_date' => $data['date'], 'due_date' => $data['due_date'], 'notes' => $data['notes'] ?? null, 'subtotal' => Decimal::sum(array_column($items, 'subtotal')), 'tax_total' => Decimal::sum(array_column($items, 'tax_amount')), 'total' => Decimal::sum(array_column($items, 'total'))]);
            $document->items()->createMany($items);
            Audit::record('document.draft_updated', $document);

            return $document;
        });
    }

    public function void(Document $document, string $date, int $userId): void
    {
        DB::transaction(function () use ($document, $date, $userId) {
            $document = $document->newQuery()->lockForUpdate()->findOrFail($document->id);
            if (! in_array($document->status, ['approved', 'sent']) || bccomp($document->paid_total, '0', 2) !== 0 || bccomp($document->credited_total, '0', 2) !== 0) {
                $this->fail('Only unpaid, uncredited posted documents can be voided.');
            }
            $entry = $document->journal;
            if ($date < $entry->date->toDateString()) {
                $this->fail('Void date cannot precede posting.');
            }
            $lines = $entry->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit])->all();
            $reversal = $this->journals->post($date, 'VOID-'.$entry->reference, $lines, $userId, $document instanceof Invoice ? 'invoice_void' : 'bill_void', $document->id, $entry->id);
            $document->update(['status' => 'void']);
            Audit::record('document.voided', $document, [], ['reversal_id' => $reversal->id]);
        });
    }

    public function account(string $key): Account
    {
        return Account::where('system_key', $key)->firstOrFail();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
