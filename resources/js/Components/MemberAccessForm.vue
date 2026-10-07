<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
const props = defineProps<{
    member: {
        id: number;
        name: string;
        pivot: { role_id: number; is_active: boolean | number };
    };
    roles: { id: number; name: string }[];
}>();
const form = useForm({
    role_id: props.member.pivot.role_id,
    is_active: Boolean(props.member.pivot.is_active),
});
function save() {
    if (
        !form.is_active &&
        props.member.pivot.is_active &&
        !window.confirm(
            `Deactivate ${props.member.name}'s access to this company?`,
        )
    )
        return;
    form.put(`/settings/users/${props.member.id}`, { preserveScroll: true });
}
</script>
<template>
    <form @submit.prevent="save" class="flex flex-wrap items-center gap-3">
        <select
            v-model="form.role_id"
            :aria-label="`Role for ${member.name}`"
            class="field !w-40"
        >
            <option v-for="role in roles" :key="role.id" :value="role.id">
                {{ role.name }}
            </option>
        </select>
        <label class="flex items-center gap-2 text-xs"
            ><input
                v-model="form.is_active"
                type="checkbox"
                :aria-label="`Active access for ${member.name}`"
                class="rounded border-slate-300 text-teal-600"
            />Active</label
        >
        <button :disabled="form.processing" class="btn-secondary">
            Save access
        </button>
        <p
            v-for="(error, key) in form.errors"
            :key="key"
            class="w-full text-xs text-rose-600"
        >
            {{ error }}
        </p>
    </form>
</template>
