<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Brain, Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface Segmento { seg_id: number; seg_nome: string }
interface Serie { ser_id: number; ser_nome: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alunos com Transtorno', href: '/relatorios/alunos-transtorno' },
];

const anoDefault = props.anosLetivos.find(a => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

const anlId = ref<number | ''>(anoDefault?.anl_id ?? '');
const escId = ref<number | ''>(props.userEscola?.esc_id ?? '');
const segId = ref<number | ''>('');
const serId = ref<number | ''>('');
const gerando = ref(false);

const segmentos = ref<Segmento[]>([]);
const series    = ref<Serie[]>([]);

async function carregarSegmentos() {
    segmentos.value = [];
    segId.value = '';
    series.value = [];
    serId.value = '';
    if (!escId.value || !anlId.value) return;
    const r = await fetch(`/api/segmentos/by-escola?esc_id=${escId.value}&anl_id=${anlId.value}`);
    if (r.ok) segmentos.value = await r.json();
}
async function carregarSeries() {
    series.value = [];
    serId.value = '';
    if (!segId.value || !escId.value || !anlId.value) return;
    const r = await fetch(`/api/series/by-escola-segmento?esc_id=${escId.value}&anl_id=${anlId.value}&seg_id=${segId.value}`);
    if (r.ok) series.value = await r.json();
}

watch([escId, anlId], carregarSegmentos, { immediate: true });
watch(segId, carregarSeries);

function gerar() {
    if (!anlId.value) return;
    gerando.value = true;
    const payload: Record<string, any> = { anl_id: Number(anlId.value) };
    if (escId.value) payload.esc_id = Number(escId.value);
    if (segId.value) payload.seg_id = Number(segId.value);
    if (serId.value) payload.ser_id = Number(serId.value);

    router.get('/relatorios/alunos-transtorno/gerar', payload, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Alunos com Transtorno" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <Brain class="size-5 text-indigo-600" /> Alunos com Transtorno
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Lista alunos com indicadores TEA, TDAH, Dislexia, Disgrafia, Discalculia, Dislalia e TPAC.
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
