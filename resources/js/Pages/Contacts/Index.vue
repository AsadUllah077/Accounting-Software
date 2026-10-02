<script setup lang="ts">
import { ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import DataTable from "@/Components/DataTable.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import FormField from "@/Components/FormField.vue";
import ContactImport from "@/Components/ContactImport.vue";
import { usePermissions } from "@/Composables/usePermissions";
import type { Contact, Paginated } from "@/types/accounting";
const props = defineProps<{
    contacts: Paginated<Contact>;
    kind: string;
    search: string;
}>();
const search = ref(props.search),
    editing = ref<number | null>(null),
    visible = ref(false);
const { can } = usePermissions();
const form = useForm({
    name: "",
    email: "",
    phone: "",
    address: "",
    tax_number: "",
    status: "active",
});
function edit(contact?: Record<string, any>) {
    form.reset();
    form.clearErrors();
    editing.value = contact?.id ?? null;
    if (contact)
        Object.assign(form, {
            name: contact.name,
            email: contact.email ?? "",
            phone: contact.phone ?? "",
            address: contact.address ?? "",
            tax_number: contact.tax_number ?? "",
            status: contact.status,
        });
    visible.value = true;
}
function save() {
    const opts = { onSuccess: () => (visible.value = false) };
    editing.value
        ? form.put(`/contacts/${props.kind}/${editing.value}`, opts)
        : form.post(`/contacts/${props.kind}`, opts);
}
function archive(id: number) {
    if (
        window.confirm(
            "Archive this contact? Its financial history will be preserved.",
        )
    )
        router.delete(`/contacts/${props.kind}/${id}`);
}
</script>
<template>
    <Head :title="kind" /><AppLayout
        ><PageHeader
            :title="kind === 'customers' ? 'Customers' : 'Suppliers'"
            description="Keep your business relationships in one place."
            ><button v-if="can('contact.create')" @click="edit()" class="btn">
                ＋ Add contact</button
            ><a :href="`/export/${kind}`" class="btn-secondary"
                >Export Excel</a
            ></PageHeader
        >
        <ContactImport v-if="can('contact.create')" :kind="kind" />
        <form
            @submit.prevent="router.get(`/contacts/${kind}`, { search })"
            class="mb-5 flex max-w-md gap-2"
        >
            <input
                v-model="search"
                class="field"
                placeholder="Search contacts…"
                aria-label="Search contacts"
            /><button class="btn-secondary">Search</button>
        </form>
        <div v-if="visible" class="panel mb-6">
            <form @submit.prevent="save" class="space-y-4">
                <h2 class="font-semibold">
                    {{ editing ? "Edit contact" : "New contact" }}
                </h2>
                <div class="grid gap-4 md:grid-cols-3">
                    <FormField label="Name" required :error="form.errors.name"
                        ><input
                            v-model="form.name"
                            class="field"
                            required /></FormField
                    ><FormField label="Email" :error="form.errors.email"
                        ><input
                            v-model="form.email"
                            type="email"
                            class="field" /></FormField
                    ><FormField label="Phone" :error="form.errors.phone"
                        ><input v-model="form.phone" class="field" /></FormField
                    ><FormField label="Address" :error="form.errors.address">
                        <textarea
                            v-model="form.address"
                            class="field"
                        /></FormField
                    ><FormField
                        label="Tax number"
                        :error="form.errors.tax_number"
                        ><input
                            v-model="form.tax_number"
                            class="field" /></FormField
                    ><FormField label="Status"
                        ><select v-model="form.status" class="field">
                            <option value="active">Active</option>
                            <option value="archived">Archived</option>
                        </select></FormField
                    >
                </div>
                <div class="flex gap-3">
                    <button class="btn" :disabled="form.processing">
                        {{
                            form.processing ? "Saving…" : "Save contact"
                        }}</button
                    ><button
                        type="button"
                        class="btn-secondary"
                        @click="visible = false"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
        <DataTable
            :rows="contacts.data"
            :links="contacts.links"
            :columns="[
                { key: 'name', label: 'Contact' },
                { key: 'email', label: 'Email' },
                { key: 'phone', label: 'Phone' },
                { key: 'status', label: 'Status' },
                { key: 'actions', label: 'Actions', right: true },
            ]"
            ><template #name="{ row }"
                ><Link
                    :href="`/contacts/${kind}/${row.id}`"
                    class="font-medium text-teal-700"
                    >{{ row.name }}</Link
                ></template
            ><template #status="{ row }"
                ><StatusBadge :status="row.status" /></template
            ><template #actions="{ row }"
                ><button
                    v-if="can('contact.update')"
                    @click="edit(row)"
                    class="mr-4 text-xs text-teal-700"
                >
                    Edit</button
                ><button
                    v-if="can('contact.delete') && row.status === 'active'"
                    @click="archive(row.id)"
                    class="text-xs text-slate-500"
                >
                    Archive
                </button></template
            ></DataTable
        ></AppLayout
    >
</template>
