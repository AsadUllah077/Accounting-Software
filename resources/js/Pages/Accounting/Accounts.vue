<script setup lang="ts">
import { ref } from "vue";
import { usePermissions } from "@/Composables/usePermissions";
import AccountForm from "@/Components/AccountForm.vue";
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import type { Account } from "@/types/accounting";
defineProps<{ accounts: Account[] }>();
const creating = ref(false);
const editing = ref<Account>();
const { can } = usePermissions();
</script>
<template>
    <Head title="Chart of accounts" /><AppLayout
        ><PageHeader
            title="Chart of accounts"
            description="The structure behind your financial statements."
            ><button
                v-if="can('settings.manage')"
                @click="
                    editing = undefined;
                    creating = !creating;
                "
                class="btn"
            >
                ＋ New account
            </button></PageHeader
        ><AccountForm
            v-if="creating || editing"
            :key="editing?.id ?? 'new'"
            :accounts="accounts"
            :account="editing"
            @saved="
                creating = false;
                editing = undefined;
            "
            @cancel="
                creating = false;
                editing = undefined;
            " /><DataTable
            :rows="accounts"
            :columns="[
                { key: 'code', label: 'Code' },
                { key: 'name', label: 'Account' },
                { key: 'type', label: 'Type' },
                { key: 'is_active', label: 'Status' },
            ]"
            ><template #type="{ row }"
                ><span class="capitalize">{{ row.type }}</span></template
            ><template #name="{ row }"
                ><button
                    v-if="can('settings.manage')"
                    class="text-teal-700"
                    @click="
                        editing = row as Account;
                        creating = false;
                    "
                >
                    {{ row.name }}</button
                ><span v-else>{{ row.name }}</span></template
            ><template #is_active="{ row }"
                ><StatusBadge
                    :status="
                        row.is_active ? 'active' : 'archived'
                    " /></template></DataTable
    ></AppLayout>
</template>
