<?php

namespace App\Services\Subscriptions;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\Audit\Audit;
use App\Services\Tenancy\CompanyContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubscriptionService
{
    public function choose(int $planId): Subscription
    {
        return DB::transaction(function () use ($planId) {
            $company = Company::lockForUpdate()->findOrFail(app(CompanyContext::class)->id());
            $plan = SubscriptionPlan::where('is_active', true)->findOrFail($planId);
            $current = Subscription::whereIn('status', ['active', 'trialing', 'past_due'])->lockForUpdate()->first();
            if ($current && $current->plan_id === $plan->id) {
                return $current;
            }
            if ($current && $current->status === 'past_due') {
                throw ValidationException::withMessages(['subscription' => 'Settle outstanding subscription invoices before changing plans.']);
            }
            $first = ! Subscription::exists();
            if ($current) {
                $current->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                Audit::record('subscription.plan_changed', $current, [], ['next_plan_id' => $plan->id]);
            }
            $end = $first ? now()->addDays(14) : ($plan->interval === 'yearly' ? now()->addYearNoOverflow() : now()->addMonthNoOverflow());
            $subscription = Subscription::create(['plan_id' => $plan->id, 'status' => $first ? 'trialing' : 'active', 'start_date' => now()->toDateString(), 'end_date' => $end->toDateString(), 'trial_ends_at' => $first ? $end : null, 'amount' => $plan->price, 'currency' => $plan->currency]);
            if (! $first) {
                $this->invoice($subscription, now()->toDateString(), $end->toDateString());
            }
            Audit::record('subscription.created', $subscription, [], ['plan_id' => $plan->id]);

            return $subscription;
        });
    }

    private function invoice(Subscription $subscription, string $start, string $end): SubscriptionInvoice
    {
        return SubscriptionInvoice::firstOrCreate(['subscription_id' => $subscription->id, 'period_start' => $start], ['number' => 'SUB-'.$subscription->company_id.'-'.$subscription->id.'-'.$start, 'amount' => $subscription->amount, 'currency' => $subscription->currency, 'due_date' => $start, 'period_end' => $end]);
    }

    public function renewDue(): int
    {
        return DB::transaction(function () {
            Company::lockForUpdate()->findOrFail(app(CompanyContext::class)->id());
            $current = Subscription::with('plan')->whereIn('status', ['active', 'trialing', 'past_due'])->lockForUpdate()->first();
            if (! $current) {
                return 0;
            }
            if (SubscriptionInvoice::where('subscription_id', $current->id)->where('status', 'open')->where('due_date', '<', now()->toDateString())->exists()) {
                $current->update(['status' => 'past_due']);

                return 0;
            }
            if ($current->end_date > now()->toDateString()) {
                return 0;
            }
            if (SubscriptionInvoice::where('subscription_id', $current->id)->where('status', 'open')->exists()) {
                $current->update(['status' => 'past_due']);

                return 0;
            }
            $start = Carbon::parse($current->end_date);
            $end = $current->plan->interval === 'yearly' ? $start->copy()->addYearNoOverflow() : $start->copy()->addMonthNoOverflow();
            $this->invoice($current, $start->toDateString(), $end->toDateString());
            $current->update(['status' => 'active', 'end_date' => $end->toDateString()]);
            Audit::record('subscription.renewed', $current);

            return 1;
        });
    }

    public function cancel(): void
    {
        DB::transaction(function () {
            Company::lockForUpdate()->findOrFail(app(CompanyContext::class)->id());
            $current = Subscription::whereIn('status', ['active', 'trialing', 'past_due'])->lockForUpdate()->firstOrFail();
            $current->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            Audit::record('subscription.cancelled', $current);
        });
    }

    public function recordPayment(SubscriptionInvoice $invoice, string $reference): SubscriptionPayment
    {
        return DB::transaction(function () use ($invoice, $reference) {
            Company::lockForUpdate()->findOrFail(app(CompanyContext::class)->id());
            $invoice = SubscriptionInvoice::lockForUpdate()->findOrFail($invoice->id);
            $existing = SubscriptionPayment::where('provider_reference', $reference)->first();
            if ($existing) {
                if ($existing->subscription_invoice_id !== $invoice->id) {
                    throw ValidationException::withMessages(['reference' => 'Reference was used for another invoice.']);
                }

                return $existing;
            }
            if ($invoice->status !== 'open') {
                throw ValidationException::withMessages(['invoice' => 'Invoice is already settled.']);
            }
            $payment = SubscriptionPayment::create(['subscription_invoice_id' => $invoice->id, 'amount' => $invoice->amount, 'provider_reference' => $reference, 'paid_at' => now()]);
            $invoice->update(['status' => 'paid']);
            if ($invoice->subscription->status === 'past_due') {
                $invoice->subscription->update(['status' => 'active']);
            }
            Audit::record('subscription.payment_recorded', $payment);

            return $payment;
        });
    }

    public function assertLimit(string $key, int $current): void
    {
        $subscription = Subscription::with('plan')->whereIn('status', ['active', 'trialing', 'past_due'])->first();
        if (! $subscription) {
            if (Subscription::exists()) {
                throw ValidationException::withMessages(['subscription' => 'Choose an active subscription before creating new records.']);
            }

            return;
        }
        $overdue = SubscriptionInvoice::where('subscription_id', $subscription->id)->where('status', 'open')->where('due_date', '<', now()->toDateString())->exists();
        if ($overdue || $subscription->status === 'past_due' || ($subscription->status === 'trialing' && $subscription->end_date < now()->toDateString())) {
            throw ValidationException::withMessages(['subscription' => 'The subscription is past due.']);
        }
        $features = is_array($subscription->plan->features) ? $subscription->plan->features : json_decode($subscription->plan->features, true);
        $limit = $features[$key] ?? null;
        if ($limit !== null && $current >= (int) $limit) {
            throw ValidationException::withMessages(['subscription' => 'Your plan limit has been reached.']);
        }
    }
}
