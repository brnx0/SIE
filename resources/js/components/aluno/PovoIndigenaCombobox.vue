<script setup lang="ts">
import { usePovosIndigenas } from '@/composables/usePovosIndigenas';
import type { PovoIndigena } from '@/types/aluno';
import { Feather, Loader2, Search, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number | null;
    initial?: PovoIndigena | null;
    placeholder?: string;
    invalid?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const { loading, items, search } = usePovosIndigenas();

const open = ref(false);
const query = ref('');
const selected = ref<PovoIndigena | null>(props.initial ?? null);
const wrapper = ref<HTMLElement | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(query, (q) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    // Lista sempre; query vazia = todos (endpoint retorna os primeiros).
    debounceTimer = setTimeout(() => search(q.trim()), 250);
});

watch(
    () => props.initial,
    (p) => {
        if (p && p.pin_id !== selected.value?.pin_id) {
            selected.value = p;
        }
    },
);

const choose = (p: PovoIndigena) => {
    selected.value = p;
    emit('update:modelValue', p.pin_id);
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

const handleOutside = (e: MouseEvent) => {
    if (wrapper.value && !wrapper.value.contains(e.target as Node)) {
        open.value = false;
    }
};

const openPanel = () => {
    open.value = true;
    search('');   // já lista os registros disponíveis ao abrir
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
                'flex h-10 w-full items-center justify-between rounded-md border bg-background px-3 py-2 text-sm shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500',
                invalid ? 'border-rose-500' : 'border-input',
            ]"
            @click="openPanel"
        >
            <span v-if="selected" class="flex items-center gap-2 truncate">
                <Feather class="size-4 text-fuchsia-600 dark:text-fuchsia-400" />
                <span class="truncate">{{ selected.pin_nome }}</span>
            </span>
            <span v-else class="text-muted-foreground">{{ placeholder ?? 'Buscar povo indígena...' }}</span>

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
            class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-lg"
        >
            <div class="flex items-center border-b px-3">
                <Search class="size-4 text-muted-foreground" />
                <input
                    ref="inputEl"
                    v-model="query"
                    type="text"
                    class="h-10 w-full bg-transparent px-2 text-sm placeholder:text-muted-foreground focus:outline-none"
                    placeholder="Digite o nome ou código..."
                />
                <Loader2 v-if="loading" class="size-4 animate-spin text-muted-foreground" />
            </div>

            <ul class="max-h-64 overflow-y-auto py-1 text-sm">
                <li
                    v-if="!loading && items.length === 0"
                    class="px-3 py-2 text-muted-foreground"
                >
                    Nenhum povo encontrado.
                </li>
                <li
                    v-for="p in items"
                    :key="p.pin_id"
                    class="flex cursor-pointer items-center justify-between px-3 py-2 hover:bg-muted"
                    @mousedown.prevent="choose(p)"
                >
                    <span>{{ p.pin_nome }}</span>
                    <span class="rounded bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground">{{ p.pin_cd_censo }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
