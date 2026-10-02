<script setup lang="ts">
import { computed } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import FinancialSummaryCard from "@/Components/FinancialSummaryCard.vue";
import MoneyDisplay from "@/Components/MoneyDisplay.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import DateRangeFilter from "@/Components/DateRangeFilter.vue";
import DataTable from "@/Components/DataTable.vue";
import { usePermissions } from "@/Composables/usePermissions";
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
} from "chart.js";
import { Line } from "vue-chartjs";
import type { Document } from "@/types/accounting";
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
);
const props = defineProps<{
    companyName: string;
    summary: Record<string, string>;
    monthly: { month: string; revenue: string; expenses: string }[];
    recentInvoices: Document[];
    overdueInvoices: Document[];
    upcomingBills: Document[];
    banks: { id: number; name: string; balance: string }[];
    recentTransactions: { id: number; reference: string; date: string }[];
    filters: { from: string; to: string };
    financialYears: {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
    }[];
}>();
const filters = useForm({ ...props.filters });
const { can } = usePermissions();
const chart = computed(() => ({
    labels: props.monthly.map((m) => m.month),
    datasets: [
        {
            label: "Revenue",
            data: props.monthly.map((m) => Number(m.revenue)),
            borderColor: "#0f766e",
            backgroundColor: "rgba(15,118,110,0.08)",
            fill: true,
            tension: 0.35,
            pointRadius: 3,
        },
        {
            label: "Expenses",
            data: props.monthly.map((m) => Number(m.expenses)),
            borderColor: "#f59e0b",
            backgroundColor: "transparent",
            tension: 0.35,
            pointRadius: 3,
        },
    ],
}));
const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, grid: { color: "#f1f5f9" } },
    },
};
const cards = [
    {
        label: "Total revenue",
        key: "revenue",
        icon: "↗",
        caption: "Posted revenue in this period",
    },
    {
        label: "Total expenses",
        key: "expenses",
        icon: "↘",
        caption: "Posted expenses in this period",
    },
    {
        label: "Net profit",
        key: "profit",
        icon: "◈",
        caption: "Revenue less expenses",
        accent: true,
    },
    {
        label: "Cash & bank",
        key: "cash",
        icon: "▥",
        caption: "Ledger balance as of period end",
    },
];
</script>
<template>
    <Head title="Overview" /><AppLayout
        ><PageHeader
            title="Business overview"
            :description="`A clear picture of ${companyName}'s finances.`"
            ><Link
                v-if="can('invoice.create')"
                href="/documents/invoices/create"
                class="btn"
                >＋ New invoice</Link
            ></PageHeader
        >
        <DateRangeFilter
            :from="filters.from"
            :to="filters.to"
            :years="financialYears"
            @apply="(value) => router.get('/dashboard', value)"
        />
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <FinancialSummaryCard
                v-for="card in cards"
                :key="card.key"
                :label="card.label"
                :amount="summary[card.key]"
                :icon="card.icon"
                :caption="card.caption"
                :accent="card.accent"
            />
        </div>
        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="panel xl:col-span-2">
                <div
                    class="mb-7 flex flex-wrap items-start justify-between gap-3"
                >
                    <div>
                        <h2 class="font-semibold">Revenue & expenses</h2>
                        <p class="mt-1 text-xs text-slate-400">
                            Monthly performance · accrual basis
                        </p>
                    </div>
                    <div class="flex gap-4 text-xs text-slate-500">
                        <span
                            ><span class="mr-1 text-teal-600">●</span>
                            Revenue</span
                        ><span
                            ><span class="mr-1 text-amber-500">●</span>
                            Expenses</span
                        >
                    </div>
                </div>
                <div class="h-64">
                    <Line
                        v-if="monthly.length"
                        :data="chart"
                        :options="options"
                    />
                    <p v-else class="pt-24 text-center text-sm text-slate-400">
                        No activity in this reporting period.
                    </p>
                </div>
            </section>
            <section class="panel">
                <h2 class="font-semibold">Money to watch</h2>
                <p class="mt-1 text-xs text-slate-400">
                    Outstanding balances across all dates
                </p>
                <div class="mt-7 border-b border-slate-100 pb-6">
                    <div class="flex justify-between text-sm text-slate-500">
                        <span>Customers owe you</span
                        ><span class="text-teal-600">↗</span>
                    </div>
                    <p class="mt-2 text-2xl font-semibold">
                        <MoneyDisplay :amount="summary.receivables" />
                    </p>
                    <Link
                        href="/documents/invoices"
                        class="mt-3 inline-block text-xs font-medium text-teal-700"
                        >View invoices →</Link
                    >
                </div>
                <div class="pt-6">
                    <div class="flex justify-between text-sm text-slate-500">
                        <span>You owe suppliers</span
                        ><span class="text-amber-500">↘</span>
                    </div>
                    <p class="mt-2 text-2xl font-semibold">
                        <MoneyDisplay :amount="summary.payables" />
                    </p>
                    <Link
                        href="/documents/bills"
                        class="mt-3 inline-block text-xs font-medium text-teal-700"
                        >View bills →</Link
                    >
                </div>
            </section>
        </div>
        <div class="mt-7 flex items-center justify-between">
            <h2 class="font-semibold">Recent invoices</h2>
            <Link
                href="/documents/invoices"
                class="text-xs font-medium text-teal-700"
                >View all invoices →</Link
            >
        </div>
        <div class="mt-4">
            <DataTable
                :rows="recentInvoices"
                :columns="[
                    { key: 'invoice_number', label: 'Invoice' },
                    { key: 'customer', label: 'Customer' },
                    { key: 'due_date', label: 'Due date' },
                    { key: 'status', label: 'Status' },
                    { key: 'total', label: 'Amount', right: true },
                ]"
                ><template #invoice_number="{ row }"
                    ><Link
                        :href="`/documents/invoices/${row.id}`"
                        class="font-medium text-teal-700"
                        >{{ row.invoice_number }}</Link
                    ></template
                ><template #customer="{ row }">{{
                    row.customer?.name
                }}</template
                ><template #status="{ row }"
                    ><StatusBadge :status="row.status" /></template
                ><template #total="{ row }"
                    ><MoneyDisplay :amount="row.total" /></template
            ></DataTable>
        </div>
        <div class="mt-6 grid gap-6 md:grid-cols-3">
            <section class="panel">
                <h2 class="mb-4 font-semibold">Bank balances</h2>
                <div
                    v-for="bank in banks"
                    :key="bank.id"
                    class="flex justify-between border-t border-slate-100 py-4 text-sm"
                >
                    <span class="text-slate-500">{{ bank.name }}</span
                    ><MoneyDisplay :amount="bank.balance" />
                </div>
            </section>
            <section class="panel">
                <h2 class="mb-4 font-semibold">Overdue invoices</h2>
                <Link
                    v-for="invoice in overdueInvoices"
                    :key="invoice.id"
                    :href="`/documents/invoices/${invoice.id}`"
                    class="flex justify-between border-t border-slate-100 py-3 text-sm"
                    ><span class="text-slate-500">{{
                        invoice.customer?.name
                    }}</span
                    ><MoneyDisplay
                        :amount="invoice.outstanding"
                        class="text-rose-600"
                /></Link>
                <p
                    v-if="!overdueInvoices.length"
                    class="py-5 text-sm text-slate-400"
                >
                    You're all caught up.
                </p>
            </section>
            <section class="panel">
                <h2 class="mb-4 font-semibold">Upcoming bills</h2>
                <Link
                    v-for="bill in upcomingBills"
                    :key="bill.id"
                    :href="`/documents/bills/${bill.id}`"
                    class="flex justify-between border-t border-slate-100 py-3 text-sm"
                    ><span class="text-slate-500">{{
                        bill.supplier?.name
                    }}</span
                    ><MoneyDisplay :amount="bill.outstanding"
                /></Link>
                <p
                    v-if="!upcomingBills.length"
                    class="py-5 text-sm text-slate-400"
                >
                    No upcoming bills.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
