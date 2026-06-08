<script setup lang="ts">
import { useSeries } from '@/composables/useSeries';
import type { SerieResumo } from '@/types/serie';
import { BookOpen, Loader2, Search, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number | null;
    initial?: SerieResumo | null;
    exclude?: number | null;
    segId?: number | null;
    placeholder?: string;
    invalid?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'select', serie: SerieResumo | null): void;
}>();

const { loading, items, search } = useSeries();

const open = ref(false);
const dropUp = ref(false);
const query = ref('');
const selected = ref<SerieResumo | null>(props.initial ?? null);
const wrapper = ref<HTMLElement | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(query, (q) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    const trimmed = q.trim();
    if (!trimmed) {
        items.value = [];
        return;
    }
    debounceTimer = setTimeout(() => {
        search(trimmed, props.exclude, props.segId);
    }, 200);
});

watch(
    () => props.initial,
    (s) => {
        if (s && s.ser_id !== selected.value?.ser_id) {
            selected.value = s;
        }
    },
);

const choose = (s: SerieResumo) => {
    selected.value = s;
    emit('update:modelValue', s.ser_id);
    emit('select', s);
    open.value = false;
    query.value = '';
    items.value = [];
};

const clear = () => {
    selected.value = null;
    emit('update:modelValue', null);
    emit('select', null);
    query.value = '';
    items.value = [];
};

const handleOutside = (e: MouseEvent) => {
    if (wrapper.value && !wrapper.value.contains(e.target as Node)) {
        open.value = false;
    }
};

const openPanel = () => {
    if (wrapper.value) {
        const rect = wrapper.value.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        // panel ~ search(40) + list(max 256) + borders; flip up if not enough room below
        dropUp.value = spaceBelow < 320 && rect.top > spaceBelow;
    }
    open.value = true;
    items.value = [];
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
                <BookOpen class="size-4 shrink-0 text-indigo-500 dark:text-indigo-400" />
                <span class="truncate">{{ selected.ser_nome }}</span>
            </span>
            <span v-else class="text-muted-foreground">{{ placeholder ?? 'Buscar série...' }}</span>

            <button
                v-if="selected"
                type="button"
                class="ml-2 rounded p-0.5 hover:bg-muted"
                @click.stop="clear"
            >
                <X class="size-4" />
            </button>
        </button>

        <div
            v-if="open"
            :class="[
                'absolute z-50 w-full overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-lg',
                dropUp ? 'bottom-full mb-1' : 'mt-1',
            ]"
        >
            <div class="flex items-center border-b px-3">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <input
                    ref="inputEl"
                    v-model="query"
                    type="text"
                    class="h-10 w-full bg-transparent px-2 text-sm placeholder:text-muted-foreground focus:outline-none"
                    placeholder="Filtrar..."
                />
                <Loader2 v-if="loading" class="size-4 animate-spin text-muted-foreground" />
            </div>

            <ul class="max-h-64 overflow-y-auto py-1 text-sm">
                <li
                    v-if="!loading && !query.trim()"
                    class="px-3 py-2 text-muted-foreground"
                >
                    Digite para buscar séries...
                </li>
                <li
                    v-else-if="!loading && items.length === 0"
                    class="px-3 py-2 text-muted-foreground"
                >
                    Nenhuma série encontrada.
                </li>
                <li
                    v-for="s in items"
                    :key="s.ser_id"
                    class="flex cursor-pointer items-center px-3 py-2 hover:bg-muted"
                    @mousedown.prevent="choose(s)"
                >
                    {{ s.ser_nome }}
                </li>
            </ul>
        </div>
    </div>
</template>
