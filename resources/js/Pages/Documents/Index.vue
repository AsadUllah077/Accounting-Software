<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import MoneyDisplay from "@/Components/MoneyDisplay.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import { usePermissions } from "@/Composables/usePermissions";
import type { Document, Paginated } from "@/types/accounting";
const props = defineProps<{
    kind: string;
    documents: Paginated<Document>;
    search: string;
    status?: string;
}>();
const search = ref(props.search),
    status = ref(props.status ?? "");
const { can } = usePermissions();
</script>
<template>
    <Head :title="kind" /><AppLayout
        ><PageHeader
            :title="kind === 'invoices' ? 'Invoices' : 'Bills'"
            description="Track your documents from draft to payment."
            ><Link
                v-if="
                    can(kind === 'invoices' ? 'invoice.create' : 'bill.create')
                "
                :href="`/documents/${kind}/create`"
                class="btn"
                >＋ Create {{ kind === "invoices" ? "invoice" : "bill" }}</Link
            ><a :href="`/export/${kind}`" class="btn-secondary"
                >Export Excel</a
            ></PageHeader
        >
        <form
            @submit.prevent="
                router.get(`/documents/${kind}`, { search, status })
            "
            class="mb-5 flex flex-wrap gap-3"
        >
            <input
                v-model="search"
                class="field !w-64"
                placeholder="Search document number…"
                aria-label="Search document number"
            /><select
                v-model="status"
                class="field !w-48"
                aria-label="Filter status"
            >
                <option value="">All statuses</option>
                <option value="draft">Draft</option>
                <option value="approved">Approved</option>
                <option value="partially_paid">Partially paid</option>
                <option value="paid">Paid</option></select
            ><button class="btn-secondary">Apply</button>
        </form>
        <DataTable
            :rows="documents.data"
            :links="documents.links"
            :columns="[
                { key: 'number', label: 'Number' },
                {
                    key: 'contact',
                    label: kind === 'invoices' ? 'Customer' : 'Supplier',
                },
                { key: 'due_date', label: 'Due date' },
                { key: 'status', label: 'Status' },
                { key: 'total', label: 'Total', right: true },
                { key: 'outstanding', label: 'Outstanding', right: true },
            ]"
            ><template #number="{ row }"
                ><Link
                    :href="`/documents/${kind}/${row.id}`"
                    class="font-medium text-teal-700"
                    >{{ row.invoice_number || row.bill_number }}</Link
                ></template
            ><template #contact="{ row }">{{
                row.customer?.name || row.supplier?.name
            }}</template
            ><template #status="{ row }"
                ><StatusBadge :status="row.status" /></template
            ><template #total="{ row }"
                ><MoneyDisplay :amount="row.total" /></template
            ><template #outstanding="{ row }"
                ><MoneyDisplay
                    :amount="row.outstanding" /></template></DataTable
    ></AppLayout>
</template>
