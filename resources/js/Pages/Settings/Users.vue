<script setup lang="ts">
import { ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import CreateCompanyUserForm from "@/Components/CreateCompanyUserForm.vue";
import MemberAccessForm from "@/Components/MemberAccessForm.vue";
import RolePermissionEditor from "@/Components/RolePermissionEditor.vue";
import type { Paginated } from "@/types/accounting";
interface Member {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    pivot: { role_id: number; is_active: boolean | number };
}
const props = defineProps<{
    members: Paginated<Member>;
    roles: {
        id: number;
        name: string;
        permissions: { id: number; name: string }[];
    }[];
    allPermissions: { id: number; name: string }[];
    permissionOverrides: {
        role_id: number;
        permission_id: number;
        allowed: boolean;
    }[];
    invitations: {
        id: number;
        email: string;
        expires_at: string;
        role: { name: string };
    }[];
    invitationUrl?: string;
    search: string;
}>();
const creating = ref(false);
const search = useForm({ search: props.search });
const invitation = useForm({
    email: "",
    role_id:
        props.roles.find((role) => role.name === "Read-only User")?.id ?? "",
});
function revoke(id: number) {
    if (window.confirm("Revoke this invitation? Its link will stop working."))
        router.delete(`/settings/invitations/${id}`, { preserveScroll: true });
}
</script>
<template>
    <Head title="Users & roles" /><AppLayout>
        <PageHeader
            title="Users & roles"
            description="Create accounts and manage access to this company."
            ><button class="btn" @click="creating = !creating">
                New user
            </button></PageHeader
        >
        <CreateCompanyUserForm
            v-if="creating"
            :roles="roles"
            @saved="creating = false"
            @cancel="creating = false"
        />
        <form
            @submit.prevent="
                search.get('/settings/users', { preserveState: true })
            "
            class="mb-5 flex gap-3"
        >
            <input
                v-model="search.search"
                aria-label="Search company users"
                placeholder="Search name or email"
                class="field max-w-sm"
            /><button class="btn-secondary">Search</button>
        </form>
        <DataTable
            :rows="members.data"
            :links="members.links"
            :columns="[
                { key: 'name', label: 'User' },
                { key: 'email', label: 'Email' },
                { key: 'verified', label: 'Verification' },
                { key: 'access', label: 'Company access' },
            ]"
        >
            <template #verified="{ row }"
                ><span
                    :class="
                        row.email_verified_at
                            ? 'text-teal-700'
                            : 'text-amber-700'
                    "
                    >{{
                        row.email_verified_at
                            ? "Verified"
                            : "Awaiting verification"
                    }}</span
                ></template
            >
            <template #access="{ row }"
                ><MemberAccessForm
                    :key="`${row.id}-${row.pivot.role_id}-${row.pivot.is_active}`"
                    :member="row as Member"
                    :roles="roles"
            /></template>
        </DataTable>
        <p class="mt-3 text-xs text-slate-500">
            Deactivation removes company access and preserves financial history.
            A company must retain an active Owner.
        </p>
        <section class="panel mt-6">
            <h2 class="font-semibold">Invite an existing or new user</h2>
            <p class="mt-2 text-sm text-slate-500">
                The recipient registers or logs in with the invited email,
                verifies it, then accepts the invitation link. Invitations
                expire after seven days.
            </p>
            <form
                @submit.prevent="
                    invitation.post('/settings/invitations', {
                        preserveScroll: true,
                        onSuccess: () => invitation.reset('email'),
                    })
                "
                class="mt-5 flex flex-wrap gap-3"
            >
                <input
                    v-model="invitation.email"
                    type="email"
                    aria-label="Invitation email"
                    placeholder="Email address"
                    class="field !w-72"
                    required
                /><select
                    v-model="invitation.role_id"
                    aria-label="Invitation role"
                    class="field !w-48"
                >
                    <option
                        v-for="role in roles"
                        :key="role.id"
                        :value="role.id"
                    >
                        {{ role.name }}
                    </option></select
                ><button
                    :disabled="invitation.processing"
                    class="btn-secondary"
                >
                    Create invitation
                </button>
                <p
                    v-for="(error, key) in invitation.errors"
                    :key="key"
                    class="w-full text-sm text-rose-600"
                >
                    {{ error }}
                </p>
            </form>
            <p
                v-if="invitationUrl"
                class="mt-4 break-all rounded-lg bg-teal-50 p-3 text-sm text-teal-800"
            >
                Share this private invitation link with its recipient:
                <a :href="invitationUrl" class="underline">{{
                    invitationUrl
                }}</a>
            </p>
            <div
                v-for="invite in invitations"
                :key="invite.id"
                class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 text-sm"
            >
                <div>
                    <p>{{ invite.email }} · {{ invite.role.name }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Expires {{ invite.expires_at.slice(0, 10) }}
                    </p>
                </div>
                <button @click="revoke(invite.id)" class="btn-secondary">
                    Revoke invitation
                </button>
            </div>
        </section>
        <RolePermissionEditor
            :roles="roles"
            :permissions="allPermissions"
            :overrides="permissionOverrides"
        />
        <p
            v-for="(error, key) in $page.props.errors"
            :key="key"
            class="mt-3 text-sm text-rose-600"
        >
            {{ error }}
        </p>
    </AppLayout>
</template>
