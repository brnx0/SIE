<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ScrollText, Loader2, TriangleAlert } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }

const props = defineProps<{ anosLetivos: AnoLetivo[]; escolas: Escola[]; userEscola: Escola | null; isAdmin: boolean }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Ata Final do Encerramento', href: '/relatorios/ata-final' }];

// Mantém os filtros ao voltar do resultado (gerar outro sem refazer tudo).
const { restaurar, salvar } = useFiltrosRelatorio('ata-final', {
    anl_id: null as number | null,
    esc_id: null as number | null,
    tur_id: null as number | null,
});
const salvos = restaurar();
// Enquanto restaura, as cascatas ficam mudas (senão zeram o que foi restaurado).
let restaurando = true;

const anlId = ref<number | null>(salvos.anl_id ?? props.anosLetivos[0]?.anl_id ?? null);
const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null);
const turId = ref<number | null>(null);

const turmas = ref<{ tur_id: number; turma: string; encerrada: boolean }[]>([]);
const carregandoTurmas = ref(false);
const gerando = ref(false);

const getJson = async (url: string) => {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    return r.ok ? await r.json() : { turmas: [] };
};

async function loadTurmas() {
    turmas.value = []; turId.value = null;
    if (!escId.value || !anlId.value) return;
    carregandoTurmas.value = true;
    try {
        const j = await getJson(`/relatorios/ata-final/turmas?esc_id=${escId.value}&anl_id=${anlId.value}`);
        turmas.value = j.turmas ?? [];
    } finally { carregandoTurmas.value = false; }
}

onMounted(async () => {
    await loadTurmas();
    // Só restaura o que ainda existe nas listas carregadas.
    if (salvos.tur_id && turmas.value.some((t) => t.tur_id === salvos.tur_id)) {
        turId.value = salvos.tur_id;
    }
    restaurando = false;
});
watch([escId, anlId], () => { if (!restaurando) loadTurmas(); });

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsTurma = computed(() => turmas.value.map((t) => ({ id: t.tur_id, label: t.encerrada ? t.turma : `${t.turma} — não encerrada` })));
const turmaSel = computed(() => turmas.value.find((t) => t.tur_id === turId.value) ?? null);
const turmaNaoEncerrada = computed(() => !!turmaSel.value && !turmaSel.value.encerrada);

function gerar() {
    if (!escId.value || !anlId.value || !turId.value) return;
    salvar({ anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value });
    gerando.value = true;
    router.get('/relatorios/ata-final/gerar', { anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value }, { preserveScroll: true, onFinish: () => { gerando.value = false; } });
}
</script>

<template>
    <Head title="Ata Final do Encerramento" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <ScrollText class="size-5 text-indigo-600" /> Ata Final do Encerramento
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">Nota final de cada disciplina e o resultado final do aluno. Lista <strong>todas as turmas</strong> e sinaliza as ainda <strong>não encerradas</strong>.</p>

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
                    <LocalCombobox v-model="turId" :items="itemsTurma" :placeholder="carregandoTurmas ? 'Carregando...' : (itemsTurma.length ? 'Selecione a turma...' : 'Nenhuma turma encontrada')" />
                </div>
                <div v-if="turmaNaoEncerrada" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 sm:col-span-2 lg:col-span-3 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>Esta turma <strong>ainda não foi encerrada</strong>. A ata sai com as notas atuais, mas <strong>sem o resultado final</strong> dos alunos.</span>
                </div>
                <div class="flex items-end justify-end sm:col-span-2 lg:col-span-3">
                    <Button :disabled="!escId || !anlId || !turId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" /> Gerar Ata
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
