<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CalendarCheck, Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    meses: { id: number; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Diário de Frequência Mensal', href: '/relatorios/frequencia-mensal' }];

// Mantém os filtros ao voltar do resultado (gerar outro sem refazer tudo).
const { restaurar, salvar } = useFiltrosRelatorio('frequencia-mensal', {
    anl_id: null as number | null,
    esc_id: null as number | null,
    tur_id: null as number | null,
    mes: null as number | null,
});
const salvos = restaurar();
// Enquanto restaura, as cascatas ficam mudas (senão zeram o que foi restaurado).
let restaurando = true;

const anlId = ref<number | null>(salvos.anl_id ?? props.anosLetivos[0]?.anl_id ?? null);
const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null);
const turId = ref<number | null>(null);
const mes = ref<number | null>(salvos.mes ?? new Date().getMonth() + 1);

const turmas = ref<{ tur_id: number; tur_nome: string; ser_nome: string | null }[]>([]);
const gerando = ref(false);

const getJson = async (url: string) => {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    return r.ok ? await r.json() : [];
};
async function loadTurmas() {
    turmas.value = []; turId.value = null;
    if (!escId.value || !anlId.value) return;
    turmas.value = await getJson(`/relatorios/frequencia-mensal/turmas?esc_id=${escId.value}&anl_id=${anlId.value}`);
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
const itemsTurma = computed(() => turmas.value.map((t) => ({ id: t.tur_id, label: (t.ser_nome ? t.ser_nome + ' - ' : '') + t.tur_nome })));
const itemsMes = computed(() => props.meses.map((m) => ({ id: m.id, label: m.label })));

function gerar() {
    if (!escId.value || !anlId.value || !turId.value || !mes.value) return;
    salvar({ anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value, mes: mes.value });
    gerando.value = true;
    router.get('/relatorios/frequencia-mensal/gerar', { anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value, mes: mes.value }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Diário de Frequência Mensal" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <CalendarCheck class="size-5 text-indigo-600" /> Diário de Frequência Mensal
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">Frequência da turma em um mês. Fundamental II e EJA contam por dias letivos; demais segmentos por aulas da turma.</p>

            <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
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
                    <FormLabel :required="true">Mês</FormLabel>
                    <LocalCombobox v-model="mes" :items="itemsMes" placeholder="Selecione o mês..." />
                </div>
                <div class="flex items-end justify-end sm:col-span-2 lg:col-span-4">
                    <Button :disabled="!escId || !anlId || !turId || !mes || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" /> Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
