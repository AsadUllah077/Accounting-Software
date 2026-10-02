<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
const props = defineProps<{
    kind: string;
    recordId: number;
    files: { id: number; file_name: string }[];
    canUpload: boolean;
}>();
const form = useForm({ file: null as File | null });
function selected(event: Event) {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}
</script>
<template>
    <section class="panel mt-6">
        <h2 class="font-semibold">Private attachments</h2>
        <a
            v-for="file in files"
            :key="file.id"
            :href="`/attachments/${file.id}`"
            class="mt-3 block text-sm text-teal-700"
            >{{ file.file_name }}</a
        >
        <form
            v-if="canUpload"
            @submit.prevent="
                form.post(`/attachments/${kind}/${recordId}`, {
                    onSuccess: () => form.reset(),
                })
            "
            class="mt-4 flex flex-wrap items-center gap-3"
        >
            <input
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,.webp"
                @change="selected"
                class="text-sm"
                required
                aria-label="Attachment file"
            /><button class="btn-secondary" :disabled="form.processing">
                Upload attachment
            </button>
            <p v-if="form.errors.file" class="text-xs text-rose-600">
                {{ form.errors.file }}
            </p>
        </form>
        <p class="mt-3 text-xs text-slate-400">
            PDF or image, maximum 10 MB. Only members with access to this record
            can download it.
        </p>
    </section>
</template>
