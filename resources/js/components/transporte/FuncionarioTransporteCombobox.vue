<script setup lang="ts">
import { useFuncionariosTransporte, type PapelTransporte } from '@/composables/useFuncionariosTransporte';
import type { FuncionarioTransporte } from '@/types/transporte';
import { IdCard, Loader2, Search, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number | null;
    papel: PapelTransporte;
    initial?: { fun_id: number; fun_nome: string } | null;
    placeholder?: string;
    invalid?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'selected', value: FuncionarioTransporte | null): void;
}>();

const { loading, items, search } = useFuncionariosTransporte(props.papel);

const open = ref(false);
const query = ref('');
const selected = ref<{ fun_id: number; fun_nome: string } | null>(props.initial ?? null);
const wrapper = ref<HTMLElement | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);

const vazio = props.papel === 'motoristas' ? 'Nenhum motorista encontrado.' : 'Nenhum monitor encontrado.';

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
        if (i && i.fun_id !== selected.value?.fun_id) selected.value = i;
    },
);

const choose = (i: FuncionarioTransporte) => {
    selected.value = { fun_id: i.fun_id, fun_nome: i.fun_nome };
    emit('update:modelValue', i.fun_id);
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
                <span class="truncate">{{ selected.fun_nome }}</span>
            </span>
            <span v-else class="text-muted-foreground">{{ placeholder ?? 'Buscar funcionário...' }}</span>

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
                    placeholder="Nome ou CPF..."
                />
                <Loader2 v-if="loading" class="size-4 animate-spin text-muted-foreground" />
            </div>

            <ul class="max-h-64 overflow-y-auto py-1 text-sm">
                <li v-if="!loading && items.length === 0" class="px-3 py-2 text-muted-foreground">
                    {{ vazio }}
                </li>
                <li
                    v-for="i in items"
                    :key="i.fun_id"
                    class="flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-muted"
                    @mousedown.prevent="choose(i)"
                >
                    <span class="flex-1">{{ i.fun_nome }}</span>
                    <span v-if="papel === 'motoristas' && i.fun_cnh_categoria" class="text-xs text-muted-foreground">
                        CNH {{ i.fun_cnh_categoria }}
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
