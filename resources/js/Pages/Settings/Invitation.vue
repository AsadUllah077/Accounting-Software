<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
const props = defineProps<{ token: string }>();
const form = useForm({});
</script>
<template>
    <Head title="Company invitation" />
    <div class="flex min-h-screen items-center justify-center bg-slate-50 p-6">
        <form
            @submit.prevent="form.post(`/invitations/${token}`)"
            class="panel max-w-lg space-y-5"
        >
            <h1 class="text-xl font-semibold">Join your company workspace</h1>
            <p class="text-sm text-slate-500">
                Accept using the email address the invitation was issued to.
            </p>
            <p
                v-for="(error, key) in form.errors"
                :key="key"
                class="text-sm text-rose-600"
            >
                {{ error }}
            </p>
            <button class="btn" :disabled="form.processing">
                Accept invitation</button
            ><Link href="/companies" class="ml-4 text-sm text-slate-500"
                >Cancel</Link
            >
        </form>
    </div>
</template>
