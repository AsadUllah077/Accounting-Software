<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import FormField from "@/Components/FormField.vue";
import { ref } from "vue";
defineProps<{ plans: any[] }>();
const id = ref<number | null>(null);
const form = useForm({
    name: "",
    description: "",
    price: "0.00",
    currency: "USD",
    interval: "monthly",
    features: { users: 5, invoices_per_month: 100 },
    is_active: true,
});
function edit(plan: any) {
    id.value = plan.id;
    Object.assign(form, {
        name: plan.name,
        description: plan.description ?? "",
        price: plan.price,
        currency: plan.currency,
        interval: plan.interval,
        features: { ...plan.features },
        is_active: plan.is_active,
    });
}
function save() {
    const opts = {
        onSuccess: () => {
            form.reset();
            id.value = null;
        },
    };
    id.value
        ? form.put(`/admin/plans/${id.value}`, opts)
        : form.post("/admin/plans", opts);
}
</script>
<template>
    <Head title="Subscription plans" /><AppLayout
        ><PageHeader
            title="Subscription plans"
            description="Platform administration · price changes apply to new subscriptions."
        />
        <form @submit.prevent="save" class="panel mb-6 space-y-4">
            <div class="grid gap-4 md:grid-cols-3">
                <FormField label="Name"
                    ><input
                        v-model="form.name"
                        class="field"
                        required /></FormField
                ><FormField label="Price"
                    ><input
                        v-model="form.price"
                        type="number"
                        min="0"
                        step="0.01"
                        class="field" /></FormField
                ><FormField label="Currency"
                    ><input v-model="form.currency" class="field" /></FormField
                ><FormField label="Billing interval"
                    ><select v-model="form.interval" class="field">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select></FormField
                ><FormField label="User limit"
                    ><input
                        v-model.number="form.features.users"
                        type="number"
                        min="1"
                        class="field" /></FormField
                ><FormField label="Monthly invoice limit"
                    ><input
                        v-model.number="form.features.invoices_per_month"
                        type="number"
                        min="1"
                        class="field"
                /></FormField>
            </div>
            <label class="text-sm"
                ><input v-model="form.is_active" type="checkbox" /> Available
                for new subscriptions</label
            >
            <p
                v-for="(error, key) in form.errors"
                :key="key"
                class="text-sm text-rose-600"
            >
                {{ error }}
            </p>
            <button class="btn" :disabled="form.processing">
                {{ id ? "Update plan" : "Create plan" }}
            </button>
        </form>
        <DataTable
            :rows="plans"
            :columns="[
                { key: 'name', label: 'Plan' },
                { key: 'price', label: 'Price' },
                { key: 'currency', label: 'Currency' },
                { key: 'interval', label: 'Interval' },
                { key: 'edit', label: 'Action' },
            ]"
            ><template #edit="{ row }"
                ><button @click="edit(row)" class="text-teal-700">
                    Edit
                </button></template
            ></DataTable
        ></AppLayout
    >
</template>
