<script setup lang="ts">
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import MoneyDisplay from "@/Components/MoneyDisplay.vue";
type Row = {
    id: number;
    code: string;
    name: string;
    type: string;
    debits: string;
    credits: string;
    balance: string;
};
const props = defineProps<{
    report: string;
    data: any;
    rows: Record<string, any>[];
    headers: string[];
    from: string;
    to: string;
}>();
const filters = useForm({ from: props.from, to: props.to });
const titles: Record<string, string> = {
    "trial-balance": "Trial balance",
    "profit-and-loss": "Profit and loss",
    "balance-sheet": "Balance sheet",
    "general-ledger": "General ledger",
};
const print = () => window.print();
const page = usePage();
const contactId =
    new URLSearchParams(page.url.split("?")[1] ?? "").get("contact_id") ?? "";
const title = titles[props.report] ?? props.report.replaceAll("-", " ");
const exportUrl = (format: string) =>
    `/reports/${props.report}?from=${filters.from}&to=${filters.to}&contact_id=${contactId}&format=${format}`;
</script>
<template>
    <Head :title="title" /><AppLayout
        ><PageHeader
            :title="title"
            description="Calculated from posted journal entries · accrual basis"
            ><a :href="exportUrl('pdf')" class="btn-secondary no-print">PDF</a
            ><a :href="exportUrl('xlsx')" class="btn-secondary no-print"
                >Excel</a
            ><button @click="print" class="btn-secondary no-print">
                Print report
            </button></PageHeader
        >
        <form
            @submit.prevent="
                router.get(`/reports/${report}`, {
                    from: filters.from,
                    to: filters.to,
                    contact_id: contactId || undefined,
                })
            "
            class="mb-6 flex flex-wrap gap-3 no-print"
        >
            <input
                v-model="filters.from"
                type="date"
                class="field !w-auto"
                aria-label="From date"
            /><input
                v-model="filters.to"
                type="date"
                class="field !w-auto"
                aria-label="To date"
            /><button class="btn-secondary">Update report</button>
        </form>
        <DataTable
            v-if="report === 'trial-balance'"
            :rows="data"
            :columns="[
                { key: 'code', label: 'Code' },
                { key: 'name', label: 'Account' },
                { key: 'debits', label: 'Debits', right: true },
                { key: 'credits', label: 'Credits', right: true },
                { key: 'balance', label: 'Net debit', right: true },
            ]"
            ><template #debits="{ row }"
                ><MoneyDisplay :amount="row.debits" /></template
            ><template #credits="{ row }"
                ><MoneyDisplay :amount="row.credits" /></template
            ><template #balance="{ row }"
                ><MoneyDisplay :amount="row.balance" /></template
        ></DataTable>
        <div v-else-if="report === 'profit-and-loss'" class="panel max-w-4xl">
            <section
                v-for="section in ['revenue', 'expenses']"
                :key="section"
                class="mb-7"
            >
                <h2
                    class="mb-3 border-b border-slate-200 pb-3 font-semibold capitalize"
                >
                    {{ section }}
                </h2>
                <div
                    v-for="row in data[section]"
                    :key="row.id"
                    class="flex justify-between py-2 text-sm"
                >
                    <span class="text-slate-500"
                        >{{ row.code }} · {{ row.name }}</span
                    ><MoneyDisplay
                        :amount="
                            section === 'revenue'
                                ? String(-Number(row.balance))
                                : row.balance
                        "
                    />
                </div>
                <div
                    class="mt-3 flex justify-between border-t pt-3 text-sm font-semibold"
                >
                    <span>Total {{ section }}</span
                    ><MoneyDisplay :amount="data[`total_${section}`]" />
                </div>
            </section>
            <div
                class="flex justify-between rounded-lg bg-teal-50 p-4 font-semibold text-teal-800"
            >
                <span>Net profit</span
                ><MoneyDisplay :amount="data.net_profit" />
            </div>
        </div>
        <div v-else-if="report === 'balance-sheet'" class="panel max-w-4xl">
            <section
                v-for="section in ['assets', 'liabilities', 'equity']"
                :key="section"
                class="mb-7"
            >
                <h2
                    class="mb-3 border-b border-slate-200 pb-3 font-semibold capitalize"
                >
                    {{ section }}
                </h2>
                <div
                    v-for="row in data[section]"
                    :key="row.id"
                    class="flex justify-between py-2 text-sm"
                >
                    <span class="text-slate-500"
                        >{{ row.code }} · {{ row.name }}</span
                    ><MoneyDisplay
                        :amount="
                            section === 'assets'
                                ? row.balance
                                : String(-Number(row.balance))
                        "
                    />
                </div>
                <div
                    v-if="section === 'equity'"
                    class="flex justify-between py-2 text-sm"
                >
                    <span>Retained earnings through {{ to }}</span
                    ><MoneyDisplay :amount="data.retained_earnings" />
                </div>
                <div
                    class="mt-3 flex justify-between border-t pt-3 text-sm font-semibold"
                >
                    <span>Total {{ section }}</span
                    ><MoneyDisplay :amount="data[`total_${section}`]" />
                </div>
            </section>
        </div>
        <DataTable
            v-else-if="
                ['general-ledger', 'account-transactions'].includes(report)
            "
            :rows="data.data"
            :links="data.links"
            :columns="[
                { key: 'date', label: 'Date' },
                { key: 'account', label: 'Account' },
                { key: 'reference', label: 'Reference' },
                { key: 'debit', label: 'Debit', right: true },
                { key: 'credit', label: 'Credit', right: true },
            ]"
            ><template #date="{ row }">{{
                row.entry.date.slice(0, 10)
            }}</template
            ><template #account="{ row }"
                >{{ row.account.code }} · {{ row.account.name }}</template
            ><template #reference="{ row }">{{ row.entry.reference }}</template
            ><template #debit="{ row }"
                ><MoneyDisplay :amount="row.debit" /></template
            ><template #credit="{ row }"
                ><MoneyDisplay :amount="row.credit" /></template></DataTable
        ><template v-else
            ><div
                v-if="data.opening_balance !== undefined"
                class="panel mb-5 flex flex-wrap gap-8 text-sm"
            >
                <span
                    >Opening
                    <MoneyDisplay :amount="data.opening_balance" /></span
                ><span
                    >Closing <MoneyDisplay :amount="data.closing_balance"
                /></span>
            </div>
            <DataTable
                :rows="rows"
                :columns="
                    headers.map((key) => ({
                        key,
                        label: key.replaceAll('_', ' '),
                    }))
                " /></template
    ></AppLayout>
</template>
