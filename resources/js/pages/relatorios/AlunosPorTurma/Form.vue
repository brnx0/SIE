<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { FileBarChart, Loader2 } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }
interface Turma { tur_id: number; tur_nome: string; tur_situacao: string; tur_modalidade?: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alunos por Turma', href: '/relatorios/alunos-por-turma' },
];

const anlId = ref<number | ''>(props.anosLetivos[0]?.anl_id ?? '');
const escId = ref<number | ''>(props.userEscola?.esc_id ?? '');
const turId = ref<number | ''>('');
const incluirSaidas = ref<boolean>(false);
const turmas = ref<Turma[]>([]);
const loadingTurmas = ref(false);
const gerando = ref(false);

async function loadTurmas() {
    turmas.value = [];
    turId.value = '';
    if (!escId.value || !anlId.value) return;
    loadingTurmas.value = true;
    try {
        const p = new URLSearchParams({ esc_id: String(escId.value), anl_id: String(anlId.value) });
        const r = await fetch(`/api/matriculas/turmas?${p}`);
        if (r.ok) {
            const lista: any[] = await r.json();
            turmas.value = lista
                .filter(t => t.tur_situacao === 'ABERTA')
                .map(t => ({ tur_id: t.tur_id, tur_nome: t.tur_nome, tur_situacao: t.tur_situacao, tur_modalidade: t.tur_modalidade }))
                .filter(t => (t.tur_modalidade ?? 'REGULAR') === 'REGULAR');
        }
    } finally {
        loadingTurmas.value = false;
    }
}

onMounted(loadTurmas);
watch([escId, anlId], loadTurmas);

function gerar() {
    if (!escId.value || !anlId.value) return;
    gerando.value = true;
    const payload: Record<string, any> = {
        anl_id: Number(anlId.value),
        esc_id: Number(escId.value),
        incluir_saidas: incluirSaidas.value ? 1 : 0,
    };
    if (turId.value) payload.tur_id = Number(turId.value);

    router.get('/relatorios/alunos-por-turma/gerar', payload, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Alunos por Turma" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <FileBarChart class="size-5 text-indigo-600" /> Alunos por Turma
            </h1>
            <p class="text-sm text-muted-foreground mb-6">Lista nominal com dados pessoais, situação e contato.</p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model="anlId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">{{ a.anl_ano }}</option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <select v-model="escId" :disabled="!isAdmin && !!userEscola" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="e in escolas" :key="e.esc_id" :value="e.esc_id">{{ e.esc_nome }}</option>
                    </select>
                </div>

                <div class="grid gap-1.5 sm:col-span-2">
                    <FormLabel>Turma (opcional)</FormLabel>
                    <select v-model="turId" :disabled="!turmas.length" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Todas as turmas regulares</option>
                        <option v-for="t in turmas" :key="t.tur_id" :value="t.tur_id">{{ t.tur_nome }}</option>
                    </select>
                    <p v-if="loadingTurmas" class="text-xs text-muted-foreground">Carregando turmas...</p>
                </div>

                <label class="flex items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" v-model="incluirSaidas" class="size-4 accent-indigo-600" />
                    Incluir alunos com saída (transferidos, evadidos, etc.)
                </label>

                <div class="sm:col-span-2 flex justify-end">
                    <Button :disabled="!escId || !anlId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
