<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';
import { CAMPO } from '@/lib/formCampo';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { UserCheck, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface Turma { tur_id: number; esc_id: number; anl_id: number; tur_nome: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    hoje: string;
    idadeMinima: number;
    turmas: Turma[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alunos Aptos para EJA', href: '/relatorios/alunos-aptos-eja' },
];

const anoDefault = props.anosLetivos.find((a) => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

const { restaurar, salvar } = useFiltrosRelatorio('alunos-aptos-eja', {
    anl_id: null as number | null,
    esc_id: null as number | null,
    tur_id: null as number | null,
    data_ref: null as string | null,
    incluir_eja: false,
});
const salvos = restaurar();

const anlId = ref<number | null>(salvos.anl_id ?? anoDefault?.anl_id ?? null);
const escId = ref<number | null>(
    props.isAdmin ? (salvos.esc_id ?? null) : (props.userEscola?.esc_id ?? null),
);
const turId = ref<number | null>(salvos.tur_id ?? null);
const dataRef = ref<string>(salvos.data_ref ?? props.hoje);
const incluirEja = ref<boolean>(salvos.incluir_eja ?? false);
const gerando = ref(false);

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));

// Turmas de todos os anos/escolas já vieram do servidor — o recorte é local.
const itemsTurma = computed(() =>
    props.turmas
        .filter((t) => (!anlId.value || t.anl_id === anlId.value) && (!escId.value || t.esc_id === escId.value))
        .map((t) => ({ id: t.tur_id, label: t.tur_nome })),
);

// Trocar ano ou escola invalida a turma escolhida se ela não pertencer mais ao recorte.
watch([anlId, escId], () => {
    if (turId.value && !itemsTurma.value.some((t) => t.id === turId.value)) turId.value = null;
});

const escolaTravada = computed(() => !props.isAdmin && !!props.userEscola);

function gerar() {
    if (!anlId.value) return;
    gerando.value = true;
    salvar({
        anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value,
        data_ref: dataRef.value, incluir_eja: incluirEja.value,
    });
    router.get('/relatorios/alunos-aptos-eja/gerar', {
        anl_id: Number(anlId.value),
        ...(escId.value ? { esc_id: Number(escId.value) } : {}),
        ...(turId.value ? { tur_id: Number(turId.value) } : {}),
        ...(dataRef.value ? { data_ref: dataRef.value } : {}),
        ...(incluirEja.value ? { incluir_eja: 1 } : {}),
    }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Alunos Aptos para EJA" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <UserCheck class="size-5 text-indigo-600" /> Alunos Aptos para EJA
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Alunos com {{ idadeMinima }} anos completos na data de referência, ordenados por escola, turma e nome.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid items-start gap-5 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Selecione o ano..." />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Data de referência</FormLabel>
                    <input v-model="dataRef" type="date" :class="CAMPO" />
                    <p class="text-xs text-muted-foreground">
                        A idade é apurada nesta data. O padrão é hoje.
                    </p>
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
                    <FormLabel>Turma</FormLabel>
                    <LocalCombobox v-model="turId" :items="itemsTurma" placeholder="Todas as turmas" />
                    <p v-if="escId" class="text-xs text-muted-foreground">
                        Mostrando apenas as turmas da escola selecionada.
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-start gap-2 text-sm cursor-pointer">
                        <!-- Checkbox (radix) expõe `checked`, não `modelValue`. -->
                        <Checkbox v-model:checked="incluirEja" class="mt-0.5" />
                        <span>
                            Incluir alunos que já estão em turma de EJA
                            <span class="block text-xs text-muted-foreground">
                                Por padrão eles ficam de fora, já que o relatório é de quem pode <em>ingressar</em> na
                                modalidade. O resumo do resultado informa quantos foram ocultados.
                            </span>
                        </span>
                    </label>
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
