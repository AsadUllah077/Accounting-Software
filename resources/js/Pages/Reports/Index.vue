<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
const reports = [
    ["profit-and-loss", "Profit and loss", "Revenue, expenses, and net profit"],
    [
        "balance-sheet",
        "Balance sheet",
        "Assets, liabilities, and accumulated equity",
    ],
    [
        "trial-balance",
        "Trial balance",
        "Debit and credit movements for every account",
    ],
    ["general-ledger", "General ledger", "Posted account transactions"],
    [
        "accounts-receivable",
        "Accounts receivable",
        "Customer balances aged by due date",
    ],
    [
        "accounts-payable",
        "Accounts payable",
        "Supplier balances aged by due date",
    ],
    ["cash-flow", "Cash flow", "Signed bank movements by counter account"],
    ["tax-summary", "Tax summary", "Input and output tax account activity"],
    ["expense-summary", "Expense summary", "Posted expense movements"],
];
</script>
<template>
    <Head title="Reports" /><AppLayout
        ><PageHeader
            title="Financial reports"
            description="A consistent view of your posted accounting records."
            ><Link href="/exports" class="btn-secondary"
                >My exports</Link
            ></PageHeader
        >
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="[slug, title, description] in reports"
                :key="slug"
                :href="`/reports/${slug}`"
                class="panel hover:border-teal-300"
                ><span class="text-teal-600">▦</span>
                <h2 class="mt-3 font-semibold">{{ title }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ description }}</p>
                <span
                    class="mt-5 inline-block text-xs font-medium text-teal-700"
                    >Open report →</span
                ></Link
            >
        </div></AppLayout
    >
</template>
