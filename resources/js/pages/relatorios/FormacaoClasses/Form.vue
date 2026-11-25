<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { LayoutGrid, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';
import { CAMPO } from '@/lib/formCampo';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    situacoes: string[];
    /** Variante da Central: escola obrigatória (sem modo rede). */
    porEscola?: boolean;
    baseUrl?: string;
}>();

const baseUrl = props.baseUrl ?? '/relatorios/formacao-classes';
const titulo = props.porEscola ? 'Formação de Classes por Escola' : 'Formação de Classes';

const breadcrumbs: BreadcrumbItem[] = [
    { title: titulo, href: baseUrl },
];

const anoDefault = props.anosLetivos.find(a => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

// Mantém os filtros ao voltar do resultado (gerar outro sem refazer tudo).
const { restaurar, salvar } = useFiltrosRelatorio(props.porEscola ? 'formacao-classes-escola' : 'formacao-classes', {
    anl_id: null as number | '' | null,
    esc_id: null as number | null,
    situacao: 'todas' as string,
});
const salvos = restaurar();

const anlId = ref<number | ''>(salvos.anl_id ?? anoDefault?.anl_id ?? '');
// Escola vazia = rede inteira. Quem não é admin segue preso à própria escola.
const escId = ref<number | null>(
    props.isAdmin ? (salvos.esc_id ?? null) : (props.userEscola?.esc_id ?? null),
);
const situacao = ref<string>(salvos.situacao ?? 'todas');
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const escolaTravada = computed(() => !props.isAdmin && !!props.userEscola);
const gerando = ref(false);
const podeGerar = computed(() => !!anlId.value && (!props.porEscola || !!escId.value));

function gerar() {
    if (!podeGerar.value) return;
    salvar({ anl_id: anlId.value, esc_id: escId.value, situacao: situacao.value });
    gerando.value = true;
    router.get(`${baseUrl}/gerar`, {
        anl_id: Number(anlId.value),
        ...(escId.value ? { esc_id: Number(escId.value) } : {}),
        situacao: situacao.value,
    }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head :title="titulo" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <LayoutGrid class="size-5 text-brand-600" /> {{ titulo }}
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Quadro de turmas regulares com capacidade, expansão, alunos da rede, novos, vagas, PCD,
                faixa etária e professores alocados.
                {{ porEscola ? 'Escolha a escola: o quadro sai por etapa, com o total da escola.' : 'Deixe a escola em branco para varrer a rede inteira.' }}
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid items-start gap-4 sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model="anlId" :class="CAMPO">
                        <option value="">—</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">
                            {{ a.anl_ano }} <span v-if="a.anl_fl_em_exercicio">(em exercício)</span>
                        </option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="!!porEscola">Escola</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" :disabled="escolaTravada" :placeholder="porEscola ? 'Selecione a escola' : 'Todas as escolas da rede'" />
                    <p v-if="escolaTravada || !porEscola" class="text-xs text-muted-foreground">
                        {{ escolaTravada ? 'Você visualiza apenas a sua escola de lotação.' : 'Em branco = rede inteira.' }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Status da Turma</FormLabel>
                    <select v-model="situacao" :class="CAMPO">
                        <option value="todas">Todas</option>
                        <option v-for="s in situacoes" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>

                <div class="sm:col-span-3 flex justify-end">
                    <Button :disabled="!podeGerar || gerando" class="bg-brand-600 text-white hover:bg-brand-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
