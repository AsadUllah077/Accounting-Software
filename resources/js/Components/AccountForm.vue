<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import FormField from "./FormField.vue";
import type { Account } from "@/types/accounting";
const props = defineProps<{ accounts: Account[]; account?: Account }>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const form = useForm({
    code: props.account?.code ?? "",
    name: props.account?.name ?? "",
    type: props.account?.type ?? "expense",
    parent_id: props.account?.parent_id ?? (null as number | null),
    is_active: props.account?.is_active ?? true,
    cash_flow_class: props.account?.cash_flow_class ?? "operating",
});
function submit() {
    const options = {
        onSuccess: () => {
            form.reset();
            emit("saved");
        },
    };
    if (props.account)
        form.put(`/accounting/accounts/${props.account.id}`, options);
    else form.post("/accounting/accounts", options);
}
</script>
<template>
    <form @submit.prevent="submit" class="panel mb-6 space-y-4">
        <h2 class="font-semibold">
            {{ account ? "Edit account" : "New account" }}
        </h2>
        <div class="grid gap-4 md:grid-cols-3">
            <FormField label="Code"
                ><input v-model="form.code" class="field" required /></FormField
            ><FormField label="Name"
                ><input v-model="form.name" class="field" required /></FormField
            ><FormField label="Type"
                ><select v-model="form.type" class="field">
                    <option
                        v-for="type in [
                            'asset',
                            'liability',
                            'equity',
                            'revenue',
                            'expense',
                        ]"
                        :key="type"
                        :value="type"
                    >
                        {{ type }}
                    </option>
                </select></FormField
            ><FormField label="Parent account"
                ><select v-model="form.parent_id" class="field">
                    <option :value="null">None</option>
                    <option
                        v-for="account in accounts.filter(
                            (a) => a.type === form.type && a.id !== account?.id,
                        )"
                        :key="account.id"
                        :value="account.id"
                    >
                        {{ account.code }} · {{ account.name }}
                    </option>
                </select></FormField
            ><FormField label="Cash flow classification"
                ><select v-model="form.cash_flow_class" class="field">
                    <option value="operating">Operating</option>
                    <option value="investing">Investing</option>
                    <option value="financing">Financing</option>
                </select></FormField
            >
        </div>
        <p
            v-for="(error, key) in form.errors"
            :key="key"
            class="text-sm text-rose-600"
        >
            {{ error }}
        </p>
        <label class="flex items-center gap-2 text-sm"
            ><input
                v-model="form.is_active"
                type="checkbox"
                class="rounded border-slate-300 text-teal-600"
            />Active account</label
        >
        <div class="flex gap-3">
            <button class="btn" :disabled="form.processing">
                {{ account ? "Save changes" : "Create account" }}</button
            ><button
                type="button"
                class="btn-secondary"
                @click="emit('cancel')"
            >
                Cancel
            </button>
        </div>
    </form>
</template>
