<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import AlunoCombobox from '@/components/relatorio/AlunoCombobox.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { GraduationCap, Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

interface Escola { esc_id: number; esc_nome: string }
interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Turma { tur_id: number; tur_nome: string; tur_situacao: string; tur_modalidade?: string; serie?: { ser_nome: string } | null }
interface AlunoResultado { tma_id: number; aln_id: number; aln_nome: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Declaração de Conclusão', href: '/relatorios/declaracao-conclusao' },
];

// Mantém os filtros ao voltar do resultado (gerar outro sem refazer tudo).
const { restaurar, salvar } = useFiltrosRelatorio('declaracao-conclusao', {
    anl_id: null as number | null,
    esc_id: null as number | null,
    tur_id: null as number | null,
    aln_id: null as number | null,
});
const salvos = restaurar();
// Enquanto restaura, as cascatas ficam mudas (senão zeram o que foi restaurado).
let restaurando = true;

const anoEmExercicio = computed(() => props.anosLetivos.find((a) => a.anl_fl_em_exercicio) ?? props.anosLetivos[0] ?? null);
const anlId = ref<number | null>(salvos.anl_id ?? anoEmExercicio.value?.anl_id ?? null);
const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null);
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const turId = ref<number | null>(null);
const alnId = ref<number | null>(null);
const turmas = ref<Turma[]>([]);
const itemsTurma = computed(() =>
    turmas.value.map((t) => ({ id: t.tur_id, label: (t.serie?.ser_nome ? t.serie.ser_nome + ' - ' : '') + t.tur_nome })),
);
const alunos = ref<AlunoResultado[]>([]);
const loadingTurmas = ref(false);
const loadingAlunos = ref(false);
const gerando = ref(false);

async function loadTurmas() {
    turmas.value = [];
    turId.value = null;
    alunos.value = [];
    alnId.value = null;
    if (!escId.value || !anlId.value) return;
    loadingTurmas.value = true;
    try {
        const p = new URLSearchParams({ esc_id: String(escId.value), anl_id: String(anlId.value) });
        const r = await fetch(`/api/matriculas/turmas?${p}`);
        if (r.ok) {
            const lista: any[] = await r.json();
            // Conclusão alcança turmas regulares (abertas ou encerradas).
            turmas.value = lista.filter((t) => (t.tur_modalidade ?? 'REGULAR') === 'REGULAR');
        }
    } finally {
        loadingTurmas.value = false;
    }
}

async function loadAlunos() {
    alunos.value = [];
    alnId.value = null;
    if (!turId.value) return;
    loadingAlunos.value = true;
    try {
        const r = await fetch(`/api/turmas/${turId.value}/alunos`);
        if (r.ok) {
            const lista: any[] = await r.json();
            // Só alunos com situação final preenchida (saída registrada).
            alunos.value = lista
                .filter((a: any) => a.tma_tas_cod_saida != null)
                .map((a: any) => ({ tma_id: a.tma_id, aln_id: a.aln_id, aln_nome: a.aln_nome }));
        }
    } finally {
        loadingAlunos.value = false;
    }
}

onMounted(async () => {
    await loadTurmas();
    // Só restaura o que ainda existe nas listas carregadas.
    if (salvos.tur_id && turmas.value.some((t) => t.tur_id === salvos.tur_id)) {
        turId.value = salvos.tur_id;
        await loadAlunos();
        if (salvos.aln_id && alunos.value.some((a) => a.aln_id === salvos.aln_id)) {
            alnId.value = salvos.aln_id;
        }
    }
    restaurando = false;
});
watch([escId, anlId], () => { if (!restaurando) loadTurmas(); });
watch(turId, () => { if (!restaurando) loadAlunos(); });

function gerar() {
    if (!escId.value || !anlId.value) return;
    salvar({
        anl_id: Number(anlId.value),
        esc_id: Number(escId.value),
        tur_id: turId.value ? Number(turId.value) : null,
        aln_id: alnId.value,
    });
    gerando.value = true;
    const payload: Record<string, any> = { esc_id: Number(escId.value), anl_id: Number(anlId.value) };
    if (turId.value) payload.tur_id = Number(turId.value);
    if (alnId.value) payload.aln_id = Number(alnId.value);

    router.get('/relatorios/declaracao-conclusao/gerar', payload, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Declaração de Conclusão" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <GraduationCap class="size-5 text-indigo-600" /> Declaração de Conclusão
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Emite declaração de conclusão para alunos com <strong>situação final registrada</strong> na turma.
                Gere por aluno, por turma ou em lote (todos da escola no ano).
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model="anlId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option :value="null" disabled>Selecione...</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">
                            {{ a.anl_ano }}<span v-if="a.anl_fl_em_exercicio"> (em exercício)</span>
                        </option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" :disabled="!isAdmin && !!userEscola" placeholder="Selecione a escola..." />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Turma (opcional)</FormLabel>
                    <LocalCombobox
                        v-model="turId"
                        :items="itemsTurma"
                        :disabled="!turmas.length"
                        placeholder="Todas as turmas regulares"
                    />
                    <p v-if="loadingTurmas" class="text-xs text-muted-foreground">Carregando turmas...</p>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Aluno (opcional)</FormLabel>
                    <template v-if="turId">
                        <AlunoCombobox
                            :model-value="alnId"
                            :alunos="alunos"
                            :loading="loadingAlunos"
                            :disabled="!alunos.length"
                            all-label="Todos da turma"
                            @update:model-value="(v) => (alnId = v)"
                        />
                        <p v-if="loadingAlunos" class="text-xs text-muted-foreground">Carregando alunos...</p>
                        <p v-else-if="!alunos.length" class="text-xs text-amber-600">Nenhum aluno com situação final registrada nesta turma.</p>
                    </template>
                    <p v-else class="text-xs text-muted-foreground">Selecione uma turma para filtrar aluno específico.</p>
                </div>

                <div class="sm:col-span-2 flex justify-end">
                    <Button :disabled="!escId || !anlId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Declaração
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
