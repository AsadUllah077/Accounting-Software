<script setup lang="ts">
import { ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import MoneyDisplay from "@/Components/MoneyDisplay.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import BankSetup from "@/Components/BankSetup.vue";
import type { Account } from "@/types/accounting";
import { usePermissions } from "@/Composables/usePermissions";
import type { Paginated } from "@/types/accounting";
const props = defineProps<{
    banks: { id: number; name: string }[];
    accounts: Account[];
    sessions: any[];
    bank: { id: number; name: string };
    transactions: Paginated<any>;
    reconciled: number[];
    lines: {
        id: number;
        debit: string;
        credit: string;
        entry: { reference: string; date: string };
    }[];
}>();
const { can } = usePermissions();
const show = ref(false),
    matches = ref<Record<number, string>>({});
const form = useForm({
    bank_account_id: props.bank.id,
    file: null as File | null,
});
function file(event: Event) {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}
</script>
<template>
    <Head title="Banking" /><AppLayout
        ><PageHeader
            title="Banking & reconciliation"
            description="Match your statement to posted ledger transactions."
            ><button
                v-if="can('banking.reconcile')"
                @click="show = !show"
                class="btn"
            >
                Import statement
            </button></PageHeader
        ><select
            :value="bank.id"
            @change="
                router.get('/banking', {
                    bank: ($event.target as HTMLSelectElement).value,
                })
            "
            class="field mb-5 !w-64"
            aria-label="Bank account"
        >
            <option
                v-for="account in banks"
                :key="account.id"
                :value="account.id"
            >
                {{ account.name }}
            </option>
        </select>
        <form
            v-if="show"
            @submit.prevent="
                form.post('/banking/import', {
                    onSuccess: () => (show = false),
                })
            "
            class="panel mb-6 space-y-4"
        >
            <h2 class="font-semibold">Import CSV statement</h2>
            <p class="text-sm text-slate-500">
                Required columns: date,description,amount,reference. Use
                YYYY-MM-DD dates and signed amounts: positive receipts, negative
                payments. Give distinct transactions distinct references.
            </p>
            <input
                type="file"
                accept=".csv,.txt"
                @change="file"
                class="text-sm"
                required
            />
            <p
                v-for="(error, key) in form.errors"
                :key="key"
                class="text-sm text-rose-600"
            >
                {{ error }}
            </p>
            <button :disabled="form.processing" class="btn">
                Import transactions
            </button>
        </form>
        <DataTable
            :rows="transactions.data"
            :links="transactions.links"
            :columns="[
                { key: 'date', label: 'Date' },
                { key: 'description', label: 'Description' },
                { key: 'amount', label: 'Statement amount', right: true },
                { key: 'status', label: 'Status' },
                { key: 'match', label: 'Match ledger line' },
            ]"
            ><template #amount="{ row }"
                ><MoneyDisplay :amount="row.amount" /></template
            ><template #status="{ row }"
                ><StatusBadge
                    :status="
                        reconciled.includes(row.id) ? 'paid' : 'draft'
                    " /></template
            ><template #match="{ row }"
                ><div
                    v-if="
                        !reconciled.includes(row.id) && can('banking.reconcile')
                    "
                    class="flex gap-2"
                >
                    <BankSetup
                        v-if="can('settings.manage')"
                        :bank-id="bank.id"
                        :accounts="accounts"
                        :sessions="sessions"
                    />
                    <select
                        v-model="matches[row.id]"
                        class="field min-w-48"
                        aria-label="Matching ledger line"
                    >
                        <option value="">Select ledger line</option>
                        <option
                            v-for="line in lines"
                            :key="line.id"
                            :value="line.id"
                        >
                            {{ line.entry.reference }} ·
                            {{ Number(line.debit) - Number(line.credit) }}
                        </option></select
                    ><button
                        :disabled="!matches[row.id]"
                        @click="
                            router.post(`/banking/${row.id}/match`, {
                                journal_line_id: matches[row.id],
                            })
                        "
                        class="btn"
                    >
                        Match
                    </button>
                </div>
                <span v-else class="text-xs text-slate-400">{{
                    reconciled.includes(row.id) ? "Reconciled" : "—"
                }}</span></template
            ></DataTable
        >
        <p
            v-if="$page.props.errors.reconciliation"
            role="alert"
            class="mt-4 text-sm text-rose-600"
        >
            {{ $page.props.errors.reconciliation }}
        </p></AppLayout
    >
</template>
