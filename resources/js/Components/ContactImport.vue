<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
const props = defineProps<{ kind: string }>();
const form = useForm({ file: null as File | null });
function choose(event: Event) {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}
</script>
<template>
    <details class="panel mb-6">
        <summary class="cursor-pointer text-sm font-medium">
            Import contacts from CSV
        </summary>
        <p class="my-4 text-xs text-slate-500">
            Columns: name,email,phone,address,tax_number. Maximum 10,000 rows
            and 5 MB. Existing name/email pairs are skipped.
        </p>
        <form
            @submit.prevent="form.post(`/contacts/${kind}/import`)"
            class="flex flex-wrap items-center gap-3"
        >
            <input
                type="file"
                accept=".csv,.txt"
                required
                @change="choose"
                aria-label="Contact import CSV"
                class="text-sm"
            /><button class="btn-secondary" :disabled="form.processing">
                Import contacts
            </button>
            <p v-if="form.errors.file" class="text-xs text-rose-600">
                {{ form.errors.file }}
            </p>
        </form>
    </details>
</template>
