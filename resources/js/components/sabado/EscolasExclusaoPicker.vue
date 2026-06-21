<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Escola {
    esc_id: number;
    esc_nome: string;
}

const props = defineProps<{ escolas: Escola[]; modelValue: number[] }>();
const emit = defineEmits<{ (e: 'update:modelValue', v: number[]): void }>();

const busca = ref('');
const selecionadas = computed(() => new Set(props.modelValue));

const filtradas = computed(() => {
    const q = busca.value.trim().toLowerCase();
    if (!q) return props.escolas;
    return props.escolas.filter((e) => e.esc_nome.toLowerCase().includes(q));
});

const toggle = (id: number) => {
    const s = new Set(props.modelValue);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    emit('update:modelValue', [...s]);
};
const limpar = () => emit('update:modelValue', []);
</script>

<template>
    <div class="rounded-md border bg-background">
        <div class="flex items-center gap-2 border-b px-2 py-1.5">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-2 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="busca"
                    type="text"
                    placeholder="Filtrar escola..."
                    class="h-8 w-full rounded-md border border-input bg-background pl-7 pr-2 text-xs focus:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                />
            </div>
            <span class="whitespace-nowrap text-[11px] text-muted-foreground">{{ modelValue.length }} sem</span>
            <button v-if="modelValue.length" type="button" class="text-[11px] font-medium text-rose-600 hover:underline" @click="limpar">limpar</button>
        </div>
        <div class="max-h-44 overflow-auto p-1">
            <label
                v-for="e in filtradas"
                :key="e.esc_id"
                class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-xs hover:bg-muted"
            >
                <input
                    type="checkbox"
                    class="size-3.5 shrink-0 rounded border-input accent-indigo-600"
                    :checked="selecionadas.has(e.esc_id)"
                    @change="toggle(e.esc_id)"
                />
                <span class="truncate">{{ e.esc_nome }}</span>
            </label>
            <div v-if="!filtradas.length" class="px-2 py-3 text-center text-xs text-muted-foreground">Nenhuma escola.</div>
        </div>
    </div>
</template>
