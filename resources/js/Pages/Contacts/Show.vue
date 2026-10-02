<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import MoneyDisplay from "@/Components/MoneyDisplay.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import type { Contact, Document, Paginated } from "@/types/accounting";
defineProps<{
    contact: Contact;
    records: Paginated<Document>;
    kind: string;
    balance: string;
}>();
</script>
<template>
    <Head :title="contact.name" /><AppLayout
        ><PageHeader :title="contact.name" :description="contact.email"
            ><Link :href="`/contacts/${kind}`" class="btn-secondary"
                >Back to contacts</Link
            ><Link
                :href="`/reports/${kind === 'customers' ? 'customer' : 'supplier'}-statement?contact_id=${contact.id}`"
                class="btn-secondary"
                >Statement</Link
            ></PageHeader
        >
        <div class="panel mb-6">
            <h2 class="font-semibold">Contact details</h2>
            <div class="mt-4">
                <p class="text-sm text-slate-500">Posted balance as of today</p>
                <div class="mt-1 text-2xl font-semibold">
                    <MoneyDisplay :amount="balance" />
                </div>
            </div>
            <p class="mt-3 text-sm text-slate-500">
                {{ contact.address || "No address recorded" }} ·
                {{ contact.phone || "No phone recorded" }}
            </p>
            <p class="mt-2 text-sm text-slate-500">
                Tax number: {{ contact.tax_number || "—" }}
            </p>
        </div>
        <DataTable
            :rows="records.data"
            :links="records.links"
            :columns="[
                { key: 'number', label: 'Document' },
                { key: 'due_date', label: 'Due date' },
                { key: 'status', label: 'Status' },
                { key: 'total', label: 'Total', right: true },
                { key: 'outstanding', label: 'Outstanding', right: true },
            ]"
            ><template #number="{ row }"
                ><Link
                    :href="`/documents/${kind === 'customers' ? 'invoices' : 'bills'}/${row.id}`"
                    class="text-teal-700"
                    >{{ row.invoice_number || row.bill_number }}</Link
                ></template
            ><template #status="{ row }"
                ><StatusBadge :status="row.status" /></template
            ><template #total="{ row }"
                ><MoneyDisplay :amount="row.total" /></template
            ><template #outstanding="{ row }"
                ><MoneyDisplay
                    :amount="row.outstanding" /></template></DataTable
    ></AppLayout>
</template>
