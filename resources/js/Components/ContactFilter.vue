<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import axios from "axios";
import { Check, ChevronDown, LoaderCircle } from "lucide-vue-next";

type Contact = { id: number; name: string };
const props = defineProps<{ modelValue: Contact | null; endpoint: string; label: string }>();
const emit = defineEmits<{ "update:modelValue": [value: Contact | null] }>();
const root = ref<HTMLElement>();
const searchInput = ref<HTMLInputElement>();
const open = ref(false);
const search = ref("");
const options = ref<Contact[]>([]);
const next = ref<number | null>(null);
const loading = ref(false);
const failed = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;
let version = 0;

async function load(more = false) {
    controller?.abort();
    controller = new AbortController();
    const requestVersion = ++version;
    loading.value = true;
    failed.value = false;
    try {
        const { data } = await axios.get<{ data: Contact[]; next: number | null }>(props.endpoint, {
            params: { search: search.value, after: more ? next.value : undefined },
            signal: controller.signal,
        });
        if (requestVersion !== version) return;
        options.value = more ? [...options.value, ...data.data] : data.data;
        next.value = data.next;
    } catch (error) {
        if (requestVersion === version && !axios.isCancel(error)) failed.value = true;
    } finally {
        if (requestVersion === version) loading.value = false;
    }
}

function close() {
    open.value = false;
    clearTimeout(timer);
    controller?.abort();
    ++version;
    loading.value = false;
}

async function toggle() {
    if (open.value) return close();
    open.value = true;
    search.value = "";
    options.value = [];
    next.value = null;
    clearTimeout(timer);
    void load();
    await nextTick();
    searchInput.value?.focus();
}

function select(contact: Contact | null) {
    emit("update:modelValue", contact);
    close();
    root.value?.querySelector<HTMLButtonElement>("button")?.focus();
}

watch(search, () => {
    clearTimeout(timer);
    controller?.abort();
    ++version;
    options.value = [];
    next.value = null;
    loading.value = true;
    timer = setTimeout(() => void load(), 300);
}, { flush: "sync" });

function outside(event: PointerEvent) {
    if (!root.value?.contains(event.target as Node)) close();
}
onMounted(() => document.addEventListener("pointerdown", outside));
onBeforeUnmount(() => {
    close();
    document.removeEventListener("pointerdown", outside);
});
</script>

<template>
    <div ref="root" class="relative w-full sm:w-64" @keydown.esc.stop.prevent="close" @focusout="(event) => { if (!root?.contains(event.relatedTarget as Node)) close(); }">
        <button type="button" class="field flex w-full items-center justify-between gap-2 text-left" :aria-label="`Filter ${label}`" :aria-expanded="open" @click="toggle">
            <span class="truncate">{{ modelValue?.name ?? `All ${label}` }}</span>
            <ChevronDown class="h-4 w-4 shrink-0" />
        </button>
        <div v-if="open" class="absolute left-0 top-full z-30 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">
            <div class="p-2">
                <input ref="searchInput" v-model="search" type="search" maxlength="100" class="field w-full" :placeholder="`Search ${label}`" :aria-label="`Search ${label}`" @keydown.enter.prevent="options.length === 1 && select(options[0])" />
            </div>
            <div class="max-h-64 overflow-y-auto">
                <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-gray-100 focus:bg-gray-100" @click="select(null)">
                    All {{ label }}<Check v-if="!modelValue" class="h-4 w-4" />
                </button>
                <button v-for="option in options" :key="option.id" type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-gray-100 focus:bg-gray-100" @click="select(option)">
                    <span class="min-w-0 break-words">{{ option.name }}</span><Check v-if="modelValue?.id === option.id" class="h-4 w-4 shrink-0" />
                </button>
                <div v-if="loading" class="flex justify-center p-3" role="status" aria-label="Loading contacts"><LoaderCircle class="h-4 w-4 animate-spin" /></div>
                <div v-else-if="failed" class="p-3 text-sm text-red-700">Could not load contacts. <button type="button" class="underline" @click="load()">Retry</button></div>
                <div v-else-if="!options.length" class="p-3 text-sm text-gray-500">No matches</div>
                <button v-if="next && !loading && !failed" type="button" class="w-full px-3 py-2 text-sm text-teal-700 hover:bg-gray-100" @click="load(true)">Load more</button>
            </div>
        </div>
    </div>
</template>
