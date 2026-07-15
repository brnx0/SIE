<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Baby, Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }
interface Turma { tur_id: number; turma: string; escola: string; encerrada: boolean }

const props = defineProps<{ anosLetivos: AnoLetivo[]; escolas: Escola[]; userEscola: Escola | null; isAdmin: boolean }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Final da Educação Infantil', href: '/relatorios/final-educacao-infantil' }];

// Mantém os filtros ao voltar do resultado (gerar outro sem refazer tudo).
const { restaurar, salvar } = useFiltrosRelatorio('final-educacao-infantil', {
    anl_id: null as number | null,
    esc_id: null as number | null,
    tur_id: null as number | null,
});
const salvos = restaurar();
// Enquanto restaura, as cascatas ficam mudas (senão zeram o que foi restaurado).
let restaurando = true;

const anlId = ref<number | null>(salvos.anl_id ?? props.anosLetivos[0]?.anl_id ?? null);
const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null); // obrigatória
const turId = ref<number>(0); // 0 = todas as turmas da escola

const turmas = ref<Turma[]>([]);
const carregandoTurmas = ref(false);
const gerando = ref(false);

async function loadTurmas() {
    turmas.value = [];
    turId.value = 0;
    if (!anlId.value || !escId.value) return;
    carregandoTurmas.value = true;
    try {
        const u = new URL('/relatorios/final-educacao-infantil/turmas', window.location.origin);
        u.searchParams.set('anl_id', String(anlId.value));
        u.searchParams.set('esc_id', String(escId.value));
        const r = await fetch(u, { headers: { Accept: 'application/json' } });
        turmas.value = r.ok ? (await r.json()).turmas ?? [] : [];
    } finally {
        carregandoTurmas.value = false;
    }
}

onMounted(async () => {
    await loadTurmas();
    // Só restaura o que ainda existe na lista carregada.
    if (salvos.tur_id && turmas.value.some((t) => t.tur_id === salvos.tur_id)) {
        turId.value = salvos.tur_id;
    }
    restaurando = false;
});
watch([anlId, escId], () => { if (!restaurando) loadTurmas(); });

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsTurma = computed(() => [
    { id: 0, label: 'Todas as turmas' },
    ...turmas.value.map((t) => ({ id: t.tur_id, label: t.turma })),
]);

function gerar() {
    if (!anlId.value || !escId.value) return;
    salvar({ anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value });
    gerando.value = true;
    const params: Record<string, number> = { anl_id: anlId.value, esc_id: escId.value };
    if (turId.value) params.tur_id = turId.value;
    router.get('/relatorios/final-educacao-infantil/gerar', params, { preserveScroll: true, onFinish: () => { gerando.value = false; } });
}
</script>

<template>
    <Head title="Final da Educação Infantil" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <Baby class="size-5 text-indigo-600" /> Relatório Final da Educação Infantil
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Por turma de <strong>Educação Infantil</strong> (Creche/Pré-escola), 1 por página: matrícula, nome, data de nascimento e situação final.
                Selecione a <strong>escola</strong>; deixe a turma em <strong>Todas</strong> para gerar todas as turmas dela.
            </p>

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
                    <FormLabel>Turma</FormLabel>
                    <LocalCombobox v-model="turId" :items="itemsTurma" :placeholder="!escId ? 'Selecione a escola primeiro' : (carregandoTurmas ? 'Carregando...' : 'Todas as turmas')" />
                </div>
                <div class="flex items-end justify-end sm:col-span-2 lg:col-span-3">
                    <Button :disabled="!anlId || !escId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" /> Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
