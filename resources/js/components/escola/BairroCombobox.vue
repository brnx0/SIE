<script setup lang="ts">
import { useBairros } from '@/composables/useBairros';
import type { Bairro } from '@/types/escola';
import { Loader2, MapPinned, Plus, Search, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number | null;
    munId: number | null;
    initial?: Bairro | null;
    placeholder?: string;
    invalid?: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const { loading, items, search, create } = useBairros();

const open = ref(false);
const query = ref('');
const selected = ref<Bairro | null>(props.initial ?? null);
const wrapper = ref<HTMLElement | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);
const creating = ref(false);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(query, (q) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        if (q.trim().length >= 1) {
            search(q.trim(), props.munId);
        } else {
            items.value = [];
        }
    }, 300);
});

watch(
    () => props.initial,
    (b) => {
        if (b && b.bai_id !== selected.value?.bai_id) selected.value = b;
    },
);

watch(
    () => props.munId,
    (newMun, oldMun) => {
        if (newMun !== oldMun && selected.value && selected.value.bai_mun_id !== newMun) {
            selected.value = null;
            emit('update:modelValue', null);
        }
        items.value = [];
    },
);

const choose = (b: Bairro) => {
    selected.value = b;
    emit('update:modelValue', b.bai_id);
    open.value = false;
    query.value = '';
    items.value = [];
};

const clear = () => {
    selected.value = null;
    emit('update:modelValue', null);
    query.value = '';
    items.value = [];
};

const createNew = async () => {
    if (!props.munId || !query.value.trim()) return;
    creating.value = true;
    const novo = await create(props.munId, query.value.trim());
    creating.value = false;
    if (novo) choose(novo);
};

const handleOutside = (e: MouseEvent) => {
    if (wrapper.value && !wrapper.value.contains(e.target as Node)) open.value = false;
};

const openPanel = () => {
    if (props.disabled || !props.munId) return;
    open.value = true;
    setTimeout(() => inputEl.value?.focus(), 0);
};

onMounted(() => document.addEventListener('mousedown', handleOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleOutside));
</script>

<template>
    <div ref="wrapper" class="relative">
        <button
            type="button"
            :disabled="disabled || !munId"
            :class="[
                'flex h-10 w-full items-center justify-between rounded-md border bg-background px-3 py-2 text-sm shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 disabled:cursor-not-allowed disabled:opacity-60',
                invalid ? 'border-rose-500' : 'border-input',
            ]"
            @click="openPanel"
        >
            <span v-if="selected" class="flex items-center gap-2 truncate">
                <MapPinned class="size-4 text-sky-600 dark:text-sky-400" />
                <span class="truncate">{{ selected.bai_nome }}</span>
            </span>
            <span v-else-if="!munId" class="text-muted-foreground">Selecione a cidade primeiro</span>
            <span v-else class="text-muted-foreground">{{ placeholder ?? 'Buscar bairro...' }}</span>

            <button v-if="selected" type="button" class="ml-2 rounded p-0.5 hover:bg-muted" @click.stop="clear">
                <X class="size-4" />
            </button>
        </button>

        <div
            v-if="open"
            class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-lg"
        >
            <div class="flex items-center border-b px-3">
                <Search class="size-4 text-muted-foreground" />
                <input
                    ref="inputEl"
                    v-model="query"
                    type="text"
                    class="h-10 w-full bg-transparent px-2 text-sm placeholder:text-muted-foreground focus:outline-none"
                    placeholder="Digite o nome do bairro..."
                />
                <Loader2 v-if="loading" class="size-4 animate-spin text-muted-foreground" />
            </div>

            <ul class="max-h-64 overflow-y-auto py-1 text-sm">
                <li v-if="!loading && query.length < 1" class="px-3 py-2 text-muted-foreground">
                    Comece a digitar
                </li>
                <li
                    v-for="b in items"
                    :key="b.bai_id"
                    class="cursor-pointer px-3 py-2 hover:bg-muted"
                    @mousedown.prevent="choose(b)"
                >
                    {{ b.bai_nome }}
                </li>
                <li
                    v-if="!loading && query.trim().length >= 1 && !items.some((i) => i.bai_nome.toLowerCase() === query.trim().toLowerCase())"
                    class="border-t"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-sm text-sky-600 hover:bg-sky-50 dark:text-sky-400 dark:hover:bg-sky-900/30"
                        :disabled="creating"
                        @mousedown.prevent="createNew"
                    >
                        <Plus class="size-4" />
                        <span>Criar bairro "{{ query.trim() }}"</span>
                        <Loader2 v-if="creating" class="ml-auto size-4 animate-spin" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
