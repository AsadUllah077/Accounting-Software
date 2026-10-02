<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import type { Document } from "@/types/accounting";
const props = defineProps<{ document: Document }>();
defineEmits<{ close: [] }>();
const form = useForm({
    date: new Date().toISOString().slice(0, 10),
    reason: "",
    items: props.document.items.map((item) => ({
        invoice_item_id: item.id,
        description: item.description,
        amount: "0.00",
    })),
});
function submit() {
    form.transform((data) => ({
        ...data,
        items: data.items
            .filter((item) => Number(item.amount) > 0)
            .map(({ invoice_item_id, amount }) => ({
                invoice_item_id,
                amount,
            })),
    })).post(`/invoices/${props.document.id}/credits`);
}
</script>
<template>
    <form @submit.prevent="submit" class="panel mt-6 space-y-4">
        <h2 class="font-semibold">Issue credit note</h2>
        <p class="text-sm text-slate-500">
            Enter amounts before tax for the lines being credited. Tax follows
            the original invoice.
        </p>
        <input
            v-model="form.date"
            type="date"
            class="field"
            aria-label="Credit date"
        /><input
            v-model="form.reason"
            class="field"
            placeholder="Reason for credit"
            aria-label="Reason for credit"
            required
        />
        <div
            v-for="item in form.items"
            :key="item.invoice_item_id"
            class="flex items-center justify-between gap-4"
        >
            <span class="text-sm">{{ item.description }}</span
            ><input
                v-model="item.amount"
                type="number"
                min="0"
                step="0.01"
                class="field !w-40"
                aria-label="Credit amount before tax"
            />
        </div>
        <p
            v-for="(error, key) in form.errors"
            :key="key"
            class="text-sm text-rose-600"
        >
            {{ error }}
        </p>
        <div class="flex gap-3">
            <button class="btn" :disabled="form.processing">
                Issue & post credit</button
            ><button
                type="button"
                @click="$emit('close')"
                class="btn-secondary"
            >
                Close
            </button>
        </div>
    </form>
</template>
