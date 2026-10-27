<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';
import { CAMPO } from '@/lib/formCampo';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { FileBarChart, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface Turma { tur_id: number; esc_id: number; anl_id: number; ser_id: number | null; tur_nome: string }
interface Serie { ser_id: number; ser_nome: string }
interface Unidade { uni_id: number; anl_id: number; label: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    unidades: Unidade[];
    turmas: Turma[];
    series: Serie[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Índices de Aprovação e Reprovação', href: '/relatorios/indices-aprovacao' },
];

const anoDefault = props.anosLetivos.find((a) => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

const { restaurar, salvar } = useFiltrosRelatorio('indices-aprovacao', {
    anl_id: null as number | null,
    periodo: null as string | null,
    esc_id: null as number | null,
    ser_id: null as number | null,
    tur_id: null as number | null,
});
const salvos = restaurar();

const anlId = ref<number | null>(salvos.anl_id ?? anoDefault?.anl_id ?? null);
// '' = todos os períodos (cada trimestre + anual); 'anual'; ou o uni_id.
const periodo = ref<string>(salvos.periodo ?? '');
const escId = ref<number | null>(props.isAdmin ? (salvos.esc_id ?? null) : (props.userEscola?.esc_id ?? salvos.esc_id ?? null));
const serId = ref<number | null>(salvos.ser_id ?? null);
const turId = ref<number | null>(salvos.tur_id ?? null);
const gerando = ref(false);

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsSerie = computed(() => props.series.map((s) => ({ id: s.ser_id, label: s.ser_nome })));
const itemsPeriodo = computed(() => [
    { id: '', label: 'Todos (cada trimestre e anual)' },
    ...props.unidades.filter((u) => u.anl_id === anlId.value).map((u) => ({ id: String(u.uni_id), label: u.label })),
    { id: 'anual', label: 'Anual' },
]);

// Turmas de todos os anos/escolas já vieram do servidor — o recorte é local.
const itemsTurma = computed(() =>
    props.turmas
        .filter((t) =>
            (!anlId.value || t.anl_id === anlId.value)
            && (!escId.value || t.esc_id === escId.value)
            && (!serId.value || t.ser_id === serId.value))
        .map((t) => ({ id: t.tur_id, label: t.tur_nome })),
);

// Recorte mudou: turma/período que não pertencem mais a ele voltam ao padrão.
watch([anlId, escId, serId], () => {
    if (turId.value && !itemsTurma.value.some((t) => t.id === turId.value)) turId.value = null;
    if (periodo.value && !itemsPeriodo.value.some((p) => p.id === periodo.value)) periodo.value = '';
});

const escolaTravada = computed(() => !props.isAdmin && !!props.userEscola && props.escolas.length <= 1);

function gerar() {
    if (!anlId.value) return;
    gerando.value = true;
    salvar({ anl_id: anlId.value, periodo: periodo.value || null, esc_id: escId.value, ser_id: serId.value, tur_id: turId.value });
    router.get('/relatorios/indices-aprovacao/gerar', {
        anl_id: Number(anlId.value),
        ...(periodo.value ? { periodo: periodo.value } : {}),
        ...(escId.value ? { esc_id: Number(escId.value) } : {}),
        ...(serId.value ? { ser_id: Number(serId.value) } : {}),
        ...(turId.value ? { tur_id: Number(turId.value) } : {}),
    }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Índices de Aprovação e Reprovação" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <FileBarChart class="size-5 text-indigo-600" /> Índices de Aprovação e Reprovação
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Aprovados, reprovados e pendentes por <strong>trimestre</strong> e no <strong>ano</strong>, a partir das
                notas e conceitos lançados no Diário Online. O percentual considera só os alunos já classificados —
                os pendentes (com nota faltando) ficam fora do cálculo.
            </p>

            <div class="grid items-start gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Selecione o ano..." />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Período</FormLabel>
                    <select v-model="periodo" :class="CAMPO">
                        <option v-for="p in itemsPeriodo" :key="p.id" :value="p.id">{{ p.label }}</option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Escola</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" :disabled="escolaTravada" placeholder="Toda a rede" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Ano de Escolarização / Série</FormLabel>
                    <LocalCombobox v-model="serId" :items="itemsSerie" placeholder="Todas as séries" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Turma</FormLabel>
                    <LocalCombobox v-model="turId" :items="itemsTurma" placeholder="Todas as turmas" />
                    <p v-if="escId || serId" class="text-xs text-muted-foreground">Mostrando apenas as turmas do recorte selecionado.</p>
                </div>

                <div class="flex justify-end sm:col-span-2">
                    <Button :disabled="!anlId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
