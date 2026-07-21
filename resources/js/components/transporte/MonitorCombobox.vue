<script setup lang="ts">
import { useMonitoresTransporte } from '@/composables/useMonitoresTransporte';
import type { MonitorResumo } from '@/types/transporte';
import { Loader2, Search, UserRound, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number | null;
    initial?: { tmo_id: number; tmo_nome: string } | null;
    placeholder?: string;
    invalid?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'selected', value: MonitorResumo | null): void;
}>();

const { loading, items, search } = useMonitoresTransporte();

const open = ref(false);
const query = ref('');
const selected = ref<{ tmo_id: number; tmo_nome: string } | null>(props.initial ?? null);
const wrapper = ref<HTMLElement | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(query, (q) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        search(q.trim(), props.modelValue ? [props.modelValue] : []);
    }, 300);
});

watch(
    () => props.initial,
    (i) => {
        if (i && i.tmo_id !== selected.value?.tmo_id) selected.value = i;
    },
);

const choose = (i: MonitorResumo) => {
    selected.value = { tmo_id: i.tmo_id, tmo_nome: i.tmo_nome };
    emit('update:modelValue', i.tmo_id);
    emit('selected', i);
    open.value = false;
    query.value = '';
    items.value = [];
};

const clear = () => {
    selected.value = null;
    emit('update:modelValue', null);
    emit('selected', null);
    query.value = '';
    items.value = [];
};

const handleOutside = (e: MouseEvent) => {
    if (wrapper.value && !wrapper.value.contains(e.target as Node)) open.value = false;
};

const openPanel = () => {
    open.value = true;
    search('', props.modelValue ? [props.modelValue] : []);
    setTimeout(() => inputEl.value?.focus(), 0);
};

onMounted(() => document.addEventListener('mousedown', handleOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleOutside));
</script>

<template>
    <div ref="wrapper" class="relative">
        <button
            type="button"
            :class="[
                'flex h-10 w-full items-center justify-between rounded-md border bg-background px-3 py-2 text-sm shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                invalid ? 'border-rose-500' : 'border-input',
            ]"
            @click="openPanel"
        >
            <span v-if="selected" class="flex items-center gap-2 truncate">
                <UserRound class="size-4 text-indigo-600 dark:text-indigo-400" />
                <span class="truncate">{{ selected.tmo_nome }}</span>
            </span>
            <span v-else class="text-muted-foreground">{{ placeholder ?? 'Buscar monitor (opcional)...' }}</span>

            <button v-if="selected" type="button" class="ml-2 rounded p-0.5 hover:bg-muted" @click.stop="clear">
                <X class="size-4" />
            </button>
        </button>

        <div v-if="open" class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-lg">
            <div class="flex items-center border-b px-3">
                <Search class="size-4 text-muted-foreground" />
                <input
                    ref="inputEl"
                    v-model="query"
                    type="text"
                    class="h-10 w-full bg-transparent px-2 text-sm placeholder:text-muted-foreground focus:outline-none"
                    placeholder="Nome do monitor..."
                />
                <Loader2 v-if="loading" class="size-4 animate-spin text-muted-foreground" />
            </div>

            <ul class="max-h-64 overflow-y-auto py-1 text-sm">
                <li v-if="!loading && items.length === 0" class="px-3 py-2 text-muted-foreground">
                    Nenhum monitor encontrado.
                </li>
                <li
                    v-for="i in items"
                    :key="i.tmo_id"
                    class="flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-muted"
                    @mousedown.prevent="choose(i)"
                >
                    <span class="flex-1">{{ i.tmo_nome }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
