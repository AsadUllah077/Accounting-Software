<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import MoneyDisplay from "@/Components/MoneyDisplay.vue";
import type { Paginated } from "@/types/accounting";
import { usePermissions } from "@/Composables/usePermissions";
const props = defineProps<{
    notes: Paginated<any>;
    banks: { id: number; name: string }[];
}>();
const { can } = usePermissions();
function refund(id: number) {
    const amount = window.prompt(
        "Refund amount (must be within available customer credit)",
    );
    if (!amount) return;
    const bank = window.prompt(
        "Bank account ID: " +
            props.banks.map((b) => `${b.id} ${b.name}`).join(", "),
        String(props.banks[0]?.id ?? ""),
    );
    if (bank)
        router.post(`/credit-notes/${id}/refunds`, {
            amount,
            bank_account_id: bank,
            date: new Date().toISOString().slice(0, 10),
            idempotency_key: crypto.randomUUID(),
        });
}
</script>
<template>
    <Head title="Credit notes" /><AppLayout
        ><PageHeader
            title="Credit notes"
            description="Posted sales adjustments and customer refunds."
        /><DataTable
            :rows="notes.data"
            :links="notes.links"
            :columns="[
                { key: 'credit_number', label: 'Credit note' },
                { key: 'date', label: 'Date' },
                { key: 'customer', label: 'Customer' },
                { key: 'reason', label: 'Reason' },
                { key: 'amount', label: 'Credit total', right: true },
                { key: 'refund', label: 'Actions' },
            ]"
            ><template #customer="{ row }">{{
                row.invoice.customer.name
            }}</template
            ><template #amount="{ row }"
                ><MoneyDisplay :amount="row.amount" /></template
            ><template #refund="{ row }"
                ><button
                    v-if="can('invoice.pay')"
                    @click="refund(row.id)"
                    class="text-sm text-teal-700"
                >
                    Record refund
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
