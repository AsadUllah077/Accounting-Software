<script setup lang="ts">
import { ref } from "vue";
const props = defineProps<{
    from: string;
    to: string;
    years?: {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
    }[];
}>();
const emit = defineEmits<{ apply: [value: { from: string; to: string }] }>();
const from = ref(props.from),
    to = ref(props.to),
    preset = ref("custom");
function date(value: Date) {
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, "0")}-${String(value.getDate()).padStart(2, "0")}`;
}
function change() {
    const today = new Date(),
        month = today.getMonth(),
        year = today.getFullYear();
    let start = new Date(year, month, 1),
        end = today;
    switch (preset.value) {
        case "last-month":
            start = new Date(year, month - 1, 1);
            end = new Date(year, month, 0);
            break;
        case "quarter":
            start = new Date(year, Math.floor(month / 3) * 3, 1);
            break;
        case "year":
            start = new Date(year, 0, 1);
            break;
        case "custom":
            return;
        default:
            if (preset.value.startsWith("fy-")) {
                const fy = props.years?.find(
                    (fy) => String(fy.id) === preset.value.slice(3),
                );
                if (fy) {
                    from.value = fy.start_date;
                    to.value = fy.end_date;
                    return;
                }
            }
    }
    from.value = date(start);
    to.value = date(end);
}
</script>
<template>
    <form
        @submit.prevent="emit('apply', { from, to })"
        class="mb-6 flex flex-wrap items-center gap-3"
    >
        <select
            v-model="preset"
            @change="change"
            class="field !w-auto"
            aria-label="Reporting period"
        >
            <option value="month">This month</option>
            <option value="last-month">Last month</option>
            <option value="quarter">This quarter</option>
            <option value="year">This year</option>
            <option v-for="fy in years" :key="fy.id" :value="`fy-${fy.id}`">
                {{ fy.name }}
            </option>
            <option value="custom">Custom range</option></select
        ><input
            v-model="from"
            type="date"
            aria-label="Start date"
            class="field !w-auto"
        /><span class="text-slate-300">—</span
        ><input
            v-model="to"
            type="date"
            aria-label="End date"
            class="field !w-auto"
        /><button class="btn-secondary">Apply</button>
    </form>
</template>
