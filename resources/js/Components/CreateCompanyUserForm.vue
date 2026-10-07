<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { computed } from "vue";
import FormField from "./FormField.vue";
defineProps<{ roles: { id: number; name: string }[] }>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    role_id: "",
});
const subscriptionError = computed(
    () => (form.errors as Record<string, string>).subscription,
);
function submit() {
    form.post("/settings/users", {
        onSuccess: () => {
            form.reset();
            emit("saved");
        },
        onFinish: () => {
            form.reset("password", "password_confirmation");
        },
    });
}
</script>
<template>
    <form @submit.prevent="submit" class="panel mb-6 space-y-5">
        <h2 class="font-semibold">Create user account</h2>
        <p class="text-sm text-slate-500">
            Create a new login and assign its company role. For someone with an
            existing account, use an invitation.
        </p>
        <div class="grid gap-4 sm:grid-cols-2">
            <FormField label="Name" :error="form.errors.name"
                ><input
                    v-model="form.name"
                    aria-label="New user name"
                    class="field"
                    required
                    autocomplete="name"
            /></FormField>
            <FormField label="Email" :error="form.errors.email"
                ><input
                    v-model="form.email"
                    aria-label="New user email"
                    type="email"
                    class="field"
                    required
                    autocomplete="off"
            /></FormField>
            <FormField label="Password" :error="form.errors.password"
                ><input
                    v-model="form.password"
                    aria-label="New user password"
                    type="password"
                    class="field"
                    required
                    minlength="12"
                    autocomplete="new-password"
                />
                <p class="mt-1 text-xs text-slate-500">
                    At least 12 characters, including letters and numbers.
                </p></FormField
            >
            <FormField
                label="Confirm password"
                :error="form.errors.password_confirmation"
                ><input
                    v-model="form.password_confirmation"
                    aria-label="Confirm new user password"
                    type="password"
                    class="field"
                    required
                    autocomplete="new-password"
            /></FormField>
            <FormField label="Company role" :error="form.errors.role_id"
                ><select
                    v-model="form.role_id"
                    aria-label="New user role"
                    class="field"
                    required
                >
                    <option value="" disabled>Select a role</option>
                    <option
                        v-for="role in roles"
                        :key="role.id"
                        :value="role.id"
                    >
                        {{ role.name }}
                    </option>
                </select></FormField
            >
        </div>
        <p v-if="subscriptionError" class="text-sm text-rose-600">
            {{ subscriptionError }}
        </p>
        <p class="text-xs text-slate-500">
            The user receives an email-verification link. In local development,
            email links are written to storage/logs/laravel.log.
        </p>
        <div class="flex gap-3">
            <button class="btn" :disabled="form.processing">Create user</button
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
