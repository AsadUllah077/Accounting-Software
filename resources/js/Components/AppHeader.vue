<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import CompanySwitcher from "./CompanySwitcher.vue";
import type { PageProps } from "@/types";
defineEmits<{ toggle: [] }>();
const page = usePage<PageProps>();
</script>
<template>
    <header
        class="flex h-20 items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 sm:px-9"
    >
        <div class="flex items-center gap-4">
            <Link
                v-if="page.props.auth.user.is_platform_admin"
                href="/admin/plans"
                class="text-xs text-teal-700"
                >Platform plans</Link
            >
            <button
                @click="$emit('toggle')"
                class="rounded p-2 text-slate-500 lg:hidden"
                aria-label="Toggle navigation"
            >
                ☰</button
            ><CompanySwitcher /><span
                class="hidden rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-medium text-emerald-700 sm:inline"
                >● Active workspace</span
            >
        </div>
        <div class="flex items-center gap-4">
            <details class="relative">
                <summary
                    class="flex cursor-pointer list-none items-center gap-2"
                >
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600"
                        >{{ page.props.auth.user.name.slice(0, 1) }}</span
                    ><span
                        class="hidden text-sm font-medium text-slate-700 sm:inline"
                        >{{ page.props.auth.user.name }}</span
                    ><span class="text-xs text-slate-400">⌄</span>
                </summary>
                <div
                    class="absolute right-0 z-20 mt-3 w-44 rounded-lg border border-slate-200 bg-white p-2 shadow-lg"
                >
                    <Link
                        href="/profile"
                        class="block rounded px-3 py-2 text-sm hover:bg-slate-50"
                        >Your profile</Link
                    ><Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="block w-full rounded px-3 py-2 text-left text-sm hover:bg-slate-50"
                        >Sign out</Link
                    >
                </div>
            </details>
        </div>
    </header>
</template>
