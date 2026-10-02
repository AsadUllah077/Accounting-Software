<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import MoneyDisplay from "@/Components/MoneyDisplay.vue";
import type { Paginated } from "@/types/accounting";
defineProps<{
    plans: any[];
    subscriptions: any[];
    invoices: Paginated<any>;
    platformAdmin: boolean;
}>();
function cancel() {
    if (
        window.confirm(
            "Cancel this subscription immediately? Billing history will be preserved.",
        )
    )
        router.post("/subscription/cancel");
}
function choose(id: number) {
    if (
        window.confirm(
            "Change plan? The first subscription includes a 14-day trial. Later changes create a full billing-period invoice without proration.",
        )
    )
        router.post("/subscription", { plan_id: id });
}
function payment(id: number) {
    const reference = window.prompt("Verified payment provider reference");
    if (reference)
        router.post(`/subscription/invoices/${id}/payments`, { reference });
}
</script>
<template>
    <Head title="Subscription" /><AppLayout
        ><PageHeader
            title="Subscription"
            description="Plans and billing for your Ledger workspace."
            ><button
                v-if="
                    subscriptions.some((s) =>
                        ['active', 'trialing', 'past_due'].includes(s.status),
                    )
                "
                @click="cancel"
                class="btn-secondary"
            >
                Cancel subscription
            </button></PageHeader
        >
        <div class="mb-7 grid gap-5 md:grid-cols-2">
            <section v-for="plan in plans" :key="plan.id" class="panel">
                <h2 class="font-semibold">{{ plan.name }}</h2>
                <p class="mt-3 text-3xl font-semibold">
                    <MoneyDisplay
                        :amount="plan.price"
                        :currency="plan.currency"
                    /><span class="text-sm font-normal text-slate-400">
                        / {{ plan.interval }}</span
                    >
                </p>
                <p class="mt-4 text-sm text-slate-500">
                    {{ plan.features?.users }} users ·
                    {{ plan.features?.invoices_per_month }} invoices per month
                </p>
                <button @click="choose(plan.id)" class="btn mt-5">
                    Choose plan
                </button>
            </section>
        </div>
        <h2 class="mb-4 font-semibold">Subscription history</h2>
        <div
            v-for="record in subscriptions"
            :key="record.id"
            class="mb-3 flex items-center justify-between rounded-lg border border-slate-200 bg-white p-4"
        >
            <span
                >{{ record.plan.name }} · {{ record.start_date }} —
                {{ record.end_date }}</span
            ><StatusBadge :status="record.status" />
        </div>
        <h2 class="mb-4 mt-7 font-semibold">Billing invoices</h2>
        <DataTable
            :rows="invoices.data"
            :links="invoices.links"
            :columns="[
                { key: 'number', label: 'Invoice' },
                { key: 'due_date', label: 'Due date' },
                { key: 'status', label: 'Status' },
                { key: 'amount', label: 'Amount', right: true },
                { key: 'actions', label: 'Actions' },
            ]"
            ><template #amount="{ row }"
                ><MoneyDisplay
                    :amount="row.amount"
                    :currency="row.currency" /></template
            ><template #status="{ row }"
                ><StatusBadge :status="row.status" /></template
            ><template #actions="{ row }"
                ><button
                    v-if="platformAdmin && row.status === 'open'"
                    @click="payment(row.id)"
                    class="text-xs text-teal-700"
                >
                    Record verified payment
                </button></template
            ></DataTable
        >
        <p
            v-for="(error, key) in $page.props.errors"
            :key="key"
            class="mt-4 text-sm text-rose-600"
        >
            {{ error }}
        </p></AppLayout
    >
</template>
