<script setup lang="ts">
import type { DisciplinaResumo } from '@/types/parametro';
import { BookOpen, Loader2, Search, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number | null;
    items: DisciplinaResumo[];
    initial?: DisciplinaResumo | null;
    loading?: boolean;
    disabledIds?: number[];
    invalid?: boolean;
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'select', d: DisciplinaResumo | null): void;
}>();

const open     = ref(false);
const query    = ref('');
const selected = ref<DisciplinaResumo | null>(props.initial ?? null);
const wrapper  = ref<HTMLElement | null>(null);
const inputEl  = ref<HTMLInputElement | null>(null);

watch(() => props.initial, (v) => { if (v) selected.value = v; });

// Atualiza selected quando modelValue muda externamente
watch(() => props.modelValue, (id) => {
    if (id == null) { selected.value = null; return; }
    if (selected.value?.dis_id === id) return;
    const hit = props.items.find((d) => d.dis_id === id);
    if (hit) selected.value = hit;
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.items;
    return props.items.filter((d) =>
        d.dis_nome.toLowerCase().includes(q)
        || (d.dis_sigla ?? '').toLowerCase().includes(q),
    );
});

const isDisabled = (id: number) => (props.disabledIds ?? []).includes(id);

const choose = (d: DisciplinaResumo) => {
    if (isDisabled(d.dis_id)) return;
    selected.value = d;
    emit('update:modelValue', d.dis_id);
    emit('select', d);
    open.value = false;
    query.value = '';
};

const clear = () => {
    selected.value = null;
    emit('update:modelValue', null);
    emit('select', null);
    query.value = '';
};

const openPanel = () => {
    open.value = true;
    setTimeout(() => inputEl.value?.focus(), 0);
};

const handleOutside = (e: MouseEvent) => {
    if (wrapper.value && !wrapper.value.contains(e.target as Node)) open.value = false;
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
                <span class="truncate">{{ selected.dis_nome }}<template v-if="selected.dis_sigla"> ({{ selected.dis_sigla }})</template></span>
            </span>
            <span v-else class="text-muted-foreground">{{ placeholder ?? 'Selecionar disciplina...' }}</span>

            <button
                v-if="selected"
                type="button"
                class="ml-2 rounded p-0.5 hover:bg-muted"
                @click.stop="clear"
                aria-label="Limpar"
            >
                <X class="size-4" />
            </button>
        </button>

        <div
            v-if="open"
            class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-lg"
        >
            <div class="flex items-center border-b px-3">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <input
                    ref="inputEl"
                    v-model="query"
                    type="text"
                    class="h-10 w-full bg-transparent px-2 text-sm placeholder:text-muted-foreground focus:outline-none"
                    placeholder="Filtrar disciplinas..."
                />
                <Loader2 v-if="loading" class="size-4 animate-spin text-muted-foreground" />
            </div>

            <ul class="max-h-72 overflow-y-auto py-1 text-sm">
                <li
                    v-if="loading && filtered.length === 0"
                    class="px-3 py-2 text-muted-foreground"
                >
                    Carregando...
                </li>
                <li
                    v-else-if="filtered.length === 0"
                    class="px-3 py-2 text-muted-foreground"
                >
                    Nenhuma disciplina encontrada.
                </li>
                <li
                    v-for="d in filtered"
                    :key="d.dis_id"
                    :class="[
                        'flex cursor-pointer items-center justify-between gap-2 px-3 py-2',
                        isDisabled(d.dis_id) ? 'cursor-not-allowed opacity-50' : 'hover:bg-muted',
                        selected?.dis_id === d.dis_id && 'bg-indigo-50 dark:bg-indigo-900/30',
                    ]"
                    @mousedown.prevent="choose(d)"
                >
                    <span class="truncate">
                        {{ d.dis_nome }}
                        <span v-if="d.dis_sigla" class="text-xs text-muted-foreground">({{ d.dis_sigla }})</span>
                    </span>
                    <span v-if="isDisabled(d.dis_id)" class="shrink-0 text-xs text-muted-foreground">já vinculada</span>
                </li>
            </ul>
        </div>
    </div>
</template>
