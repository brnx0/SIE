<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Accessibility, Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface TurmaAee { tur_id: number; tur_nome: string; tur_esc_id: number }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Desempenho AEE', href: '/relatorios/desempenho-aee' },
];

const anoDefault = props.anosLetivos.find((a) => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

// Mantém os filtros ao voltar do resultado (gerar outro sem refazer tudo).
const { restaurar, salvar } = useFiltrosRelatorio('desempenho-aee', {
    anl_id: null as number | '' | null,
    esc_id: null as number | null,
    tur_id: null as number | '' | null,
});
const salvos = restaurar();
// Enquanto restaura, as cascatas ficam mudas (senão zeram o que foi restaurado).
let restaurando = true;

const anlId = ref<number | ''>(salvos.anl_id ?? anoDefault?.anl_id ?? '');
const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null);
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const turId = ref<number | ''>('');
const turmas = ref<TurmaAee[]>([]);
const gerando = ref(false);

const fetchTurmas = async () => {
    turId.value = '';
    turmas.value = [];
    if (!anlId.value) return;
    const url = new URL('/relatorios/desempenho-aee/turmas', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    if (escId.value) url.searchParams.set('esc_id', String(escId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) turmas.value = await r.json();
};

onMounted(async () => {
    await fetchTurmas();
    // Só restaura o que ainda existe na lista carregada.
    if (salvos.tur_id && turmas.value.some((t) => t.tur_id === salvos.tur_id)) {
        turId.value = salvos.tur_id;
    }
    restaurando = false;
});
watch([anlId, escId], () => { if (!restaurando) fetchTurmas(); });

function gerar() {
    if (!anlId.value) return;
    salvar({ anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value });
    gerando.value = true;
    const payload: Record<string, any> = { anl_id: Number(anlId.value) };
    if (escId.value) payload.esc_id = Number(escId.value);
    if (turId.value) payload.tur_id = Number(turId.value);

    router.get('/relatorios/desempenho-aee/gerar', payload, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Desempenho AEE" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <Accessibility class="size-5 text-indigo-600" /> Relatório de Desempenho AEE
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Lista os alunos das turmas AEE com suas avaliações descritivas registradas e a situação na turma.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid gap-4 sm:grid-cols-3">
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
                    <LocalCombobox v-model="escId" :items="itemsEscola" :disabled="!isAdmin && !!userEscola" placeholder="Todas as escolas" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Turma AEE</FormLabel>
                    <select v-model="turId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <option v-for="t in turmas" :key="t.tur_id" :value="t.tur_id">{{ t.tur_nome }}</option>
                    </select>
                </div>

                <div class="sm:col-span-3 flex justify-end">
                    <Button :disabled="!anlId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
