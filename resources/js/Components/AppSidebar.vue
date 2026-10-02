<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/Composables/usePermissions";
defineProps<{ open: boolean }>();
defineEmits<{ close: [] }>();
const page = usePage();
const { can } = usePermissions();
const groups = [
    {
        label: "Workspace",
        items: [
            {
                name: "Overview",
                href: "/dashboard",
                icon: "◈",
                permission: "report.view",
            },
        ],
    },
    {
        label: "Sales",
        items: [
            {
                name: "Invoices",
                href: "/documents/invoices",
                icon: "▤",
                permission: "invoice.view",
            },
            {
                name: "Customers",
                href: "/contacts/customers",
                icon: "♧",
                permission: "contact.view",
            },
            {
                name: "Credit notes",
                href: "/credit-notes",
                icon: "↶",
                permission: "invoice.view",
            },
        ],
    },
    {
        label: "Purchases",
        items: [
            {
                name: "Bills",
                href: "/documents/bills",
                icon: "▤",
                permission: "bill.view",
            },
            {
                name: "Suppliers",
                href: "/contacts/suppliers",
                icon: "♧",
                permission: "contact.view",
            },
            {
                name: "Expenses",
                href: "/expenses",
                icon: "↗",
                permission: "expense.view",
            },
        ],
    },
    {
        label: "Accounting",
        items: [
            {
                name: "Chart of accounts",
                href: "/accounting/accounts",
                icon: "▦",
                permission: "accounting.view",
            },
            {
                name: "Manual journals",
                href: "/accounting/journals",
                icon: "≡",
                permission: "accounting.view",
            },
            {
                name: "Banking",
                href: "/banking",
                icon: "▥",
                permission: "banking.view",
            },
        ],
    },
    {
        label: "Settings",
        items: [
            {
                name: "Company settings",
                href: "/settings",
                icon: "⚙",
                permission: "settings.manage",
            },
            {
                name: "Subscription",
                href: "/subscription",
                icon: "◇",
                permission: "settings.manage",
            },
            {
                name: "Audit trail",
                href: "/audit",
                icon: "≡",
                permission: "settings.manage",
            },
        ],
    },
    {
        label: "Reports",
        items: [
            {
                name: "All reports",
                href: "/reports",
                icon: "▦",
                permission: "report.view",
            },
            {
                name: "Profit and loss",
                href: "/reports/profit-and-loss",
                icon: "↗",
                permission: "report.view",
            },
            {
                name: "Balance sheet",
                href: "/reports/balance-sheet",
                icon: "▦",
                permission: "report.view",
            },
            {
                name: "Trial balance",
                href: "/reports/trial-balance",
                icon: "≡",
                permission: "report.view",
            },
            {
                name: "General ledger",
                href: "/reports/general-ledger",
                icon: "▤",
                permission: "report.view",
            },
        ],
    },
];
</script>
<template>
    <div
        v-if="open"
        class="fixed inset-0 z-30 bg-slate-900/30 lg:hidden"
        @click="$emit('close')"
    />
    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
    >
        <Link href="/dashboard" class="flex h-20 items-center gap-3 px-7"
            ><span
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-700 text-xl font-semibold text-white"
                >L</span
            ><span class="text-xl font-semibold tracking-tight text-slate-900"
                >ledger<span class="text-teal-600">.</span></span
            ><span
                class="ml-auto rounded border border-slate-200 px-1.5 py-0.5 text-[10px] font-semibold text-slate-400"
                >WORKSPACE</span
            ></Link
        >
        <nav class="flex-1 overflow-y-auto px-4 pb-6">
            <div v-for="group in groups" :key="group.label" class="mt-4">
                <p
                    v-if="group.items.some((item) => can(item.permission))"
                    class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-widest text-slate-400"
                >
                    {{ group.label }}
                </p>
                <template v-for="item in group.items" :key="item.href"
                    ><Link
                        v-if="can(item.permission)"
                        :href="item.href"
                        @click="$emit('close')"
                        class="mb-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[13px] font-medium"
                        :class="
                            page.url.split('?')[0] === item.href
                                ? 'bg-teal-50 text-teal-800'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'
                        "
                        ><span
                            class="w-5 text-center text-base"
                            aria-hidden="true"
                            >{{ item.icon }}</span
                        >{{ item.name }}</Link
                    ></template
                >
            </div>
        </nav>
        <div class="border-t border-slate-100 p-5">
            <Link
                href="/companies"
                class="text-sm text-slate-500 hover:text-teal-700"
                >Manage companies</Link
            >
            <p class="mt-2 text-xs text-slate-400">
                Your business, in balance.
            </p>
        </div>
    </aside>
</template>
