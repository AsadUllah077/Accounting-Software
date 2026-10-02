<script setup lang="ts">
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
const props = defineProps<{
    roles: any[];
    permissions: { id: number; name: string }[];
    overrides: { role_id: number; permission_id: number; allowed: boolean }[];
}>();
const roleId = ref(
    props.roles.find((role) => role.name === "Read-only User")?.id ??
        props.roles[0]?.id,
);
const selected = ref<number[]>([]);
function load() {
    const role = props.roles.find((r) => r.id === Number(roleId.value));
    selected.value = role?.permissions.map((p: any) => p.id) ?? [];
    for (const item of props.overrides.filter(
        (o) => o.role_id === Number(roleId.value),
    )) {
        if (item.allowed && !selected.value.includes(item.permission_id))
            selected.value.push(item.permission_id);
        if (!item.allowed)
            selected.value = selected.value.filter(
                (id) => id !== item.permission_id,
            );
    }
}
load();
function save() {
    router.put(`/settings/roles/${roleId.value}/permissions`, {
        permissions: selected.value,
    });
}
</script>
<template>
    <section class="panel mt-6">
        <h2 class="font-semibold">Company role permissions</h2>
        <p class="mt-2 text-sm text-slate-500">
            Customize permissions for this company's roles. Other companies keep
            their own settings.
        </p>
        <select
            v-model="roleId"
            @change="load"
            class="field my-5 !w-64"
            aria-label="Role to customize"
        >
            <option
                v-for="role in roles.filter((r) => r.name !== 'Owner')"
                :key="role.id"
                :value="role.id"
            >
                {{ role.name }}
            </option>
        </select>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label
                v-for="permission in permissions"
                :key="permission.id"
                class="flex items-center gap-2 text-xs text-slate-600"
                ><input
                    v-model="selected"
                    type="checkbox"
                    :value="permission.id"
                    class="rounded border-slate-300 text-teal-700 focus:ring-teal-600"
                />{{ permission.name }}</label
            >
        </div>
        <button @click="save" class="btn mt-5">Save company permissions</button>
    </section>
</template>
