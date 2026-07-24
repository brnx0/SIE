<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { AlarmClock, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface Segmento { seg_id: number; seg_nome: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    segmentos: Segmento[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pendências de Encerramento', href: '/relatorios/pendencias-encerramento' },
];

const anoDefault = props.anosLetivos.find((a) => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

const { restaurar, salvar } = useFiltrosRelatorio('pendencias-encerramento', {
    anl_id: null as number | null,
    esc_id: null as number | null,
    seg_id: null as number | null,
    formato: 'consolidado' as 'consolidado' | 'detalhado',
});
const salvos = restaurar();

// Filtros de topo (sem cascata): inicializam direto do que foi salvo.
const anlId = ref<number | null>(salvos.anl_id ?? anoDefault?.anl_id ?? null);
const escId = ref<number | null>(
    props.isAdmin ? (salvos.esc_id ?? null) : (props.userEscola?.esc_id ?? null),
);
const segId = ref<number | null>(salvos.seg_id ?? null);
const formato = ref<'consolidado' | 'detalhado'>(salvos.formato ?? 'consolidado');
const gerando = ref(false);

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsSegmento = computed(() => props.segmentos.map((s) => ({ id: s.seg_id, label: s.seg_nome })));
const escolaTravada = computed(() => !props.isAdmin && !!props.userEscola);

function gerar() {
    if (!anlId.value) return;
    gerando.value = true;
    salvar({ anl_id: anlId.value, esc_id: escId.value, seg_id: segId.value, formato: formato.value });
    router.get('/relatorios/pendencias-encerramento/gerar', {
        anl_id: Number(anlId.value),
        ...(escId.value ? { esc_id: Number(escId.value) } : {}),
        ...(segId.value ? { seg_id: Number(segId.value) } : {}),
        formato: formato.value,
    }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Pendências de Encerramento" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <AlarmClock class="size-5 text-indigo-600" /> Pendências de Encerramento e Renovação
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Escolas que ainda têm turmas sem encerramento ou sem renovação de matrículas no ano letivo.
                Escolas sem nenhuma pendência não aparecem no resultado.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid gap-5 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Selecione o ano..." />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Escola</FormLabel>
                    <LocalCombobox
                        v-model="escId"
                        :items="itemsEscola"
                        :disabled="escolaTravada"
                        placeholder="Todas as escolas da rede"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ escolaTravada ? 'Você visualiza apenas a sua escola de lotação.' : 'Deixe em branco para varrer toda a rede.' }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Segmento</FormLabel>
                    <LocalCombobox v-model="segId" :items="itemsSegmento" placeholder="Todos os segmentos" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Formato</FormLabel>
                    <div class="flex flex-col gap-2 pt-1">
                        <label class="flex items-start gap-2 text-sm cursor-pointer">
                            <input v-model="formato" type="radio" value="consolidado" class="mt-1 accent-indigo-600" />
                            <span>
                                <span class="font-medium">Consolidado</span>
                                <span class="block text-xs text-muted-foreground">Uma linha por escola, com as contagens.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm cursor-pointer">
                            <input v-model="formato" type="radio" value="detalhado" class="mt-1 accent-indigo-600" />
                            <span>
                                <span class="font-medium">Detalhado</span>
                                <span class="block text-xs text-muted-foreground">Uma linha por turma pendente.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="sm:col-span-2 rounded-lg border bg-muted/30 p-3 text-xs text-muted-foreground">
                    <strong class="text-foreground">Como as pendências são contadas:</strong>
                    <span class="block mt-1">
                        <strong>Sem encerramento</strong> — turma cuja situação ainda não é ENCERRADA.
                    </span>
                    <span class="block">
                        <strong>Sem renovação</strong> — turma já encerrada, mas em que nenhum aluno foi renovado para o ano seguinte.
                    </span>
                    <span class="block mt-1">
                        As duas categorias não se sobrepõem: renovar só é possível depois de encerrar, então cada turma é contada uma única vez.
                    </span>
                </div>

                <div class="sm:col-span-2 flex justify-end">
                    <Button :disabled="!anlId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
