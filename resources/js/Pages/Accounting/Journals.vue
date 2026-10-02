<script setup lang="ts">
import { ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import FormField from "@/Components/FormField.vue";
import MoneyDisplay from "@/Components/MoneyDisplay.vue";
import { usePermissions } from "@/Composables/usePermissions";
import type { Account, Paginated } from "@/types/accounting";
interface Journal {
    id: number;
    date: string;
    reference: string;
    status: string;
    source_type?: string;
    reversal_of_id?: number;
    lines: { id: number; debit: string; credit: string; account: Account }[];
}
const props = defineProps<{
    journals: Paginated<Journal>;
    accounts: Account[];
}>();
const { can } = usePermissions();
const show = ref(false),
    expanded = ref<number | null>(null);
const line = () => ({
    account_id: props.accounts[0]?.id ?? "",
    debit: "0.00",
    credit: "0.00",
});
const form = useForm({
    date: new Date().toISOString().slice(0, 10),
    reference: "",
    lines: [line(), line()],
});
function reverse(id: number) {
    const date = window.prompt(
        "Reversal date (YYYY-MM-DD)",
        new Date().toISOString().slice(0, 10),
    );
    if (date) router.post(`/accounting/journals/${id}/reverse`, { date });
}
</script>
<template>
    <Head title="Journals" /><AppLayout
        ><PageHeader
            title="Manual journals"
            description="Post balanced entries and preserve the full audit trail."
            ><button
                v-if="can('accounting.post')"
                @click="show = !show"
                class="btn"
            >
                ＋ New journal
            </button></PageHeader
        >
        <form
            v-if="show"
            @submit.prevent="
                form.post('/accounting/journals', {
                    onSuccess: () => {
                        show = false;
                        form.reset();
                    },
                })
            "
            class="panel mb-6 space-y-5"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <FormField label="Date"
                    ><input
                        v-model="form.date"
                        type="date"
                        class="field" /></FormField
                ><FormField label="Reference"
                    ><input v-model="form.reference" class="field" required
                /></FormField>
            </div>
            <div
                v-for="(entry, i) in form.lines"
                :key="i"
                class="grid grid-cols-3 gap-4"
            >
                <select
                    v-model="entry.account_id"
                    class="field"
                    aria-label="Account"
                >
                    <option
                        v-for="account in accounts"
                        :key="account.id"
                        :value="account.id"
                    >
                        {{ account.code }} · {{ account.name }}
                    </option></select
                ><input
                    v-model="entry.debit"
                    type="number"
                    step="0.01"
                    min="0"
                    class="field"
                    aria-label="Debit"
                /><input
                    v-model="entry.credit"
                    type="number"
                    step="0.01"
                    min="0"
                    class="field"
                    aria-label="Credit"
                />
            </div>
            <button
                type="button"
                @click="form.lines.push(line())"
                class="text-sm text-teal-700"
            >
                ＋ Add line
            </button>
            <p
                v-for="(error, key) in form.errors"
                :key="key"
                class="text-sm text-rose-600"
            >
                {{ error }}
            </p>
            <div class="flex gap-3">
                <button class="btn" :disabled="form.processing">
                    Post journal</button
                ><button
                    type="button"
                    @click="show = false"
                    class="btn-secondary"
                >
                    Cancel
                </button>
            </div>
        </form>
        <DataTable
            :rows="journals.data"
            :links="journals.links"
            :columns="[
                { key: 'id', label: 'Journal' },
                { key: 'date', label: 'Date' },
                { key: 'reference', label: 'Reference' },
                { key: 'status', label: 'Status' },
                { key: 'actions', label: 'Actions', right: true },
            ]"
            ><template #id="{ row }"
                ><button
                    @click="expanded = expanded === row.id ? null : row.id"
                    class="text-teal-700"
                >
                    JE-{{ row.id }}
                </button></template
            ><template #actions="{ row }"
                ><button
                    v-if="
                        !row.source_type &&
                        !row.reversal_of_id &&
                        can('accounting.reverse')
                    "
                    @click="reverse(row.id)"
                    class="text-xs text-teal-700"
                >
                    Reverse
                </button></template
            ></DataTable
        >
        <section v-if="expanded" class="panel mt-6">
            <h2 class="mb-5 font-semibold">Journal JE-{{ expanded }}</h2>
            <div
                v-for="entry in journals.data.find((j) => j.id === expanded)
                    ?.lines"
                :key="entry.id"
                class="grid grid-cols-3 border-t border-slate-100 py-3 text-sm"
            >
                <span>{{ entry.account.code }} · {{ entry.account.name }}</span
                ><span>Debit <MoneyDisplay :amount="entry.debit" /></span
                ><span>Credit <MoneyDisplay :amount="entry.credit" /></span>
            </div></section
    ></AppLayout>
</template>
