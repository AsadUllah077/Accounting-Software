<script setup lang="ts">
import { router, usePage } from "@inertiajs/vue3";
import type { PageProps } from "@/types";
const page = usePage<PageProps>();
function switchCompany(event: Event) {
    const id = (event.target as HTMLSelectElement).value;
    if (id) router.post(`/companies/${id}/switch`);
}
</script>
<template>
    <select
        aria-label="Active company"
        :value="page.props.company?.id ?? ''"
        @change="switchCompany"
        class="max-w-56 rounded-lg border-slate-200 py-2 text-sm font-medium"
    >
        <option value="" disabled>Select company</option>
        <option
            v-for="company in page.props.companies"
            :key="company.id"
            :value="company.id"
        >
            {{ company.name }}
        </option>
    </select>
</template>
