<script setup lang="ts">
import MoneyDisplay from "./MoneyDisplay.vue";
defineProps<{
    label: string;
    amount: string;
    caption: string;
    accent?: boolean;
    icon: string;
}>();
</script>
<template>
    <div
        class="rounded-xl border p-5"
        :class="
            accent
                ? 'border-teal-700 bg-teal-700 text-white'
                : 'border-slate-200 bg-white'
        "
    >
        <div class="flex items-center justify-between">
            <p
                class="text-sm"
                :class="accent ? 'text-teal-100' : 'text-slate-500'"
            >
                {{ label }}
            </p>
            <span
                class="flex h-8 w-8 items-center justify-center rounded-lg text-lg"
                :class="accent ? 'bg-white/10' : 'bg-slate-50 text-teal-700'"
                >{{ icon }}</span
            >
        </div>
        <p class="mt-4 text-3xl font-semibold tracking-tight">
            <MoneyDisplay :amount="amount" />
        </p>
        <p
            class="mt-3 text-xs"
            :class="accent ? 'text-teal-100' : 'text-slate-400'"
        >
            {{ caption }}
        </p>
    </div>
</template>
