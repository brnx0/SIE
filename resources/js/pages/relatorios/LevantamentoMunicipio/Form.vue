<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';
import { CAMPO } from '@/lib/formCampo';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ClipboardList, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface Segmento { seg_id: number; seg_nome: string }
interface Serie { ser_id: number; ser_nome: string; seg_id: number | null }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    turnos: string[];
    segmentos: Segmento[];
    series: Serie[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Levantamento do Município', href: '/relatorios/levantamento-municipio' },
];

const anoDefault = props.anosLetivos.find((a) => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

const { restaurar, salvar } = useFiltrosRelatorio('levantamento-municipio', {
    anl_id: null as number | null,
    esc_id: null as number | null,
    seg_id: null as number | null,
    ser_id: null as number | null,
    turno: null as string | null,
});
const salvos = restaurar();

const anlId = ref<number | null>(salvos.anl_id ?? anoDefault?.anl_id ?? null);
const escId = ref<number | null>(
    props.isAdmin ? (salvos.esc_id ?? null) : (props.userEscola?.esc_id ?? null),
);
const segId = ref<number | null>(salvos.seg_id ?? null);
const serId = ref<number | null>(salvos.ser_id ?? null);
const turno = ref<string | null>(salvos.turno ?? null);
const gerando = ref(false);

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsSegmento = computed(() => props.segmentos.map((s) => ({ id: s.seg_id, label: s.seg_nome })));
// Série depende do segmento, mas a lista inteira já veio do servidor — o filtro
// é local, sem cascata assíncrona (e sem o risco de restore descrito no CLAUDE.md).
const itemsSerie = computed(() =>
    props.series
        .filter((s) => !segId.value || s.seg_id === segId.value)
        .map((s) => ({ id: s.ser_id, label: s.ser_nome })),
);

// Trocar de segmento invalida a série escolhida se ela não pertencer a ele.
watch(segId, () => {
    if (serId.value && !itemsSerie.value.some((s) => s.id === serId.value)) serId.value = null;
});

const escolaTravada = computed(() => !props.isAdmin && !!props.userEscola);

function limpar() {
    escId.value = escolaTravada.value ? (props.userEscola?.esc_id ?? null) : null;
    segId.value = null;
    serId.value = null;
    turno.value = null;
}

function gerar() {
    if (!anlId.value) return;
    gerando.value = true;
    salvar({ anl_id: anlId.value, esc_id: escId.value, seg_id: segId.value, ser_id: serId.value, turno: turno.value });
    router.get('/relatorios/levantamento-municipio/gerar', {
        anl_id: Number(anlId.value),
        ...(escId.value ? { esc_id: Number(escId.value) } : {}),
        ...(segId.value ? { seg_id: Number(segId.value) } : {}),
        ...(serId.value ? { ser_id: Number(serId.value) } : {}),
        ...(turno.value ? { turno: turno.value } : {}),
    }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Levantamento de Dados do Município" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <ClipboardList class="size-5 text-indigo-600" /> Levantamento de Dados do Município
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Panorama consolidado da rede, com uma linha por turma: identificação, segmento e ano escolar,
                turno, capacidade, matrícula, professores responsáveis e indicadores de diário.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Selecione o ano..." />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Escola</FormLabel>
                    <LocalCombobox
                        v-model="escId"
                        :items="itemsEscola"
                        :disabled="escolaTravada"
                        placeholder="Todas as escolas"
                    />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Turno</FormLabel>
                    <!-- LocalCombobox só aceita id numérico; turno é string com 4 opções fixas. -->
                    <select v-model="turno" :class="CAMPO">
                        <option :value="null">Todos os turnos</option>
                        <option v-for="t in turnos" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Segmento</FormLabel>
                    <LocalCombobox v-model="segId" :items="itemsSegmento" placeholder="Todos os segmentos" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Ano Escolar / Série</FormLabel>
                    <LocalCombobox v-model="serId" :items="itemsSerie" placeholder="Todas as séries" />
                    <p v-if="segId" class="text-xs text-muted-foreground">
                        Mostrando apenas as séries do segmento selecionado.
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>&nbsp;</FormLabel>
                    <Button variant="outline" @click="limpar">Limpar filtros</Button>
                </div>

                <div class="sm:col-span-2 lg:col-span-3 rounded-lg border bg-muted/30 p-3 text-xs text-muted-foreground">
                    <strong class="text-foreground">Sobre a coluna “Freq. lançada”:</strong>
                    é a razão entre presenças e registros de chamada das aulas executadas no ano — um indicador de
                    <em>preenchimento do diário</em> por turma. Não é a frequência oficial do aluno usada para
                    aprovação por falta, que é calculada por aluno (com regra própria por segmento) nos relatórios
                    de Frequência Mensal e no encerramento do ano.
                </div>

                <div class="sm:col-span-2 lg:col-span-3 flex justify-end">
                    <Button :disabled="!anlId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
