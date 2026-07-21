<script setup lang="ts">
import { useMotoristasTransporte } from '@/composables/useMotoristasTransporte';
import type { MotoristaResumo } from '@/types/transporte';
import { IdCard, Loader2, Search, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number | null;
    initial?: { mot_id: number; mot_nome: string } | null;
    placeholder?: string;
    invalid?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'selected', value: MotoristaResumo | null): void;
}>();

const { loading, items, search } = useMotoristasTransporte();

const open = ref(false);
const query = ref('');
const selected = ref<{ mot_id: number; mot_nome: string } | null>(props.initial ?? null);
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
        if (i && i.mot_id !== selected.value?.mot_id) selected.value = i;
    },
);

const choose = (i: MotoristaResumo) => {
    selected.value = { mot_id: i.mot_id, mot_nome: i.mot_nome };
    emit('update:modelValue', i.mot_id);
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
                <IdCard class="size-4 text-indigo-600 dark:text-indigo-400" />
                <span class="truncate">{{ selected.mot_nome }}</span>
            </span>
            <span v-else class="text-muted-foreground">{{ placeholder ?? 'Buscar motorista...' }}</span>

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
                    placeholder="Nome, CPF ou CNH..."
                />
                <Loader2 v-if="loading" class="size-4 animate-spin text-muted-foreground" />
            </div>

            <ul class="max-h-64 overflow-y-auto py-1 text-sm">
                <li v-if="!loading && items.length === 0" class="px-3 py-2 text-muted-foreground">
                    Nenhum motorista encontrado.
                </li>
                <li
                    v-for="i in items"
                    :key="i.mot_id"
                    class="flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-muted"
                    @mousedown.prevent="choose(i)"
                >
                    <span class="flex-1">{{ i.mot_nome }}</span>
                    <span class="text-xs text-muted-foreground">CNH {{ i.mot_cnh_categoria }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
