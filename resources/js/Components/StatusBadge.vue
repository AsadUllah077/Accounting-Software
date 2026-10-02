<script setup lang="ts">
defineProps<{ status: string }>();
const colors: Record<string, string> = {
    draft: "bg-slate-100 text-slate-600",
    approved: "bg-blue-50 text-blue-700",
    posted: "bg-blue-50 text-blue-700",
    paid: "bg-emerald-50 text-emerald-700",
    partially_paid: "bg-amber-50 text-amber-700",
    overdue: "bg-rose-50 text-rose-700",
    active: "bg-emerald-50 text-emerald-700",
    archived: "bg-slate-100 text-slate-500",
    void: "bg-rose-50 text-rose-700",
};
</script>
<template>
    <span
        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize"
        :class="colors[status] ?? colors.draft"
        >{{ status.replaceAll("_", " ") }}</span
    >
</template>
