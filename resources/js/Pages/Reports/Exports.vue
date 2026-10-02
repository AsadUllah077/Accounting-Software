<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import type { Paginated } from "@/types/accounting";
defineProps<{ exports: Paginated<any> }>();
</script>
<template>
    <Head title="Report exports" /><AppLayout
        ><PageHeader
            title="Report exports"
            description="Private downloads for your requested reports."
            ><button
                @click="router.reload({ only: ['exports'] })"
                class="btn-secondary"
            >
                Refresh status
            </button></PageHeader
        ><DataTable
            :rows="exports.data"
            :links="exports.links"
            :columns="[
                { key: 'report', label: 'Report' },
                { key: 'format', label: 'Format' },
                { key: 'created_at', label: 'Requested' },
                { key: 'status', label: 'Status' },
                { key: 'download', label: 'Download' },
            ]"
            ><template #status="{ row }"
                ><StatusBadge :status="row.status" /></template
            ><template #download="{ row }"
                ><a
                    v-if="row.status === 'ready'"
                    :href="`/exports/${row.id}/download`"
                    class="text-teal-700"
                    >Download</a
                ><span v-else class="text-xs text-slate-400">{{
                    row.error || "Processing"
                }}</span></template
            ></DataTable
        ></AppLayout
    >
</template>
