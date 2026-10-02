<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import FormField from "@/Components/FormField.vue";
import MoneyDisplay from "@/Components/MoneyDisplay.vue";
import { usePermissions } from "@/Composables/usePermissions";
import type { Paginated } from "@/types/accounting";
const props = defineProps<{
    expenses: Paginated<any>;
    categories: { id: number; name: string }[];
    banks: { id: number; name: string }[];
    taxRates: { id: number; name: string }[];
    search: string;
}>();
const { can } = usePermissions();
const visible = ref(false);
function newExpenseKey() {
    return crypto.randomUUID();
}
const searchForm = useForm({ search: props.search });
const form = useForm({
    idempotency_key: crypto.randomUUID(),
    category_id: props.categories[0]?.id ?? "",
    bank_account_id: props.banks[0]?.id ?? "",
    tax_rate_id: null as number | null,
    date: new Date().toISOString().slice(0, 10),
    description: "",
    amount: "",
});
</script>
<template>
    <Head title="Expenses" /><AppLayout
        ><PageHeader
            title="Expenses"
            description="Record paid expenses directly to the ledger."
            ><button
                v-if="can('expense.create')"
                @click="visible = !visible"
                class="btn"
            >
                ＋ New expense
            </button></PageHeader
        >
        <form
            v-if="visible"
            @submit.prevent="
                form.post('/expenses', {
                    onSuccess: () => {
                        visible = false;
                        form.reset();
                        form.idempotency_key = newExpenseKey();
                    },
                })
            "
            class="panel mb-6 space-y-5"
        >
            <div class="grid gap-4 md:grid-cols-3">
                <FormField label="Description"
                    ><input
                        v-model="form.description"
                        class="field"
                        required /></FormField
                ><FormField label="Date"
                    ><input
                        v-model="form.date"
                        type="date"
                        class="field" /></FormField
                ><FormField label="Amount before tax"
                    ><input
                        v-model="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        class="field"
                        required /></FormField
                ><FormField label="Category"
                    ><select v-model="form.category_id" class="field">
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select></FormField
                ><FormField label="Paid from"
                    ><select v-model="form.bank_account_id" class="field">
                        <option
                            v-for="bank in banks"
                            :key="bank.id"
                            :value="bank.id"
                        >
                            {{ bank.name }}
                        </option>
                    </select></FormField
                ><FormField label="Tax"
                    ><select v-model="form.tax_rate_id" class="field">
                        <option :value="null">No tax</option>
                        <option
                            v-for="tax in taxRates"
                            :key="tax.id"
                            :value="tax.id"
                        >
                            {{ tax.name }}
                        </option>
                    </select></FormField
                >
            </div>
            <p
                v-for="(error, key) in form.errors"
                :key="key"
                class="text-sm text-rose-600"
            >
                {{ error }}
            </p>
            <div class="flex gap-3">
                <button :disabled="form.processing" class="btn">
                    Save & post expense</button
                ><button
                    type="button"
                    @click="visible = false"
                    class="btn-secondary"
                >
                    Cancel
                </button>
            </div>
        </form>
        <form
            @submit.prevent="
                searchForm.get('/expenses', { preserveState: true })
            "
            class="mb-5 flex gap-3"
        >
            <input
                v-model="searchForm.search"
                class="field max-w-sm"
                placeholder="Search expenses"
                aria-label="Search expenses"
            /><button class="btn-secondary">Search</button>
        </form>
        <DataTable
            :rows="expenses.data"
            :links="expenses.links"
            :columns="[
                { key: 'date', label: 'Date' },
                { key: 'description', label: 'Description' },
                { key: 'category', label: 'Category' },
                { key: 'bank', label: 'Paid from' },
                { key: 'amount', label: 'Before tax', right: true },
                { key: 'tax_amount', label: 'Tax', right: true },
            ]"
            ><template #description="{ row }"
                ><Link :href="`/expenses/${row.id}`" class="text-teal-700">{{
                    row.description
                }}</Link></template
            ><template #category="{ row }">{{ row.category.name }}</template
            ><template #bank="{ row }">{{ row.bank_account.name }}</template
            ><template #amount="{ row }"
                ><MoneyDisplay :amount="row.amount" /></template
            ><template #tax_amount="{ row }"
                ><MoneyDisplay :amount="row.tax_amount" /></template></DataTable
    ></AppLayout>
</template>
