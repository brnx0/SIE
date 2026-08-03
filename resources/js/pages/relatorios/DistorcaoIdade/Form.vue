<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';
import { CAMPO } from '@/lib/formCampo';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CalendarClock, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface Turma { tur_id: number; esc_id: number; anl_id: number; ser_id: number | null; tur_nome: string }
interface Serie { ser_id: number; ser_nome: string; ser_idade: number }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    hoje: string;
    turmas: Turma[];
    series: Serie[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Distorção Idade-Série', href: '/relatorios/distorcao-idade' },
];

const anoDefault = props.anosLetivos.find((a) => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

const { restaurar, salvar } = useFiltrosRelatorio('distorcao-idade', {
    anl_id: null as number | null,
    esc_id: null as number | null,
    tur_id: null as number | null,
    ser_id: null as number | null,
    data_ref: null as string | null,
});
const salvos = restaurar();

const anlId = ref<number | null>(salvos.anl_id ?? anoDefault?.anl_id ?? null);
const escId = ref<number | null>(
    props.isAdmin ? (salvos.esc_id ?? null) : (props.userEscola?.esc_id ?? null),
);
const turId = ref<number | null>(salvos.tur_id ?? null);
const serId = ref<number | null>(salvos.ser_id ?? null);
const dataRef = ref<string>(salvos.data_ref ?? props.hoje);
const gerando = ref(false);

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsSerie = computed(() => props.series.map((s) => ({ id: s.ser_id, label: `${s.ser_nome} (idade ${s.ser_idade})` })));

// Turmas de todos os anos/escolas já vieram do servidor — o recorte é local.
const itemsTurma = computed(() =>
    props.turmas
        .filter((t) =>
            (!anlId.value || t.anl_id === anlId.value)
            && (!escId.value || t.esc_id === escId.value)
            && (!serId.value || t.ser_id === serId.value))
        .map((t) => ({ id: t.tur_id, label: t.tur_nome })),
);

// Trocar ano/escola/série invalida a turma escolhida se não pertencer mais ao recorte.
watch([anlId, escId, serId], () => {
    if (turId.value && !itemsTurma.value.some((t) => t.id === turId.value)) turId.value = null;
});

const escolaTravada = computed(() => !props.isAdmin && !!props.userEscola);

function gerar() {
    if (!anlId.value) return;
    gerando.value = true;
    salvar({
        anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value,
        ser_id: serId.value, data_ref: dataRef.value,
    });
    router.get('/relatorios/distorcao-idade/gerar', {
        anl_id: Number(anlId.value),
        ...(escId.value ? { esc_id: Number(escId.value) } : {}),
        ...(turId.value ? { tur_id: Number(turId.value) } : {}),
        ...(serId.value ? { ser_id: Number(serId.value) } : {}),
        ...(dataRef.value ? { data_ref: dataRef.value } : {}),
    }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Distorção Idade-Série" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <CalendarClock class="size-5 text-indigo-600" /> Distorção Idade-Série
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Alunos com idade <strong>acima da recomendada</strong> para a série/ano em que estão
                matriculados. Ordenado por escola, turma e nome.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid items-start gap-5 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Selecione o ano..." />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Data de referência</FormLabel>
                    <input v-model="dataRef" type="date" :class="CAMPO" />
                    <p class="text-xs text-muted-foreground">A idade é apurada nesta data. O padrão é hoje.</p>
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
                    <LocalCombobox v-model="serId" :items="itemsSerie" placeholder="Todas as séries" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Turma</FormLabel>
                    <LocalCombobox v-model="turId" :items="itemsTurma" placeholder="Todas as turmas" />
                    <p v-if="escId || serId" class="text-xs text-muted-foreground">
                        Mostrando apenas as turmas do recorte selecionado.
                    </p>
                </div>

                <div class="sm:col-span-2 flex justify-end">
                    <Button :disabled="!anlId || !dataRef || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
