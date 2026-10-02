<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import type { Paginated } from "@/types/accounting";
defineProps<{ logs: Paginated<any> }>();
</script>
<template>
    <Head title="Audit trail" /><AppLayout
        ><PageHeader
            title="Audit trail"
            description="An append-only history of activity in this company." /><DataTable
            :rows="logs.data"
            :links="logs.links"
            :columns="[
                { key: 'created_at', label: 'When' },
                { key: 'user_name', label: 'User' },
                { key: 'event', label: 'Event' },
                { key: 'model_type', label: 'Record type' },
                { key: 'model_id', label: 'Record ID' },
            ]"
    /></AppLayout>
</template>
