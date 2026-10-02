<script setup lang="ts">
defineProps<{
    label: string;
    error?: string;
    required?: boolean;
    for?: string;
}>();
</script>
<template>
    <div class="space-y-1.5">
        <label
            :for="$props.for"
            class="block text-sm font-medium text-slate-700"
            >{{ label
            }}<span v-if="required" class="ml-1 text-rose-600">*</span></label
        ><slot />
        <p v-if="error" class="text-xs text-rose-600" role="alert">
            {{ error }}
        </p>
    </div>
</template>
