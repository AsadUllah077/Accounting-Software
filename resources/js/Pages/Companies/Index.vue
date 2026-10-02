<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import FormField from "@/Components/FormField.vue";
import type { Company } from "@/types/accounting";
defineProps<{
    companies: Company[];
    currencies: { code: string; name: string }[];
}>();
const form = useForm({
    name: "",
    currency: "USD",
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    financial_year_start_date: `${new Date().getFullYear()}-01-01`,
});
</script>
<template>
    <Head title="Your companies" />
    <div class="min-h-screen bg-slate-50 p-6">
        <div class="mx-auto max-w-3xl py-12">
            <h1 class="text-3xl font-semibold text-slate-900">
                Your companies
            </h1>
            <p class="mt-2 text-slate-500">
                Choose a workspace or create a new company.
            </p>
            <div class="my-8 grid gap-3">
                <Link
                    v-for="company in companies"
                    :key="company.id"
                    :href="`/companies/${company.id}/switch`"
                    method="post"
                    as="button"
                    class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 text-left"
                    ><span class="font-medium">{{ company.name }}</span
                    ><span class="text-teal-700">Open workspace →</span></Link
                >
            </div>
            <form
                @submit.prevent="form.post('/companies')"
                class="space-y-5 rounded-xl border border-slate-200 bg-white p-7"
            >
                <h2 class="text-lg font-semibold">Create a company</h2>
                <FormField
                    label="Company name"
                    required
                    :error="form.errors.name"
                    ><input v-model="form.name" class="field" required
                /></FormField>
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField
                        label="Base currency"
                        :error="form.errors.currency"
                        ><select v-model="form.currency" class="field">
                            <option
                                v-for="currency in currencies"
                                :key="currency.code"
                                :value="currency.code"
                            >
                                {{ currency.code }} · {{ currency.name }}
                            </option>
                        </select></FormField
                    ><FormField label="Timezone" :error="form.errors.timezone"
                        ><input v-model="form.timezone" class="field"
                    /></FormField>
                </div>
                <FormField
                    label="Financial year begins"
                    :error="form.errors.financial_year_start_date"
                    ><input
                        v-model="form.financial_year_start_date"
                        type="date"
                        class="field" /></FormField
                ><button class="btn" :disabled="form.processing">
                    {{ form.processing ? "Creating…" : "Create workspace" }}
                </button>
            </form>
            <Link
                href="/logout"
                method="post"
                as="button"
                class="mt-6 text-sm text-slate-500"
                >Sign out</Link
            >
        </div>
    </div>
</template>
