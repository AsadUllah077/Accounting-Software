<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import FormField from "@/Components/FormField.vue";
import DataTable from "@/Components/DataTable.vue";
import RolePermissionEditor from "@/Components/RolePermissionEditor.vue";
import { usePermissions } from "@/Composables/usePermissions";
const props = defineProps<{
    profile: any;
    members: any[];
    roles: any[];
    taxRates: any[];
    years: any[];
    invitationUrl?: string;
    allPermissions: { id: number; name: string }[];
    permissionOverrides: any[];
}>();
const { can } = usePermissions();
const profile = useForm({
    name: props.profile.name,
    legal_name: props.profile.legal_name ?? "",
    email: props.profile.email ?? "",
    phone: props.profile.phone ?? "",
    timezone: props.profile.timezone,
    locked_through: props.profile.locked_through?.slice(0, 10) ?? "",
});
const invitation = useForm({
    email: "",
    role_id:
        props.roles.find((role) => role.name === "Read-only User")?.id ?? "",
});
const tax = useForm({ name: "", rate: "0.00", type: "sales" });
const year = useForm({ name: "", start_date: "", end_date: "" });
function changeRole(id: number, event: Event) {
    router.put(`/settings/users/${id}`, {
        role_id: (event.target as HTMLSelectElement).value,
        is_active: true,
    });
}
function closeYear(id: number) {
    if (
        window.confirm(
            "Lock this financial year? New postings in the closed period will be blocked.",
        )
    )
        router.post(`/settings/years/${id}/close`);
}
</script>
<template>
    <Head title="Settings" /><AppLayout
        ><PageHeader
            title="Company settings"
            description="Manage your workspace, people, and accounting periods."
        />
        <div class="grid gap-6 xl:grid-cols-2">
            <form
                @submit.prevent="profile.put('/settings')"
                class="panel space-y-4"
            >
                <h2 class="font-semibold">Company profile</h2>
                <FormField label="Company name" :error="profile.errors.name"
                    ><input
                        v-model="profile.name"
                        class="field"
                        required /></FormField
                ><FormField label="Legal name"
                    ><input
                        v-model="profile.legal_name"
                        class="field" /></FormField
                ><FormField label="Email"
                    ><input
                        v-model="profile.email"
                        type="email"
                        class="field" /></FormField
                ><FormField label="Phone"
                    ><input v-model="profile.phone" class="field" /></FormField
                ><FormField label="Timezone" :error="profile.errors.timezone"
                    ><input
                        v-model="profile.timezone"
                        class="field" /></FormField
                ><FormField
                    label="Lock postings through"
                    :error="profile.errors.locked_through"
                    ><input
                        v-model="profile.locked_through"
                        type="date"
                        class="field"
                /></FormField>
                <p class="text-xs text-slate-400">
                    Base currency: {{ props.profile.currency }}. Lock dates can
                    only advance.
                </p>
                <button class="btn" :disabled="profile.processing">
                    Save settings
                </button>
            </form>
            <section v-if="can('users.manage')" class="panel">
                <h2 class="mb-4 font-semibold">Users & roles</h2>
                <div
                    v-for="member in members"
                    :key="member.id"
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 py-3"
                >
                    <div>
                        <p class="text-sm font-medium">{{ member.name }}</p>
                        <p class="text-xs text-slate-400">{{ member.email }}</p>
                    </div>
                    <select
                        :value="member.pivot.role_id"
                        @change="changeRole(member.id, $event)"
                        class="field !w-40"
                        :aria-label="`Role for ${member.name}`"
                    >
                        <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="role.id"
                        >
                            {{ role.name }}
                        </option>
                    </select>
                </div>
                <form
                    @submit.prevent="invitation.post('/settings/invitations')"
                    class="mt-6 space-y-4"
                >
                    <h3 class="text-sm font-semibold">Invite a team member</h3>
                    <input
                        v-model="invitation.email"
                        type="email"
                        class="field"
                        placeholder="Email address"
                        aria-label="Invitation email"
                        required
                    /><select
                        v-model="invitation.role_id"
                        class="field"
                        aria-label="Invitation role"
                    >
                        <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="role.id"
                        >
                            {{ role.name }}
                        </option>
                    </select>
                    <p
                        v-for="(error, key) in invitation.errors"
                        :key="key"
                        class="text-xs text-rose-600"
                    >
                        {{ error }}
                    </p>
                    <button class="btn" :disabled="invitation.processing">
                        Create invitation
                    </button>
                    <p
                        v-if="invitationUrl"
                        class="break-all rounded-lg bg-teal-50 p-3 text-xs text-teal-800"
                    >
                        Share this link with the invited person:
                        {{ invitationUrl }}
                    </p>
                </form>
            </section>
            <section class="panel">
                <h2 class="mb-4 font-semibold">Tax rates</h2>
                <div
                    v-for="rate in taxRates"
                    :key="rate.id"
                    class="flex justify-between border-b border-slate-100 py-3 text-sm"
                >
                    <span>{{ rate.name }} · {{ rate.type }}</span
                    ><span>{{ rate.rate }}%</span>
                </div>
                <form
                    @submit.prevent="
                        tax.post('/settings/taxes', {
                            onSuccess: () => tax.reset(),
                        })
                    "
                    class="mt-5 space-y-3"
                >
                    <input
                        v-model="tax.name"
                        class="field"
                        placeholder="Tax name"
                        aria-label="Tax name"
                        required
                    /><input
                        v-model="tax.rate"
                        type="number"
                        step="0.0001"
                        min="0"
                        max="100"
                        class="field"
                        aria-label="Tax percentage"
                    /><select
                        v-model="tax.type"
                        class="field"
                        aria-label="Tax type"
                    >
                        <option value="sales">Sales</option>
                        <option value="purchases">Purchases</option>
                    </select>
                    <p
                        v-for="(error, key) in tax.errors"
                        :key="key"
                        class="text-xs text-rose-600"
                    >
                        {{ error }}
                    </p>
                    <button class="btn" :disabled="tax.processing">
                        Add tax rate
                    </button>
                </form>
            </section>
            <section class="panel">
                <h2 class="mb-4 font-semibold">Financial years</h2>
                <div
                    v-for="record in years"
                    :key="record.id"
                    class="mb-3 rounded-lg border border-slate-100 p-3"
                >
                    <div class="flex justify-between text-sm">
                        <span>{{ record.name }}</span
                        ><span v-if="record.is_closed" class="text-slate-400"
                            >Locked</span
                        ><button
                            v-else
                            @click="closeYear(record.id)"
                            class="text-teal-700"
                        >
                            Close & lock
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        {{ record.start_date }} — {{ record.end_date }}
                    </p>
                </div>
                <form
                    @submit.prevent="
                        year.post('/settings/years', {
                            onSuccess: () => year.reset(),
                        })
                    "
                    class="mt-5 space-y-3"
                >
                    <input
                        v-model="year.name"
                        class="field"
                        placeholder="Financial year name"
                        aria-label="Financial year name"
                        required
                    /><input
                        v-model="year.start_date"
                        type="date"
                        class="field"
                        aria-label="Financial year start"
                        required
                    /><input
                        v-model="year.end_date"
                        type="date"
                        class="field"
                        aria-label="Financial year end"
                        required
                    />
                    <p
                        v-for="(error, key) in year.errors"
                        :key="key"
                        class="text-xs text-rose-600"
                    >
                        {{ error }}
                    </p>
                    <button class="btn" :disabled="year.processing">
                        Add financial year
                    </button>
                </form>
            </section>
        </div>
        <RolePermissionEditor
            v-if="can('users.manage')"
            :roles="roles"
            :permissions="allPermissions"
            :overrides="permissionOverrides"
        />
        <div class="mt-6 panel">
            <h2 class="font-semibold">Role permissions</h2>
            <div v-for="role in roles" :key="role.id" class="mt-4">
                <h3 class="text-sm font-medium">{{ role.name }}</h3>
                <p class="mt-1 text-xs leading-6 text-slate-400">
                    {{ role.permissions.map((p: any) => p.name).join(" · ") }}
                </p>
            </div>
        </div>
        <p
            v-for="(error, key) in $page.props.errors"
            :key="key"
            class="mt-4 text-sm text-rose-600"
        >
            {{ error }}
        </p></AppLayout
    >
</template>
