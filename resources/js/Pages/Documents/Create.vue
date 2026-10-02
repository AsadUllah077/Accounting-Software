<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import FormField from "@/Components/FormField.vue";
import type { Contact, Account, Item, Document } from "@/types/accounting";
const props = defineProps<{
    kind: string;
    document?: Document;
    contacts: Contact[];
    accounts: Account[];
    taxRates: { id: number; name: string; rate: string }[];
}>();
const item = (): Item => ({
    description: "",
    quantity: "1",
    unit_price: "0.00",
    discount: "0.00",
    account_id: props.accounts[0]?.id ?? "",
    tax_rate_id: null,
});
const today = new Date().toISOString().slice(0, 10);
const doc = props.document;
const form = useForm({
    customer_id: doc?.customer_id ?? "",
    supplier_id: doc?.supplier_id ?? "",
    date: doc?.issue_date ?? doc?.bill_date ?? today,
    due_date: doc?.due_date ?? today,
    notes: doc?.notes ?? "",
    items: doc?.items ?? [item()],
});
function submit() {
    doc
        ? form.put(`/documents/${props.kind}/${doc.id}`)
        : form.post(`/documents/${props.kind}`);
}
</script>
<template>
    <Head title="Create document" /><AppLayout
        ><PageHeader
            :title="kind === 'invoices' ? 'New invoice' : 'New bill'"
            description="Save a draft, review the amounts, then approve to post."
            ><Link :href="`/documents/${kind}`" class="btn-secondary"
                >Cancel</Link
            ></PageHeader
        >
        <form @submit.prevent="submit" class="panel space-y-7">
            <div class="grid gap-5 md:grid-cols-3">
                <FormField
                    :label="kind === 'invoices' ? 'Customer' : 'Supplier'"
                    required
                    :error="form.errors.customer_id || form.errors.supplier_id"
                    ><select
                        v-if="kind === 'invoices'"
                        v-model="form.customer_id"
                        class="field"
                        required
                    >
                        <option value="">Select customer</option>
                        <option
                            v-for="contact in contacts"
                            :key="contact.id"
                            :value="contact.id"
                        >
                            {{ contact.name }}
                        </option></select
                    ><select
                        v-else
                        v-model="form.supplier_id"
                        class="field"
                        required
                    >
                        <option value="">Select supplier</option>
                        <option
                            v-for="contact in contacts"
                            :key="contact.id"
                            :value="contact.id"
                        >
                            {{ contact.name }}
                        </option>
                    </select></FormField
                ><FormField
                    label="Document date"
                    required
                    :error="form.errors.date"
                    ><input
                        v-model="form.date"
                        type="date"
                        class="field"
                        required /></FormField
                ><FormField
                    label="Due date"
                    required
                    :error="form.errors.due_date"
                    ><input
                        v-model="form.due_date"
                        type="date"
                        class="field"
                        required
                /></FormField>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-500">
                            <th class="pb-3">Description</th>
                            <th>Account</th>
                            <th>Qty</th>
                            <th>Unit price</th>
                            <th>Discount</th>
                            <th>Tax</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, i) in form.items" :key="i">
                            <td class="min-w-48 py-2 pr-3">
                                <input
                                    v-model="line.description"
                                    class="field"
                                    :aria-label="`Line ${i + 1} description`"
                                    required
                                />
                            </td>
                            <td class="min-w-40 pr-3">
                                <select
                                    v-model="line.account_id"
                                    class="field"
                                    :aria-label="`Line ${i + 1} account`"
                                >
                                    <option
                                        v-for="account in accounts"
                                        :key="account.id"
                                        :value="account.id"
                                    >
                                        {{ account.code }} · {{ account.name }}
                                    </option>
                                </select>
                            </td>
                            <td class="min-w-20 pr-3">
                                <input
                                    v-model="line.quantity"
                                    type="number"
                                    step="0.0001"
                                    min="0.0001"
                                    class="field"
                                    aria-label="Quantity"
                                />
                            </td>
                            <td class="min-w-28 pr-3">
                                <input
                                    v-model="line.unit_price"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    class="field"
                                    aria-label="Unit price"
                                />
                            </td>
                            <td class="min-w-24 pr-3">
                                <input
                                    v-model="line.discount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="field"
                                    aria-label="Discount amount"
                                />
                            </td>
                            <td class="min-w-28">
                                <select
                                    v-model="line.tax_rate_id"
                                    class="field"
                                    aria-label="Tax rate"
                                >
                                    <option :value="null">No tax</option>
                                    <option
                                        v-for="tax in taxRates"
                                        :key="tax.id"
                                        :value="tax.id"
                                    >
                                        {{ tax.name }}
                                    </option>
                                </select>
                            </td>
                            <td class="pl-2">
                                <button
                                    v-if="form.items.length > 1"
                                    @click="form.items.splice(i, 1)"
                                    type="button"
                                    aria-label="Remove line"
                                    class="text-slate-400"
                                >
                                    ×
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button
                @click="form.items.push(item())"
                type="button"
                class="text-sm font-medium text-teal-700"
            >
                ＋ Add line item</button
            ><FormField label="Notes" :error="form.errors.notes">
                <textarea v-model="form.notes" class="field" rows="3" />
            </FormField>
            <div
                v-if="Object.keys(form.errors).length"
                class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700"
                role="alert"
            >
                <p v-for="(error, key) in form.errors" :key="key">
                    {{ error }}
                </p>
            </div>
            <p class="text-xs text-slate-400">
                Totals and tax are calculated on the server. Review the saved
                draft before approval.
            </p>
            <button :disabled="form.processing" class="btn">
                {{ form.processing ? "Saving…" : "Save draft" }}
            </button>
        </form></AppLayout
    >
</template>
