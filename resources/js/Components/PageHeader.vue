<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>
<template>
    <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p
                class="mb-2 text-xs font-medium uppercase tracking-widest text-slate-400"
            >
                Workspace / {{ title }}
            </p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-1.5 text-sm text-slate-500">
                {{ description }}
            </p>
        </div>
        <div class="flex gap-3"><slot /></div>
    </div>
</template>
