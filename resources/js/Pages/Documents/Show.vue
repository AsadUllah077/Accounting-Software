<script setup lang="ts">
import { ref } from "vue";
import CreditNoteForm from "@/Components/CreditNoteForm.vue";
import AttachmentPanel from "@/Components/AttachmentPanel.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import MoneyDisplay from "@/Components/MoneyDisplay.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import FormField from "@/Components/FormField.vue";
import { usePermissions } from "@/Composables/usePermissions";
import type { Document } from "@/types/accounting";
const creditOpen = ref(false);
const props = defineProps<{
    kind: string;
    document: Document;
    banks: { id: number; name: string }[];
    files: { id: number; file_name: string }[];
}>();
const { can } = usePermissions();
const payment = useForm({
    amount: props.document.outstanding,
    paid_at: new Date().toISOString().slice(0, 10),
    bank_account_id: props.banks[0]?.id ?? "",
    reference: "",
    idempotency_key: crypto.randomUUID(),
});
function voidDocument() {
    const date = window.prompt(
        "Void date (YYYY-MM-DD). This creates an opposite journal.",
        new Date().toISOString().slice(0, 10),
    );
    if (date)
        router.post(`/documents/${props.kind}/${props.document.id}/void`, {
            date,
        });
}
function approve() {
    if (
        window.confirm(
            "Approve and post this document? Posted amounts cannot be edited.",
        )
    )
        router.post(`/documents/${props.kind}/${props.document.id}/approve`);
}
function pay() {
    payment.post(`/documents/${props.kind}/${props.document.id}/payments`, {
        onSuccess: () => {
            payment.idempotency_key = crypto.randomUUID();
            payment.amount = "";
        },
    });
}
</script>
<template>
    <Head :title="document.invoice_number || document.bill_number" /><AppLayout
        ><PageHeader
            :title="document.invoice_number || document.bill_number || ''"
            :description="document.customer?.name || document.supplier?.name"
            ><Link :href="`/documents/${kind}`" class="btn-secondary">Back</Link
            ><a
                :href="`/documents/${kind}/${document.id}/pdf`"
                class="btn-secondary"
                >PDF</a
            ><Link
                v-if="
                    document.status === 'draft' &&
                    can(kind === 'invoices' ? 'invoice.update' : 'bill.update')
                "
                :href="`/documents/${kind}/${document.id}/edit`"
                class="btn-secondary"
                >Edit draft</Link
            ><button
                v-if="
                    document.status === 'draft' &&
                    can(
                        kind === 'invoices'
                            ? 'invoice.approve'
                            : 'bill.approve',
                    )
                "
                @click="approve"
                class="btn"
            >
                Approve & post
            </button></PageHeader
        >
        <AttachmentPanel
            :kind="kind === 'invoices' ? 'invoice' : 'bill'"
            :record-id="document.id"
            :files="files"
            :can-upload="
                can(kind === 'invoices' ? 'invoice.update' : 'bill.update')
            "
        />
        <div class="grid gap-6 xl:grid-cols-3">
            <section class="panel xl:col-span-2">
                <div class="mb-7 flex justify-between">
                    <div>
                        <StatusBadge :status="document.status" />
                        <p class="mt-3 text-sm text-slate-500">
                            Date:
                            {{ document.issue_date || document.bill_date }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            Due: {{ document.due_date }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-400">Outstanding</p>
                        <p class="mt-1 text-2xl font-semibold">
                            <MoneyDisplay :amount="document.outstanding" />
                        </p>
                    </div>
                </div>
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-xs text-slate-400">
                        <tr>
                            <th class="py-3">Description</th>
                            <th>Qty</th>
                            <th class="text-right">Unit price</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="line in document.items"
                            :key="line.id"
                            class="border-b border-slate-100"
                        >
                            <td class="py-4">{{ line.description }}</td>
                            <td>{{ line.quantity }}</td>
                            <td class="text-right">
                                <MoneyDisplay :amount="line.unit_price" />
                            </td>
                            <td class="text-right">
                                <MoneyDisplay :amount="line.total ?? '0'" />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <dl class="ml-auto mt-6 max-w-xs space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Subtotal</dt>
                        <dd><MoneyDisplay :amount="document.subtotal" /></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Tax</dt>
                        <dd><MoneyDisplay :amount="document.tax_total" /></dd>
                    </div>
                    <div
                        class="flex justify-between border-t pt-3 font-semibold"
                    >
                        <dt>Total</dt>
                        <dd><MoneyDisplay :amount="document.total" /></dd>
                    </div>
                </dl>
                <p v-if="document.notes" class="mt-8 text-sm text-slate-500">
                    {{ document.notes }}
                </p>
            </section>
            <section class="panel">
                <h2 class="font-semibold">Payment history</h2>
                <p
                    v-if="!document.payments.length"
                    class="my-5 text-sm text-slate-400"
                >
                    No payments recorded.
                </p>
                <div
                    v-for="record in document.payments"
                    :key="record.id"
                    class="flex justify-between border-b border-slate-100 py-4 text-sm"
                >
                    <span>{{ record.paid_at }}</span
                    ><MoneyDisplay :amount="record.amount" />
                </div>
                <form
                    v-if="
                        ['approved', 'sent', 'partially_paid'].includes(
                            document.status,
                        ) &&
                        can(kind === 'invoices' ? 'invoice.pay' : 'bill.pay')
                    "
                    @submit.prevent="pay"
                    class="mt-6 space-y-4"
                >
                    <h3 class="text-sm font-semibold">Record payment</h3>
                    <FormField label="Amount" :error="payment.errors.amount"
                        ><input
                            v-model="payment.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="field"
                            required /></FormField
                    ><FormField label="Date" :error="payment.errors.paid_at"
                        ><input
                            v-model="payment.paid_at"
                            type="date"
                            class="field"
                            required /></FormField
                    ><FormField
                        label="Bank account"
                        :error="payment.errors.bank_account_id"
                        ><select
                            v-model="payment.bank_account_id"
                            class="field"
                        >
                            <option
                                v-for="bank in banks"
                                :key="bank.id"
                                :value="bank.id"
                            >
                                {{ bank.name }}
                            </option>
                        </select></FormField
                    ><FormField label="Reference"
                        ><input v-model="payment.reference" class="field"
                    /></FormField>
                    <p
                        v-for="(error, key) in payment.errors"
                        :key="key"
                        class="text-xs text-rose-600"
                    >
                        {{ error }}
                    </p>
                    <button :disabled="payment.processing" class="btn w-full">
                        Record payment
                    </button>
                </form>
            </section>
        </div>
        <div class="mt-6 flex flex-wrap gap-3">
            <button
                v-if="
                    kind === 'invoices' &&
                    ['approved', 'sent', 'paid', 'partially_paid'].includes(
                        document.status,
                    ) &&
                    can('invoice.approve')
                "
                @click="creditOpen = !creditOpen"
                class="btn-secondary"
            >
                Issue credit note</button
            ><button
                v-if="
                    kind === 'invoices' &&
                    document.status === 'approved' &&
                    can('invoice.send')
                "
                @click="router.post(`/invoices/${document.id}/send`)"
                class="btn-secondary"
            >
                Email invoice</button
            ><button
                v-if="
                    ['approved', 'sent'].includes(document.status) &&
                    can(kind === 'invoices' ? 'invoice.void' : 'bill.void')
                "
                @click="voidDocument"
                class="btn-secondary"
            >
                Void document
            </button>
        </div>
        <CreditNoteForm
            v-if="creditOpen"
            :document="document"
            @close="creditOpen = false"
        />
        <p
            v-for="(error, key) in $page.props.errors"
            :key="key"
            class="mt-4 text-sm text-rose-600"
        >
            {{ error }}
        </p></AppLayout
    >
</template>
