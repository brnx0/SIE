<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';
import { CAMPO } from '@/lib/formCampo';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { LayoutGrid, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface Serie { ser_id: number; ser_nome: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    hoje: string;
    turnos: string[];
    series: Serie[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alunos em Turmas Multisseriadas', href: '/relatorios/turmas-multisseriadas' },
];

const anoDefault = props.anosLetivos.find((a) => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

const { restaurar, salvar } = useFiltrosRelatorio('turmas-multisseriadas', {
    anl_id: null as number | null,
    esc_id: null as number | null,
    ser_id: null as number | null,
    turno: '' as string,
    situacao: 'todos' as 'todos' | 'ativo' | 'inativo',
    data_ref: null as string | null,
});
const salvos = restaurar();

const anlId = ref<number | null>(salvos.anl_id ?? anoDefault?.anl_id ?? null);
const escId = ref<number | null>(
    props.isAdmin ? (salvos.esc_id ?? null) : (props.userEscola?.esc_id ?? null),
);
const serId = ref<number | null>(salvos.ser_id ?? null);
const turno = ref<string>(salvos.turno ?? '');
const situacao = ref<'todos' | 'ativo' | 'inativo'>(salvos.situacao ?? 'todos');
const dataRef = ref<string>(salvos.data_ref ?? props.hoje);
const gerando = ref(false);

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsSerie = computed(() => props.series.map((s) => ({ id: s.ser_id, label: s.ser_nome })));

const escolaTravada = computed(() => !props.isAdmin && !!props.userEscola);

function gerar() {
    if (!anlId.value) return;
    gerando.value = true;
    salvar({
        anl_id: anlId.value, esc_id: escId.value, ser_id: serId.value,
        turno: turno.value, situacao: situacao.value, data_ref: dataRef.value,
    });
    router.get('/relatorios/turmas-multisseriadas/gerar', {
        anl_id: Number(anlId.value),
        ...(escId.value ? { esc_id: Number(escId.value) } : {}),
        ...(serId.value ? { ser_id: Number(serId.value) } : {}),
        ...(turno.value ? { turno: turno.value } : {}),
        situacao: situacao.value,
        ...(dataRef.value ? { data_ref: dataRef.value } : {}),
    }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Alunos em Turmas Multisseriadas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <LayoutGrid class="size-5 text-indigo-600" /> Alunos em Turmas Multisseriadas
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Alunos matriculados em turmas multisseriadas (série marcada como multi), ordenados por escola, turma e nome.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid items-start gap-5 sm:grid-cols-2 lg:grid-cols-3">
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
                        placeholder="Todas as escolas"
                    />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Série / Ano Escolar</FormLabel>
                    <LocalCombobox v-model="serId" :items="itemsSerie" placeholder="Todas as séries multi" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Turno</FormLabel>
                    <select v-model="turno" :class="CAMPO">
                        <option value="">Todos os turnos</option>
                        <option v-for="t in turnos" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Situação do Aluno</FormLabel>
                    <select v-model="situacao" :class="CAMPO">
                        <option value="todos">Todos</option>
                        <option value="ativo">Somente ativos</option>
                        <option value="inativo">Somente inativos</option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Data de referência (idade)</FormLabel>
                    <input v-model="dataRef" type="date" :class="CAMPO" />
                </div>

                <div class="sm:col-span-2 lg:col-span-3 flex justify-end">
                    <Button :disabled="!anlId || !dataRef || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
