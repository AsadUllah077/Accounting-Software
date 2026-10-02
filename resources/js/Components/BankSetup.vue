<script setup lang="ts">
import { useForm, router } from "@inertiajs/vue3";
import FormField from "./FormField.vue";
import MoneyDisplay from "./MoneyDisplay.vue";
import type { Account } from "@/types/accounting";
const props = defineProps<{
    bankId: number;
    accounts: Account[];
    sessions: any[];
}>();
const bank = useForm({
    name: "",
    bank_name: "",
    account_number: "",
    account_id: props.accounts[0]?.id ?? "",
});
const session = useForm({
    bank_account_id: props.bankId,
    statement_date: new Date().toISOString().slice(0, 10),
    closing_balance: "",
});
function close(id: number) {
    if (window.confirm("Validate and close this statement session?"))
        router.post(`/banking/sessions/${id}/close`);
}
</script>
<template>
    <details class="panel mb-6">
        <summary class="cursor-pointer text-sm font-semibold">
            Bank setup & statement sessions
        </summary>
        <div class="mt-5 grid gap-6 lg:grid-cols-2">
            <form
                @submit.prevent="
                    bank.post('/banking/accounts', {
                        onSuccess: () => bank.reset(),
                    })
                "
                class="space-y-4"
            >
                <h3 class="font-medium">New bank account</h3>
                <FormField label="Display name"
                    ><input
                        v-model="bank.name"
                        class="field"
                        required /></FormField
                ><FormField label="Bank name"
                    ><input v-model="bank.bank_name" class="field" /></FormField
                ><FormField label="Account number"
                    ><input
                        v-model="bank.account_number"
                        class="field" /></FormField
                ><FormField label="Ledger account"
                    ><select v-model="bank.account_id" class="field">
                        <option
                            v-for="account in accounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.code }} · {{ account.name }}
                        </option>
                    </select></FormField
                >
                <p class="text-xs text-slate-400">
                    Create an asset ledger account in the chart of accounts
                    before linking it.
                </p>
                <p
                    v-for="(error, key) in bank.errors"
                    :key="key"
                    class="text-xs text-rose-600"
                >
                    {{ error }}
                </p>
                <button class="btn" :disabled="bank.processing">
                    Create bank account
                </button>
            </form>
            <div>
                <form
                    @submit.prevent="session.post('/banking/sessions')"
                    class="space-y-4"
                >
                    <h3 class="font-medium">Open statement session</h3>
                    <FormField label="Statement date"
                        ><input
                            v-model="session.statement_date"
                            type="date"
                            class="field"
                            required /></FormField
                    ><FormField label="Statement closing balance"
                        ><input
                            v-model="session.closing_balance"
                            type="number"
                            step="0.01"
                            class="field"
                            required
                    /></FormField>
                    <p
                        v-for="(error, key) in session.errors"
                        :key="key"
                        class="text-xs text-rose-600"
                    >
                        {{ error }}
                    </p>
                    <button class="btn" :disabled="session.processing">
                        Open session
                    </button>
                </form>
                <div
                    v-for="record in sessions"
                    :key="record.id"
                    class="mt-4 flex justify-between border-t border-slate-100 pt-3 text-sm"
                >
                    <span
                        >{{ record.statement_date }} ·
                        <MoneyDisplay :amount="record.closing_balance" /></span
                    ><button
                        v-if="record.status === 'open'"
                        @click="close(record.id)"
                        class="text-teal-700"
                    >
                        Validate & close</button
                    ><span v-else class="text-slate-400">Closed</span>
                </div>
            </div>
        </div>
        <p
            v-for="(error, key) in $page.props.errors"
            :key="key"
            class="mt-4 text-xs text-rose-600"
        >
            {{ error }}
        </p>
    </details>
</template>
