<script setup lang="ts">
import { ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import AppSidebar from "@/Components/AppSidebar.vue";
import AppHeader from "@/Components/AppHeader.vue";
import type { PageProps } from "@/types";
const open = ref(false);
const page = usePage<PageProps>();
</script>
<template>
    <div class="min-h-screen bg-[#f7f9fb]">
        <AppSidebar :open="open" @close="open = false" />
        <div class="lg:pl-64">
            <AppHeader @toggle="open = !open" />
            <main class="mx-auto max-w-[1600px] px-5 py-8 sm:px-9">
                <div
                    v-if="page.props.flash?.success"
                    role="status"
                    class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ page.props.flash.success }}
                </div>
                <slot />
            </main>
            <footer class="px-9 pb-6 text-xs text-slate-400">
                Ledger · {{ page.props.company?.name }} ·
                {{ page.props.company?.currency }}
            </footer>
        </div>
    </div>
</template>
