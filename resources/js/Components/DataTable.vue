<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
defineProps<{
    columns: { key: string; label: string; right?: boolean }[];
    rows: Record<string, any>[];
    links?: { url: string | null; label: string; active: boolean }[];
    empty?: string;
}>();
</script>
<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/70">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="whitespace-nowrap px-5 py-3.5 text-xs font-medium uppercase tracking-wide text-slate-500"
                            :class="{ 'text-right': column.right }"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="hover:bg-slate-50/60"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-5 py-4 text-slate-700"
                            :class="{ 'text-right': column.right }"
                        >
                            <slot :name="column.key" :row="row">{{
                                row[column.key]
                            }}</slot>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td
                            :colspan="columns.length"
                            class="px-6 py-16 text-center text-slate-400"
                        >
                            {{
                                empty ??
                                "No records yet. Create your first record to get started."
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-if="links && links.length > 3"
            class="flex flex-wrap justify-end gap-1 border-t border-slate-100 p-4"
        >
            <template v-for="(link, i) in links" :key="i"
                ><Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md px-3 py-1.5 text-xs"
                    :class="
                        link.active
                            ? 'bg-teal-700 text-white'
                            : 'text-slate-500 hover:bg-slate-100'
                    "
                    v-html="link.label" /><span
                    v-else
                    class="px-3 py-1.5 text-xs text-slate-300"
                    v-html="link.label"
            /></template>
        </div>
    </div>
</template>
