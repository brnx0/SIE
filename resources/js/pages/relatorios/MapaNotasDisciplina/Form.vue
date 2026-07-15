<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ListChecks, Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mapa de Notas', href: '/relatorios/mapa-notas-disciplina' }];

// Mantém os filtros ao voltar do resultado (gerar outro sem refazer tudo).
const { restaurar, salvar } = useFiltrosRelatorio('mapa-notas-disciplina', {
    anl_id: null as number | null,
    esc_id: null as number | null,
    tur_id: null as number | null,
    dis_id: null as number | null,
    uni_id: null as number | null,
});
const salvos = restaurar();
// Enquanto restaura, as cascatas ficam mudas (senão zeram o que foi restaurado).
let restaurando = true;

const anlId = ref<number | null>(salvos.anl_id ?? props.anosLetivos[0]?.anl_id ?? null);
const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null);
const turId = ref<number | null>(null);
const disId = ref<number | null>(null);
const uniId = ref<number | null>(null);

const turmas = ref<{ tur_id: number; tur_nome: string; ser_nome: string | null }[]>([]);
const disciplinas = ref<{ dis_id: number; dis_nome: string }[]>([]);
const unidades = ref<{ uni_id: number; uni_numero: number; uni_tipo: string }[]>([]);
const gerando = ref(false);

const capitalize = (s: string) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);
const getJson = async (url: string) => {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    return r.ok ? await r.json() : [];
};

async function loadTurmas() {
    turmas.value = []; turId.value = null;
    disciplinas.value = []; disId.value = null;
    if (!escId.value || !anlId.value) return;
    turmas.value = await getJson(`/relatorios/mapa-notas-disciplina/turmas?esc_id=${escId.value}&anl_id=${anlId.value}`);
}
async function loadDisciplinas() {
    disciplinas.value = []; disId.value = null;
    if (!turId.value) return;
    disciplinas.value = await getJson(`/relatorios/mapa-notas-disciplina/disciplinas?tur_id=${turId.value}`);
}
async function loadUnidades() {
    unidades.value = []; uniId.value = null;
    if (!anlId.value) return;
    unidades.value = await getJson(`/relatorios/mapa-notas-disciplina/unidades?anl_id=${anlId.value}`);
}

onMounted(async () => {
    await Promise.all([loadTurmas(), loadUnidades()]);
    // Só restaura o que ainda existe nas listas carregadas.
    if (salvos.uni_id && unidades.value.some((u) => u.uni_id === salvos.uni_id)) {
        uniId.value = salvos.uni_id;
    }
    if (salvos.tur_id && turmas.value.some((t) => t.tur_id === salvos.tur_id)) {
        turId.value = salvos.tur_id;
        await loadDisciplinas();
        if (salvos.dis_id && disciplinas.value.some((d) => d.dis_id === salvos.dis_id)) {
            disId.value = salvos.dis_id;
        }
    }
    restaurando = false;
});
watch([escId, anlId], () => { if (!restaurando) loadTurmas(); });
watch(turId, () => { if (!restaurando) loadDisciplinas(); });
watch(anlId, () => { if (!restaurando) loadUnidades(); });

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsTurma = computed(() => turmas.value.map((t) => ({ id: t.tur_id, label: (t.ser_nome ? t.ser_nome + ' - ' : '') + t.tur_nome })));
const itemsDisciplina = computed(() => disciplinas.value.map((d) => ({ id: d.dis_id, label: d.dis_nome })));
const itemsPeriodo = computed(() => unidades.value.map((u) => ({ id: u.uni_id, label: `${u.uni_numero}º ${capitalize(u.uni_tipo)}` })));

const podeGerar = computed(() => !!escId.value && !!anlId.value && !!turId.value && !!disId.value && !!uniId.value);

function gerar() {
    if (!podeGerar.value) return;
    salvar({
        anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value,
        dis_id: disId.value, uni_id: uniId.value,
    });
    gerando.value = true;
    const payload: Record<string, any> = {
        anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value, dis_id: disId.value, uni_id: uniId.value,
    };
    router.get('/relatorios/mapa-notas-disciplina/gerar', payload, { preserveScroll: true, onFinish: () => { gerando.value = false; } });
}
</script>

<template>
    <Head title="Mapa de Notas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <ListChecks class="size-5 text-indigo-600" /> Mapa de Notas
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">Todas as notas de uma disciplina no período: média, recuperação e média final (M.F) por aluno.</p>

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
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Disciplina</FormLabel>
                    <LocalCombobox v-model="disId" :items="itemsDisciplina" placeholder="Selecione a disciplina..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Período</FormLabel>
                    <LocalCombobox v-model="uniId" :items="itemsPeriodo" placeholder="Selecione a unidade..." />
                </div>
                <div class="flex items-end justify-end sm:col-span-2 lg:col-span-1">
                    <Button :disabled="!podeGerar || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" /> Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
