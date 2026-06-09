<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Tipo {
    tmv_cod: number;
    tmv_descricao: string;
    tmv_tas_cod_entrada: number | null;
    tmv_tas_cod_saida: number | null;
}

const props = defineProps<{
    tipos: Tipo[];
    anosLetivos: { anl_id: number; anl_ano: number }[];
    escolas: { esc_id: number; esc_nome: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Movimentações', href: '/movimentacoes' },
    { title: 'Nova', href: '/movimentacoes/create' },
];

const form = useForm({
    tmv_cod: null as number | null,
    tma_id_origem: null as number | null,
    tur_id_destino: null as number | null,
    dt_movimentacao: new Date().toISOString().slice(0, 10),
    protocolo: '',
    observacao: '',
});

const tipoSelecionado = computed(() => props.tipos.find(t => t.tmv_cod === form.tmv_cod) ?? null);
const exigeDestino   = computed(() => !!tipoSelecionado.value?.tmv_tas_cod_entrada);

// ── Cascata Origem ────────────────────────────────────────────────────────────
const anosLetivos    = ref<{ anl_id: number; anl_ano: number }[]>(props.anosLetivos);
const escolas        = ref<{ esc_id: number; esc_nome: string }[]>(props.escolas);
const segmentos      = ref<{ seg_id: number; seg_nome: string }[]>([]);
const series         = ref<{ ser_id: number; ser_nome: string }[]>([]);
const turmas         = ref<{ tur_id: number; tur_nome: string }[]>([]);
const alunos         = ref<{ tma_id: number; aln_id: number; aln_nome: string }[]>([]);

const oAnl = ref<number | ''>('');
const oEsc = ref<number | ''>('');
const oSeg = ref<number | ''>('');
const oSer = ref<number | ''>('');
const oTur = ref<number | ''>('');

async function loadSegmentos(escId: number, anlId: number) {
    const r = await fetch(`/api/segmentos/by-escola?esc_id=${escId}&anl_id=${anlId}`);
    if (r.ok) segmentos.value = await r.json();
}
async function loadSeries(escId: number, anlId: number, segId: number) {
    const r = await fetch(`/api/series/by-turmas-abertas?esc_id=${escId}&anl_id=${anlId}&seg_id=${segId}`);
    if (r.ok) series.value = await r.json();
}
async function loadTurmas(escId: number, anlId: number, segId: number, serId: number) {
    const p = new URLSearchParams({ esc_id: String(escId), anl_id: String(anlId), seg_id: String(segId), ser_id: String(serId) });
    const r = await fetch(`/api/matriculas/turmas?${p}`);
    if (r.ok) turmas.value = (await r.json()).filter((t: any) => t.tur_situacao === 'ABERTA');
}
async function loadAlunos(turId: number) {
    const r = await fetch(`/api/turmas/${turId}/alunos`);
    if (r.ok) alunos.value = await r.json();
}

watch([oEsc, oAnl], async ([e, a]) => {
    segmentos.value = []; series.value = []; turmas.value = []; alunos.value = [];
    oSeg.value = ''; oSer.value = ''; oTur.value = ''; form.tma_id_origem = null;
    if (e && a) await loadSegmentos(+e, +a);
});
watch(oSeg, async (v) => {
    series.value = []; turmas.value = []; alunos.value = [];
    oSer.value = ''; oTur.value = ''; form.tma_id_origem = null;
    if (v && oEsc.value && oAnl.value) await loadSeries(+oEsc.value, +oAnl.value, +v);
});
watch(oSer, async (v) => {
    turmas.value = []; alunos.value = [];
    oTur.value = ''; form.tma_id_origem = null;
    if (v && oEsc.value && oAnl.value && oSeg.value) await loadTurmas(+oEsc.value, +oAnl.value, +oSeg.value, +v);
});
watch(oTur, async (v) => {
    alunos.value = []; form.tma_id_origem = null;
    if (v) await loadAlunos(+v);
});

// ── Cascata Destino ────────────────────────────────────────────────────────────
const segmentosD = ref<{ seg_id: number; seg_nome: string }[]>([]);
const seriesD    = ref<{ ser_id: number; ser_nome: string }[]>([]);
const turmasD    = ref<{ tur_id: number; tur_nome: string }[]>([]);

const dSeg = ref<number | ''>('');
const dSer = ref<number | ''>('');

watch(exigeDestino, async (v) => {
    if (v && oEsc.value && oAnl.value) {
        const r = await fetch(`/api/segmentos/by-escola?esc_id=${oEsc.value}&anl_id=${oAnl.value}`);
        if (r.ok) segmentosD.value = await r.json();
    } else {
        segmentosD.value = []; seriesD.value = []; turmasD.value = [];
        dSeg.value = ''; dSer.value = ''; form.tur_id_destino = null;
    }
});
watch(dSeg, async (v) => {
    seriesD.value = []; turmasD.value = [];
    dSer.value = ''; form.tur_id_destino = null;
    if (v && oEsc.value && oAnl.value) {
        const r = await fetch(`/api/series/by-turmas-abertas?esc_id=${oEsc.value}&anl_id=${oAnl.value}&seg_id=${v}`);
        if (r.ok) seriesD.value = await r.json();
    }
});
watch(dSer, async (v) => {
    turmasD.value = []; form.tur_id_destino = null;
    if (v && oEsc.value && oAnl.value && dSeg.value) {
        const p = new URLSearchParams({ esc_id: String(oEsc.value), anl_id: String(oAnl.value), seg_id: String(dSeg.value), ser_id: String(v) });
        const r = await fetch(`/api/matriculas/turmas?${p}`);
        if (r.ok) {
            const lista = (await r.json()).filter((t: any) => t.tur_situacao === 'ABERTA');
            // Remanejamento: remove a turma de origem da lista destino
            turmasD.value = form.tmv_cod === 5
                ? lista.filter((t: any) => t.tur_id !== Number(oTur.value))
                : lista;
        }
    }
});

function submit() {
    form.post('/movimentacoes', {
        preserveScroll: true,
        onSuccess: () => router.visit('/movimentacoes'),
    });
}
</script>

<template>
    <Head title="Nova Movimentação" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Nova Movimentação</h1>
                <div class="flex gap-2">
                    <Button variant="outline" @click="router.visit('/movimentacoes')">Cancelar</Button>
                    <Button :disabled="form.processing" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="submit">
                        <Loader2 v-if="form.processing" class="mr-1 size-4 animate-spin" /> Salvar
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 rounded-xl border bg-card p-6 shadow-sm">
                <!-- Tipo + Data + Protocolo -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Tipo de Movimentação</FormLabel>
                        <select v-model="form.tmv_cod" class="rounded-md border bg-background px-3 py-2 text-sm">
                            <option :value="null">Selecione...</option>
                            <option v-for="t in tipos" :key="t.tmv_cod" :value="t.tmv_cod">{{ t.tmv_descricao }}</option>
                        </select>
                        <InputError :message="form.errors.tmv_cod" />
                    </div>
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Data</FormLabel>
                        <Input type="date" v-model="form.dt_movimentacao" />
                        <InputError :message="form.errors.dt_movimentacao" />
                    </div>
                    <div class="grid gap-1.5">
                        <FormLabel>Protocolo nº</FormLabel>
                        <Input v-model="form.protocolo" maxlength="50" placeholder="Ex.: 2026/0001" />
                        <InputError :message="form.errors.protocolo" />
                    </div>
                </div>

                <!-- Origem -->
                <div class="grid gap-3 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4">
                    <h3 class="text-sm font-semibold text-indigo-800">Origem</h3>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Ano Letivo</FormLabel>
                            <select v-model="oAnl" class="rounded-md border bg-background px-3 py-2 text-sm">
                                <option value="">—</option>
                                <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">{{ a.anl_ano }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Escola</FormLabel>
                            <select v-model="oEsc" class="rounded-md border bg-background px-3 py-2 text-sm">
                                <option value="">—</option>
                                <option v-for="e in escolas" :key="e.esc_id" :value="e.esc_id">{{ e.esc_nome }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Segmento</FormLabel>
                            <select v-model="oSeg" class="rounded-md border bg-background px-3 py-2 text-sm" :disabled="!segmentos.length">
                                <option value="">—</option>
                                <option v-for="s in segmentos" :key="s.seg_id" :value="s.seg_id">{{ s.seg_nome }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Série</FormLabel>
                            <select v-model="oSer" class="rounded-md border bg-background px-3 py-2 text-sm" :disabled="!series.length">
                                <option value="">—</option>
                                <option v-for="s in series" :key="s.ser_id" :value="s.ser_id">{{ s.ser_nome }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Turma</FormLabel>
                            <select v-model="oTur" class="rounded-md border bg-background px-3 py-2 text-sm" :disabled="!turmas.length">
                                <option value="">—</option>
                                <option v-for="t in turmas" :key="t.tur_id" :value="t.tur_id">{{ t.tur_nome }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Aluno</FormLabel>
                            <select v-model="form.tma_id_origem" class="rounded-md border bg-background px-3 py-2 text-sm" :disabled="!alunos.length">
                                <option :value="null">—</option>
                                <option v-for="a in alunos" :key="a.tma_id" :value="a.tma_id">{{ a.aln_nome }}</option>
                            </select>
                            <InputError :message="form.errors.tma_id_origem" />
                        </div>
                    </div>
                </div>

                <!-- Destino -->
                <div v-if="exigeDestino" class="grid gap-3 rounded-lg border border-fuchsia-200 bg-fuchsia-50/40 p-4">
                    <h3 class="text-sm font-semibold text-fuchsia-800">Destino (mesma escola)</h3>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Segmento</FormLabel>
                            <select v-model="dSeg" class="rounded-md border bg-background px-3 py-2 text-sm" :disabled="!segmentosD.length">
                                <option value="">—</option>
                                <option v-for="s in segmentosD" :key="s.seg_id" :value="s.seg_id">{{ s.seg_nome }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Série</FormLabel>
                            <select v-model="dSer" class="rounded-md border bg-background px-3 py-2 text-sm" :disabled="!seriesD.length">
                                <option value="">—</option>
                                <option v-for="s in seriesD" :key="s.ser_id" :value="s.ser_id">{{ s.ser_nome }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Turma</FormLabel>
                            <select v-model="form.tur_id_destino" class="rounded-md border bg-background px-3 py-2 text-sm" :disabled="!turmasD.length">
                                <option :value="null">—</option>
                                <option v-for="t in turmasD" :key="t.tur_id" :value="t.tur_id">{{ t.tur_nome }}</option>
                            </select>
                            <InputError :message="form.errors.tur_id_destino" />
                        </div>
                    </div>
                </div>

                <!-- Observação -->
                <div class="grid gap-1.5">
                    <FormLabel>Observação</FormLabel>
                    <textarea v-model="form.observacao" rows="3" maxlength="1000" class="rounded-md border bg-background px-3 py-2 text-sm resize-none" />
                    <InputError :message="form.errors.observacao" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
