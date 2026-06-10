<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ClipboardList, Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface Segmento { seg_id: number; seg_nome: string }
interface Serie { ser_id: number; ser_nome: string }
interface Turma { tur_id: number; tur_nome: string; tur_situacao: string; serie?: { ser_nome: string } | null }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dados de Alunos por Turma', href: '/relatorios/dados-alunos-turma' },
];

const anoDefault = props.anosLetivos.find(a => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

const anlId = ref<number | ''>(anoDefault?.anl_id ?? '');
const escId = ref<number | ''>(props.userEscola?.esc_id ?? '');
const segId = ref<number | ''>('');
const serId = ref<number | ''>('');
const turId = ref<number | ''>('');
const gerando = ref(false);

const segmentos = ref<Segmento[]>([]);
const series    = ref<Serie[]>([]);
const turmas    = ref<Turma[]>([]);

async function carregarSegmentos() {
    segmentos.value = []; series.value = []; turmas.value = [];
    segId.value = ''; serId.value = ''; turId.value = '';
    if (!escId.value || !anlId.value) return;
    const r = await fetch(`/api/segmentos/by-escola?esc_id=${escId.value}&anl_id=${anlId.value}`);
    if (r.ok) segmentos.value = await r.json();
}
async function carregarSeries() {
    series.value = []; turmas.value = [];
    serId.value = ''; turId.value = '';
    if (!segId.value || !escId.value || !anlId.value) return;
    const r = await fetch(`/api/series/by-escola-segmento?esc_id=${escId.value}&anl_id=${anlId.value}&seg_id=${segId.value}`);
    if (r.ok) series.value = await r.json();
}
async function carregarTurmas() {
    turmas.value = [];
    turId.value = '';
    if (!escId.value || !anlId.value) return;
    const p = new URLSearchParams({
        esc_id: String(escId.value),
        anl_id: String(anlId.value),
    });
    if (segId.value) p.set('seg_id', String(segId.value));
    if (serId.value) p.set('ser_id', String(serId.value));
    const r = await fetch(`/api/matriculas/turmas?${p}`);
    if (r.ok) {
        const lista: any[] = await r.json();
        turmas.value = lista
            .filter(t => t.tur_situacao === 'ABERTA')
            .filter(t => (t.tur_modalidade ?? 'REGULAR') === 'REGULAR');
    }
}

watch([escId, anlId], carregarSegmentos, { immediate: true });
watch(segId, carregarSeries);
watch([segId, serId], carregarTurmas);

function gerar() {
    if (!anlId.value) return;
    gerando.value = true;
    const payload: Record<string, any> = { anl_id: Number(anlId.value) };
    if (escId.value) payload.esc_id = Number(escId.value);
    if (segId.value) payload.seg_id = Number(segId.value);
    if (serId.value) payload.ser_id = Number(serId.value);
    if (turId.value) payload.tur_id = Number(turId.value);

    router.get('/relatorios/dados-alunos-turma/gerar', payload, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Dados de Alunos por Turma" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <ClipboardList class="size-5 text-indigo-600" /> Dados de Alunos por Turma
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Cadastro completo dos alunos matriculados em turmas regulares ativas.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model="anlId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">
                            {{ a.anl_ano }} <span v-if="a.anl_fl_em_exercicio">(em exercício)</span>
                        </option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Escola</FormLabel>
                    <select v-model="escId" :disabled="!isAdmin && !!userEscola" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <option v-for="e in escolas" :key="e.esc_id" :value="e.esc_id">{{ e.esc_nome }}</option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Segmento</FormLabel>
                    <select v-model="segId" :disabled="!segmentos.length" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        <option v-for="s in segmentos" :key="s.seg_id" :value="s.seg_id">{{ s.seg_nome }}</option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Série</FormLabel>
                    <select v-model="serId" :disabled="!series.length" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <option v-for="s in series" :key="s.ser_id" :value="s.ser_id">{{ s.ser_nome }}</option>
                    </select>
                </div>

                <div class="grid gap-1.5 sm:col-span-2">
                    <FormLabel>Turma</FormLabel>
                    <select v-model="turId" :disabled="!turmas.length" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <option v-for="t in turmas" :key="t.tur_id" :value="t.tur_id">
                            {{ t.serie?.ser_nome ? `${t.serie.ser_nome} ${t.tur_nome}` : t.tur_nome }}
                        </option>
                    </select>
                </div>

                <div class="sm:col-span-2 flex justify-end">
                    <Button :disabled="!anlId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
