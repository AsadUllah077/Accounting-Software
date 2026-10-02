<?php

namespace App\Services\Reporting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\CreditNote;
use App\Models\CustomerRefund;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Support\Decimal;
use Carbon\Carbon;

final class OperationalReports
{
    public function aging(string $kind, string $to): array
    {
        $sales = $kind === 'receivables';
        $class = $sales ? Invoice::class : Bill::class;
        $contact = $sales ? 'customer' : 'supplier';
        $rows = [];
        $relations = [$contact, 'payments.journal', 'journal.reversal'];
        if ($sales) {
            $relations[] = 'creditNotes.refunds';
        }
        foreach ($class::with($relations)->whereHas('journal', fn ($q) => $q->where('status', 'posted')->where('date', '<=', $to))->orderBy('due_date')->lazy(500) as $document) {
            if ($document->journal->reversal && $document->journal->reversal->date->toDateString() <= $to) {
                continue;
            }
            $payments = Decimal::sum($document->payments->filter(fn ($p) => $p->journal->date->toDateString() <= $to)->pluck('amount'));
            $credits = $sales ? $document->creditNotes->filter(fn ($credit) => $credit->status === 'posted' && $credit->date <= $to) : collect();
            $refunded = $sales ? Decimal::sum($credits->flatMap(fn ($credit) => $credit->refunds)->filter(fn ($refund) => $refund->date <= $to)->pluck('amount')) : '0.00';
            $outstanding = bcadd(bcsub(bcsub($document->total, $payments, 2), Decimal::sum($credits->pluck('amount')), 2), $refunded, 2);
            if (bccomp($outstanding, '0', 2) === 0) {
                continue;
            }
            $late = (int) Carbon::parse($document->due_date)->diffInDays(Carbon::parse($to), false);
            $rows[] = ['id' => $document->id, 'number' => $sales ? $document->invoice_number : $document->bill_number, 'contact' => $document->$contact->name, 'due_date' => $document->due_date, 'outstanding' => $outstanding, 'bucket' => $late <= 0 ? 'Current' : ($late <= 30 ? '1–30 days' : ($late <= 60 ? '31–60 days' : ($late <= 90 ? '61–90 days' : '90+ days')))];
        }

        return $rows;
    }

    public function taxSummary(string $from, string $to): array
    {
        $accounts = Account::whereIn('system_key', ['input_tax', 'output_tax'])->get();

        return $accounts->map(function ($account) use ($from, $to) {
            $lines = JournalLine::where('account_id', $account->id)->whereHas('entry', fn ($q) => $q->where('status', 'posted')->whereBetween('date', [$from, $to]))->get(['debit', 'credit']);

            return ['id' => $account->id, 'name' => $account->name, 'debits' => Decimal::sum($lines->pluck('debit')), 'credits' => Decimal::sum($lines->pluck('credit'))];
        })->all();
    }

    public function expenseSummary(string $from, string $to): array
    {
        return array_values(array_filter(app(FinancialReports::class)->trialBalance($from, $to), fn ($r) => $r['type'] === 'expense'));
    }

    public function cashFlow(string $from, string $to): array
    {
        $bankAccounts = BankAccount::pluck('account_id')->all();
        $rows = [];
        $entries = JournalEntry::with('lines.account')->where('status', 'posted')->whereBetween('date', [$from, $to])->whereHas('lines', fn ($q) => $q->whereIn('account_id', $bankAccounts))->get();
        $billPayments = BillPayment::with('bill.items.account')->whereIn('journal_entry_id', $entries->where('source_type', 'bill_payment')->pluck('id'))->get()->keyBy('journal_entry_id');
        foreach ($entries as $entry) {
            $bankNet = Decimal::sum($entry->lines->filter(fn ($l) => in_array($l->account_id, $bankAccounts))->map(fn ($l) => bcsub($l->debit, $l->credit, 2)));
            if (bccomp($bankNet, '0', 2) === 0) {
                continue;
            } // Exclude transfers between bank accounts.
            if ($entry->source_type === 'bill_payment') {
                $payment = $billPayments->get($entry->id) ?? throw new \LogicException('Bill payment journal requires its allocation.');
                $parts = [];
                foreach ($payment->bill->items as $item) {
                    if (bccomp($item->subtotal, '0', 2) > 0) {
                        $parts[] = ['name' => $item->account->name, 'classification' => $item->account->cash_flow_class, 'weight' => $item->subtotal];
                    }
                }
                if (bccomp($payment->bill->tax_total, '0', 2) > 0) {
                    $parts[] = ['name' => 'Recoverable purchase tax', 'classification' => 'operating', 'weight' => $payment->bill->tax_total];
                }
                $allocated = '0.00';
                foreach ($parts as $index => $part) {
                    // Allocate partial settlements proportionally; the final part absorbs rounding.
                    $amount = $index === count($parts) - 1 ? bcsub($bankNet, $allocated, 2) : Decimal::round(bcdiv(bcmul($bankNet, $part['weight'], 8), $payment->bill->total, 8));
                    $allocated = bcadd($allocated, $amount, 2);
                    $rows[] = ['id' => $entry->id.'-'.$index, 'date' => $entry->date->toDateString(), 'reference' => $entry->reference, 'name' => $part['name'], 'classification' => $part['classification'], 'amount' => $amount];
                }

                continue;
            }
            foreach ($entry->lines->reject(fn ($l) => in_array($l->account_id, $bankAccounts)) as $line) {
                $rows[] = ['id' => $line->id, 'date' => $entry->date->toDateString(), 'reference' => $entry->reference, 'name' => $line->account->name, 'classification' => $line->account->cash_flow_class, 'amount' => bcsub($line->credit, $line->debit, 2)];
            }
        }
        $opening = Decimal::sum(JournalLine::whereIn('account_id', $bankAccounts)->whereHas('entry', fn ($q) => $q->where('status', 'posted')->where('date', '<', $from))->get()->map(fn ($l) => bcsub($l->debit, $l->credit, 2)));
        $net = Decimal::sum(array_column($rows, 'amount'));

        return ['rows' => $rows, 'opening_balance' => $opening, 'net_movement' => $net, 'closing_balance' => bcadd($opening, $net, 2)];
    }

    public function statement(string $kind, int $contactId, string $from, string $to): array
    {
        $sales = $kind === 'customer';
        $documents = ($sales ? Invoice::class : Bill::class)::where($sales ? 'customer_id' : 'supplier_id', $contactId)->get(['id', 'journal_entry_id']);
        $ids = $documents->pluck('id');
        $journalIds = $documents->pluck('journal_entry_id');
        $journalIds = $journalIds->merge(($sales ? Payment::class : BillPayment::class)::whereIn($sales ? 'invoice_id' : 'bill_id', $ids)->pluck('journal_entry_id'));
        if ($sales) {
            $credits = CreditNote::whereIn('invoice_id', $ids)->get(['id', 'journal_entry_id']);
            $journalIds = $journalIds->merge($credits->pluck('journal_entry_id'))->merge(CustomerRefund::whereIn('credit_note_id', $credits->pluck('id'))->pluck('journal_entry_id'));
        }
        $control = Account::where('system_key', $sales ? 'receivables' : 'payables')->firstOrFail();
        $journalIds = $journalIds->merge(JournalEntry::whereIn('reversal_of_id', $journalIds)->pluck('id'));
        $query = JournalLine::with('entry')->where('account_id', $control->id)->whereIn('journal_entry_id', $journalIds)->whereHas('entry', fn ($q) => $q->where('status', 'posted')->where('date', '<=', $to));
        $rows = $query->get()->sortBy(fn ($l) => $l->entry->date->toDateString().str_pad((string) $l->id, 20, '0', STR_PAD_LEFT));
        $balance = '0.00';
        $opening = '0.00';
        $result = [];
        foreach ($rows as $line) {
            $balance = bcadd($balance, $sales ? bcsub($line->debit, $line->credit, 2) : bcsub($line->credit, $line->debit, 2), 2);
            if ($line->entry->date->toDateString() < $from) {
                $opening = $balance;

                continue;
            }
            $result[] = ['id' => $line->id, 'date' => $line->entry->date->toDateString(), 'reference' => $line->entry->reference, 'debit' => $line->debit, 'credit' => $line->credit, 'balance' => $balance];
        }

        return ['rows' => $result, 'opening_balance' => $opening, 'closing_balance' => $balance];
    }
}
