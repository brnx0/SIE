<script setup lang="ts">
import { useMunicipios } from '@/composables/useMunicipios';
import type { Municipio } from '@/types/aluno';
import { Loader2, MapPin, Search, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number | null;
    initial?: Municipio | null;
    placeholder?: string;
    invalid?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const { loading, items, search } = useMunicipios();

const open = ref(false);
const query = ref('');
const selected = ref<Municipio | null>(props.initial ?? null);
const wrapper = ref<HTMLElement | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(query, (q) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        if (q.trim().length >= 2) {
            search(q.trim());
        } else {
            items.value = [];
        }
    }, 300);
});

watch(
    () => props.initial,
    (m) => {
        if (m && m.mun_id !== selected.value?.mun_id) {
            selected.value = m;
        }
    },
);

const choose = (m: Municipio) => {
    selected.value = m;
    emit('update:modelValue', m.mun_id);
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
                <MapPin class="size-4 text-sky-600 dark:text-sky-400" />
                <span class="truncate">{{ selected.mun_nome }}</span>
                <span class="rounded bg-muted px-1.5 py-0.5 text-xs font-medium">{{ selected.mun_uf }}</span>
            </span>
            <span v-else class="text-muted-foreground">{{ placeholder ?? 'Buscar município...' }}</span>

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
                    placeholder="Digite ao menos 2 letras..."
                />
                <Loader2 v-if="loading" class="size-4 animate-spin text-muted-foreground" />
            </div>

            <ul class="max-h-64 overflow-y-auto py-1 text-sm">
                <li
                    v-if="!loading && query.length < 2"
                    class="px-3 py-2 text-muted-foreground"
                >
                    Comece a digitar o nome da cidade
                </li>
                <li
                    v-else-if="!loading && items.length === 0"
                    class="px-3 py-2 text-muted-foreground"
                >
                    Nenhum município encontrado.
                </li>
                <li
                    v-for="m in items"
                    :key="m.mun_id"
                    class="flex cursor-pointer items-center justify-between px-3 py-2 hover:bg-muted"
                    @mousedown.prevent="choose(m)"
                >
                    <span>{{ m.mun_nome }}</span>
                    <span class="rounded bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground">{{ m.mun_uf }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
