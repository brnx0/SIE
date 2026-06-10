<script setup lang="ts">
import { ChevronDown, Loader2, Search, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface AlunoOpt { aln_id: number; aln_nome: string }

const props = defineProps<{
    modelValue: number | null;
    alunos: AlunoOpt[];
    loading?: boolean;
    disabled?: boolean;
    placeholder?: string;
    allLabel?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const open = ref(false);
const query = ref('');
const wrapper = ref<HTMLElement | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);
const dropUp = ref(false);

const selected = computed(() =>
    props.modelValue != null ? props.alunos.find(a => a.aln_id === props.modelValue) ?? null : null,
);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.alunos;
    return props.alunos.filter(a => a.aln_nome.toLowerCase().includes(q));
});

const choose = (a: AlunoOpt | null) => {
    emit('update:modelValue', a ? a.aln_id : null);
    open.value = false;
    query.value = '';
};

const clear = (e: Event) => {
    e.stopPropagation();
    emit('update:modelValue', null);
};

const handleOutside = (e: MouseEvent) => {
    if (wrapper.value && !wrapper.value.contains(e.target as Node)) {
        open.value = false;
    }
};

const openPanel = () => {
    if (props.disabled) return;
    if (wrapper.value) {
        const rect = wrapper.value.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        dropUp.value = spaceBelow < 320 && rect.top > spaceBelow;
    }
    open.value = true;
    setTimeout(() => inputEl.value?.focus(), 0);
};

watch(() => props.alunos, () => { query.value = ''; });

onMounted(() => document.addEventListener('mousedown', handleOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleOutside));
</script>

<template>
    <div ref="wrapper" class="relative">
        <button
            type="button"
            :disabled="disabled"
            class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 text-sm shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
            @click="openPanel"
        >
            <span v-if="selected" class="truncate">{{ selected.aln_nome }}</span>
            <span v-else class="truncate text-muted-foreground">{{ placeholder ?? (allLabel ?? 'Todos') }}</span>
            <span class="ml-2 flex items-center gap-1">
                <button v-if="selected" type="button" class="rounded p-0.5 hover:bg-muted" @click="clear">
                    <X class="size-4" />
                </button>
                <ChevronDown class="size-4 text-muted-foreground" />
            </span>
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
                    placeholder="Filtrar aluno..."
                />
                <Loader2 v-if="loading" class="size-4 animate-spin text-muted-foreground" />
            </div>
            <ul class="max-h-64 overflow-y-auto py-1 text-sm">
                <li
                    class="flex cursor-pointer items-center px-3 py-2 text-muted-foreground hover:bg-muted"
                    @mousedown.prevent="choose(null)"
                >
                    {{ allLabel ?? 'Todos' }}
                </li>
                <li
                    v-if="!filtered.length && !loading"
                    class="px-3 py-2 text-muted-foreground"
                >
                    Nenhum aluno encontrado.
                </li>
                <li
                    v-for="a in filtered"
                    :key="a.aln_id"
                    class="flex cursor-pointer items-center px-3 py-2 hover:bg-muted"
                    :class="{ 'bg-muted/60': a.aln_id === modelValue }"
                    @mousedown.prevent="choose(a)"
                >
                    {{ a.aln_nome }}
                </li>
            </ul>
        </div>
    </div>
</template>
