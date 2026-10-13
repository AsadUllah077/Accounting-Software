<script setup lang="ts">
import MoneyDisplay from "./MoneyDisplay.vue";
import type { Component } from "vue";
defineProps<{
    label: string;
    amount: string;
    caption: string;
    accent?: boolean;
    icon: Component;
}>();
</script>
<template>
    <div
        class="min-w-0 rounded-xl border p-5"
        :class="
            accent
                ? 'border-teal-700 bg-teal-700 text-white'
                : 'border-slate-200 bg-white'
        "
    >
        <div class="flex min-w-0 items-center justify-between gap-3">
            <p
                class="min-w-0 text-sm"
                :class="accent ? 'text-teal-100' : 'text-slate-500'"
            >
                {{ label }}
            </p>
            <span
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                :class="accent ? 'bg-white/10' : 'bg-slate-50 text-teal-700'"
                ><component :is="icon" class="h-4 w-4" stroke-width="2.25"
            /></span>
        </div>
        <p
            class="mt-5 max-w-full break-words text-2xl font-semibold leading-tight tracking-normal"
        >
            <MoneyDisplay :amount="amount" />
        </p>
        <p
            class="mt-4 text-xs"
            :class="accent ? 'text-teal-100' : 'text-slate-400'"
        >
            {{ caption }}
        </p>
    </div>
</template>
