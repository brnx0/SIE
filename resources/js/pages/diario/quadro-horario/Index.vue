<script setup lang="ts">
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import TurmaHorariosTab from '@/components/turma/tabs/TurmaHorariosTab.vue';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivoResumo } from '@/types/diario';
import type { DisciplinaResumo, GradeHorarioResumo, Turma, TurmaHorario, TurmaProfessor } from '@/types/turma';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    anosLetivos: AnoLetivoResumo[];
    turma: Turma | null;
    horarios: TurmaHorario[];
    gradeHorarios: GradeHorarioResumo[];
    professores: TurmaProfessor[];
    disciplinas: DisciplinaResumo[];
    filters: { anl_id: number | null; esc_id: number | null; tur_id: number | null };
}>();

const anlInicial = computed<number | null>(() => {
    if (props.filters.anl_id) return props.filters.anl_id;
    if (props.anosLetivos.length === 1) return props.anosLetivos[0].anl_id;
    const exer = props.anosLetivos.find((a) => a.anl_fl_em_exercicio);
    return exer?.anl_id ?? null;
});

const anlId = ref<number | null>(anlInicial.value);
const escId = ref<number | null>(props.filters.esc_id);
const turId = ref<number | null>(props.filters.tur_id);

const escolas = ref<{ esc_id: number; esc_nome: string }[]>([]);
const turmas = ref<{ tur_id: number; tur_nome: string; ser_nome: string | null }[]>([]);

const fetchEscolas = async () => {
    if (!anlId.value) { escolas.value = []; return; }
    const url = new URL('/api/diario/quadro-horario/escolas', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) escolas.value = await r.json();
};

const fetchTurmas = async () => {
    if (!anlId.value || !escId.value) { turmas.value = []; return; }
    const url = new URL('/api/diario/quadro-horario/turmas', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    url.searchParams.set('esc_id', String(escId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) turmas.value = await r.json();
};

const carregarTurma = () => {
    const params: Record<string, string> = {};
    if (anlId.value) params.anl_id = String(anlId.value);
    if (escId.value) params.esc_id = String(escId.value);
    if (turId.value) params.tur_id = String(turId.value);
    router.get('/diario/quadro-horario', params, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

watch(anlId, () => {
    escId.value = null;
    turId.value = null;
    turmas.value = [];
    fetchEscolas();
});
watch(escId, () => {
    turId.value = null;
    fetchTurmas();
});
watch(turId, (v) => {
    if (v) carregarTurma();
});

onMounted(async () => {
    await fetchEscolas();
    if (escId.value) await fetchTurmas();
});

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => escolas.value.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsTurma = computed(() => turmas.value.map((t) => ({
    id: t.tur_id,
    label: t.ser_nome ? `${t.ser_nome} / ${t.tur_nome}` : t.tur_nome,
})));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Quadro de Horário', href: '/diario/quadro-horario' },
];
</script>

<template>
    <Head title="Quadro de Horário" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Quadro de Horário
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Selecione a turma para alocar professor/disciplina por dia e tempo.
                </p>
            </div>

            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <div class="md:col-span-3">
                        <Label>Ano Letivo <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Selecione o ano..." />
                    </div>
                    <div class="md:col-span-5">
                        <Label>Escola <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="escId" :items="itemsEscola" placeholder="Selecione a escola..." />
                    </div>
                    <div class="md:col-span-4">
                        <Label>Turma <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="turId" :items="itemsTurma" placeholder="Selecione a turma..." />
                    </div>
                </div>
            </section>

            <section v-if="turma" class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="mb-4">
                    <h2 class="text-sm font-semibold text-muted-foreground">
                        {{ turma.serie?.ser_nome ? `${turma.serie.ser_nome} / ` : '' }}{{ turma.tur_nome }}
                    </h2>
                    <p class="text-xs text-muted-foreground">{{ turma.escola?.esc_nome }}</p>
                </div>
                <TurmaHorariosTab
                    :turma="turma"
                    :horarios="horarios"
                    :grade-horarios="gradeHorarios"
                    :professores="professores"
                    :disciplinas="disciplinas"
                    :api-base-url="`/diario/quadro-horario/${turma.tur_id}/horarios`"
                />
            </section>

            <div v-else class="rounded-xl border bg-muted/20 p-8 text-center text-sm text-muted-foreground">
                Selecione uma turma para visualizar o quadro de horários.
            </div>
        </div>
    </AppLayout>
</template>
