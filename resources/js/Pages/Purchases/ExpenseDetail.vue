<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import MoneyDisplay from "@/Components/MoneyDisplay.vue";
import AttachmentPanel from "@/Components/AttachmentPanel.vue";
import DataTable from "@/Components/DataTable.vue";
import { usePermissions } from "@/Composables/usePermissions";
defineProps<{
    expense: {
        id: number;
        description: string;
        date: string;
        amount: string;
        tax_amount: string;
        category: { name: string };
        bank_account: { name: string };
        attachments: { id: number; file_name: string }[];
        journal: {
            reference: string;
            lines: {
                id: number;
                debit: string;
                credit: string;
                account: { name: string };
            }[];
        };
    };
}>();
const { can } = usePermissions();
</script>
<template>
    <Head :title="expense.description" /><AppLayout>
        <PageHeader
            :title="expense.description"
            :description="`Paid expense · ${expense.date}`"
            ><Link href="/expenses" class="btn-secondary"
                >Back to expenses</Link
            ></PageHeader
        >
        <div class="panel grid gap-5 sm:grid-cols-4">
            <div>
                <p class="text-sm text-slate-500">Before tax</p>
                <MoneyDisplay :amount="expense.amount" />
            </div>
            <div>
                <p class="text-sm text-slate-500">Tax</p>
                <MoneyDisplay :amount="expense.tax_amount" />
            </div>
            <div>
                <p class="text-sm text-slate-500">Category</p>
                {{ expense.category.name }}
            </div>
            <div>
                <p class="text-sm text-slate-500">Paid from</p>
                {{ expense.bank_account.name }}
            </div>
        </div>
        <AttachmentPanel
            kind="expense"
            :record-id="expense.id"
            :files="expense.attachments"
            :can-upload="can('expense.create')"
        />
        <section v-if="can('accounting.view')" class="mt-6">
            <h2 class="mb-3 font-semibold">
                Posted journal · {{ expense.journal.reference }}
            </h2>
            <DataTable
                :rows="expense.journal.lines"
                :columns="[
                    { key: 'account', label: 'Account' },
                    { key: 'debit', label: 'Debit', right: true },
                    { key: 'credit', label: 'Credit', right: true },
                ]"
                ><template #account="{ row }">{{ row.account.name }}</template
                ><template #debit="{ row }"
                    ><MoneyDisplay :amount="row.debit" /></template
                ><template #credit="{ row }"
                    ><MoneyDisplay :amount="row.credit" /></template
            ></DataTable>
        </section>
    </AppLayout>
</template>
