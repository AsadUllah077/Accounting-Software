<script setup lang="ts">
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-slate-50 px-4 py-10 sm:justify-center"
    >
        <div>
            <Link href="/" class="flex items-center gap-3">
                <ApplicationLogo class="h-11 w-11" /><span
                    class="text-3xl font-semibold tracking-tight text-slate-900"
                    >Ledger</span
                >
            </Link>
        </div>

        <div
            class="mt-7 w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white p-7"
        >
            <slot />
        </div>
    </div>
</template>
