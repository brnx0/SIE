<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { NotebookPen, Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Parecer Descritivo', href: '/relatorios/parecer-descritivo' },
];

// Contexto (obrigatórios: ano, escola, turma)
const anlId = ref<number | null>(props.anosLetivos[0]?.anl_id ?? null);
const escId = ref<number | null>(props.userEscola?.esc_id ?? null);
const turId = ref<number | null>(null);
// Opcionais
const disId = ref<number | null>(null);
const alnId = ref<number | null>(null);
const uniId = ref<number | null>(null);

const turmas = ref<{ tur_id: number; tur_nome: string; ser_nome: string | null }[]>([]);
const disciplinas = ref<{ dis_id: number; dis_nome: string }[]>([]);
const alunos = ref<{ aln_id: number; aln_nome: string }[]>([]);
const unidades = ref<{ uni_id: number; uni_numero: number; uni_tipo: string }[]>([]);
const gerando = ref(false);

const capitalize = (s: string) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);

const getJson = async (url: string) => {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    return r.ok ? await r.json() : [];
};

async function loadTurmas() {
    turmas.value = [];
    turId.value = null;
    disId.value = null;
    alnId.value = null;
    disciplinas.value = [];
    alunos.value = [];
    if (!escId.value || !anlId.value) return;
    turmas.value = await getJson(`/relatorios/parecer-descritivo/turmas?esc_id=${escId.value}&anl_id=${anlId.value}`);
}

async function loadUnidades() {
    unidades.value = [];
    uniId.value = null;
    if (!anlId.value) return;
    unidades.value = await getJson(`/relatorios/parecer-descritivo/unidades?anl_id=${anlId.value}`);
}

async function loadTurmaDeps() {
    disId.value = null;
    alnId.value = null;
    disciplinas.value = [];
    alunos.value = [];
    if (!turId.value) return;
    [disciplinas.value, alunos.value] = await Promise.all([
        getJson(`/relatorios/parecer-descritivo/disciplinas?tur_id=${turId.value}`),
        getJson(`/relatorios/parecer-descritivo/alunos?tur_id=${turId.value}`),
    ]);
}

onMounted(() => {
    loadTurmas();
    loadUnidades();
});
watch([escId, anlId], loadTurmas);
watch(anlId, loadUnidades);
watch(turId, loadTurmaDeps);

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsTurma = computed(() => turmas.value.map((t) => ({ id: t.tur_id, label: (t.ser_nome ? t.ser_nome + ' - ' : '') + t.tur_nome })));
const itemsDisciplina = computed(() => disciplinas.value.map((d) => ({ id: d.dis_id, label: d.dis_nome })));
const itemsAluno = computed(() => alunos.value.map((a) => ({ id: a.aln_id, label: a.aln_nome })));
const itemsPeriodo = computed(() => unidades.value.map((u) => ({ id: u.uni_id, label: `${u.uni_numero}º ${capitalize(u.uni_tipo)}` })));

function gerar() {
    if (!escId.value || !anlId.value || !turId.value) return;
    gerando.value = true;
    const payload: Record<string, any> = { anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value };
    if (disId.value) payload.dis_id = disId.value;
    if (alnId.value) payload.aln_id = alnId.value;
    if (uniId.value) payload.uni_id = uniId.value;

    router.get('/relatorios/parecer-descritivo/gerar', payload, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Parecer Descritivo" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <NotebookPen class="size-5 text-indigo-600" /> Parecer Descritivo
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">Pareceres descritivos dos alunos de uma turma.</p>

            <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-3">
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
                    <LocalCombobox v-model="turId" :items="itemsTurma" placeholder="Selecione a turma..." />
                </div>

                <div v-if="itemsDisciplina.length" class="grid gap-1.5">
                    <FormLabel>Disciplina <span class="text-xs font-normal text-muted-foreground">(opcional)</span></FormLabel>
                    <LocalCombobox v-model="disId" :items="itemsDisciplina" placeholder="Todas as disciplinas" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Aluno <span class="text-xs font-normal text-muted-foreground">(opcional)</span></FormLabel>
                    <LocalCombobox v-model="alnId" :items="itemsAluno" placeholder="Todos os alunos" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Período <span class="text-xs font-normal text-muted-foreground">(opcional)</span></FormLabel>
                    <LocalCombobox v-model="uniId" :items="itemsPeriodo" placeholder="Todos os períodos" />
                </div>

                <div class="flex justify-end sm:col-span-2 lg:col-span-3">
                    <Button
                        :disabled="!escId || !anlId || !turId || gerando"
                        class="bg-indigo-600 text-white hover:bg-indigo-700"
                        @click="gerar"
                    >
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
