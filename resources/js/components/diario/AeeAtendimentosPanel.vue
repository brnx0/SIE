<script setup lang="ts">
import { HandHeart, LoaderCircle } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    turId: number;
}>();

interface Atendimento {
    tat_id: number;
    ate_id: number;
    ate_descricao: string;
}

const atendimentos = ref<Atendimento[]>([]);
const carregando = ref(false);

const fetchAtendimentos = async () => {
    carregando.value = true;
    try {
        const url = new URL('/api/diario-aee/contexto/atendimentos', window.location.origin);
        url.searchParams.set('tur_id', String(props.turId));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        atendimentos.value = r.ok ? await r.json() : [];
    } finally {
        carregando.value = false;
    }
};

onMounted(fetchAtendimentos);
</script>

<template>
    <div class="rounded-xl border bg-card p-4 shadow-sm">
        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
            <HandHeart class="size-4 text-indigo-600" /> Atendimentos ofertados pela turma
        </h3>

        <div v-if="carregando" class="flex items-center gap-2 text-sm text-muted-foreground">
            <LoaderCircle class="size-4 animate-spin" /> Carregando...
        </div>

        <div v-else-if="atendimentos.length" class="flex flex-wrap gap-2">
            <span
                v-for="a in atendimentos"
                :key="a.tat_id"
                class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300"
            >{{ a.ate_descricao }}</span>
        </div>

        <p v-else class="text-sm text-muted-foreground">
            Nenhum atendimento cadastrado para esta turma. Defina no cadastro da turma AEE.
        </p>
    </div>
</template>
