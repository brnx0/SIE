<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Stethoscope, Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }
interface Turma { tur_id: number; tur_nome: string; ser_nome: string | null }
interface Disciplina { dis_id: number; dis_nome: string }
interface Aluno { aln_id: number; aln_nome: string }

const props = defineProps<{ anosLetivos: AnoLetivo[]; escolas: Escola[]; userEscola: Escola | null; isAdmin: boolean }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Avaliações por Diagnóstico', href: '/relatorios/diagnostico' }];

// Mantém os filtros ao voltar do resultado (gerar outro sem refazer tudo).
const { restaurar, salvar } = useFiltrosRelatorio('diagnostico', {
    anl_id: null as number | null,
    esc_id: null as number | null,
    tur_id: null as number | null,
    dis_id: null as number | null,
    aln_id: 0 as number,
});
const salvos = restaurar();
// Enquanto restaura, as cascatas ficam mudas (senão zeram o que foi restaurado).
let restaurando = true;

const anlId = ref<number | null>(salvos.anl_id ?? props.anosLetivos[0]?.anl_id ?? null);
const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null);
const turId = ref<number | null>(null);
const disId = ref<number | null>(null);
const alnId = ref<number>(0); // 0 = todos

const turmas = ref<Turma[]>([]);
const disciplinas = ref<Disciplina[]>([]);
const alunos = ref<Aluno[]>([]);
const carregandoTurmas = ref(false);
const gerando = ref(false);

const getJson = async (url: string) => {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    return r.ok ? await r.json() : [];
};

async function loadTurmas() {
    turmas.value = []; disciplinas.value = []; alunos.value = [];
    turId.value = null; disId.value = null; alnId.value = 0;
    if (!anlId.value || !escId.value) return;
    carregandoTurmas.value = true;
    try {
        turmas.value = await getJson(`/relatorios/diagnostico/turmas?anl_id=${anlId.value}&esc_id=${escId.value}`);
    } finally { carregandoTurmas.value = false; }
}

async function loadDaTurma() {
    disciplinas.value = []; alunos.value = [];
    disId.value = null; alnId.value = 0;
    if (!turId.value) return;
    [disciplinas.value, alunos.value] = await Promise.all([
        getJson(`/relatorios/diagnostico/disciplinas?tur_id=${turId.value}`),
        getJson(`/relatorios/diagnostico/alunos?tur_id=${turId.value}`),
    ]);
}

onMounted(async () => {
    await loadTurmas();
    // Só restaura o que ainda existe nas listas carregadas.
    if (salvos.tur_id && turmas.value.some((t) => t.tur_id === salvos.tur_id)) {
        turId.value = salvos.tur_id;
        await loadDaTurma();
        if (salvos.dis_id && disciplinas.value.some((d) => d.dis_id === salvos.dis_id)) {
            disId.value = salvos.dis_id;
        }
        if (salvos.aln_id && alunos.value.some((a) => a.aln_id === salvos.aln_id)) {
            alnId.value = salvos.aln_id;
        }
    }
    restaurando = false;
});
watch([anlId, escId], () => { if (!restaurando) loadTurmas(); });
watch(turId, () => { if (!restaurando) loadDaTurma(); });

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsTurma = computed(() => turmas.value.map((t) => ({ id: t.tur_id, label: trim(`${t.ser_nome ? t.ser_nome + ' - ' : ''}${t.tur_nome}`) })));
const itemsDisciplina = computed(() => disciplinas.value.map((d) => ({ id: d.dis_id, label: d.dis_nome })));
const itemsAluno = computed(() => [{ id: 0, label: 'Todos os alunos' }, ...alunos.value.map((a) => ({ id: a.aln_id, label: a.aln_nome }))]);

function trim(s: string) { return s.trim(); }

function gerar() {
    if (!anlId.value || !escId.value || !turId.value || !disId.value) return;
    salvar({
        anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value,
        dis_id: disId.value, aln_id: alnId.value,
    });
    gerando.value = true;
    const params: Record<string, number> = { anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value, dis_id: disId.value };
    if (alnId.value) params.aln_id = alnId.value;
    router.get('/relatorios/diagnostico/gerar', params, { preserveScroll: true, onFinish: () => { gerando.value = false; } });
}
</script>

<template>
    <Head title="Avaliações por Diagnóstico" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <Stethoscope class="size-5 text-indigo-600" /> Avaliações por Diagnóstico
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Por turma e disciplina, agrupado por aluno e ordenado por indicador — mostra a opção atribuída em <strong>cada período</strong>. Deixe o aluno em <strong>Todos</strong> para gerar a turma inteira.
            </p>

            <div class="grid items-start gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Selecione o ano..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" placeholder="Selecione a escola..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Turma</FormLabel>
                    <LocalCombobox v-model="turId" :items="itemsTurma" :placeholder="carregandoTurmas ? 'Carregando...' : (itemsTurma.length ? 'Selecione a turma...' : 'Nenhuma turma')" />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Disciplina</FormLabel>
                    <LocalCombobox v-model="disId" :items="itemsDisciplina" :placeholder="!turId ? 'Selecione a turma primeiro' : (itemsDisciplina.length ? 'Selecione a disciplina...' : 'Nenhuma disciplina')" />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Aluno</FormLabel>
                    <LocalCombobox v-model="alnId" :items="itemsAluno" :placeholder="!turId ? 'Selecione a turma primeiro' : 'Todos os alunos'" />
                </div>
                <div class="flex items-end justify-end">
                    <Button :disabled="!anlId || !escId || !turId || !disId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" /> Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
