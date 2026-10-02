<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import type { PageProps } from "@/types";
defineProps<{ amount: string | number; currency?: string }>();
const page = usePage<PageProps>();
// Conversion to Number is display-only. All authoritative arithmetic is server-side BCMath.
const format = (amount: string | number, currency?: string) =>
    new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: currency ?? page.props.company?.currency ?? "USD",
    }).format(Number(amount));
</script>
<template>
    <span class="tabular-nums">{{ format(amount, currency) }}</span>
</template>
