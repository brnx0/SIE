<script setup lang="ts">
import OrdemForm from '@/components/alimentacao/OrdemForm.vue';
import OrdemItensTab from '@/components/alimentacao/OrdemItensTab.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Contrato, Empenho, ItemContratoDisponivel, OrdemFornecimento, OrdemItemLinha } from '@/types/alimentacao';
import { Head, useForm } from '@inertiajs/vue3';
import { Ban, LoaderCircle, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    ordem: OrdemFornecimento;
    itens: OrdemItemLinha[];
    itensContrato: ItemContratoDisponivel[];
    saldoEmpenho: { empenhado: number; consumido: number; saldo: number } | null;
    vlTotal: number;
    contratos: Contrato[];
    empenhos: Pick<Empenho, 'emp_id' | 'emp_ctr_id' | 'emp_nr_empenho' | 'emp_vl_empenhado'>[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Ordens de Fornecimento', href: '/alimentacao/ordens-fornecimento' },
    { title: props.ordem.ofn_nr_ordem, href: `/alimentacao/ordens-fornecimento/${props.ordem.ofn_id}/edit` },
]);

const { isReadOnly } = usePermissao();

// Cancelar é a única mudança de situação feita à mão: EMITIDA/PARCIAL/ENTREGUE
// saem do recebimento. Entrega já registrada trava o cancelamento no backend.
const cancelada = computed(() => props.ordem.ofn_situacao === 'CANCELADA');
const temEntrega = computed(() => props.itens.some((i) => Number(i.ofi_qtd_entregue ?? 0) > 0));

const cancelando = ref(false);
const formCancelar = useForm<Record<string, any>>({ ofn_motivo_cancelamento: '' });
const cancelar = () => {
    formCancelar.post(`/alimentacao/ordens-fornecimento/${props.ordem.ofn_id}/cancelar`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { cancelando.value = false; },
    });
};
</script>

<template>
    <Head :title="`Ordem ${ordem.ofn_nr_ordem}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Ordem {{ ordem.ofn_nr_ordem }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Contrato {{ ordem.contrato?.ctr_nr_contrato }}/{{ ordem.contrato?.ctr_ano }}
                    · {{ ordem.contrato?.fornecedor?.frn_razao_social }}
                </p>
            </div>

            <OrdemForm
                mode="edit"
                :initial="ordem"
                :contratos="contratos"
                :empenhos="empenhos"
            />
            <OrdemItensTab
                :ordem="ordem"
                :itens="itens"
                :itens-contrato="itensContrato"
                :saldo-empenho="saldoEmpenho"
                :vl-total="vlTotal"
            />

            <div v-if="cancelada" class="flex items-start gap-3 rounded-xl border bg-muted/40 p-4">
                <Ban class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <div>
                    <p class="text-sm font-medium leading-none">Ordem cancelada</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ ordem.ofn_motivo_cancelamento }}</p>
                </div>
            </div>

            <div v-else-if="!isReadOnly" class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium leading-none">Cancelar ordem</p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            A ordem fica registrada como cancelada, com o motivo — não some do histórico.
                        </p>
                    </div>
                    <Button v-if="!cancelando && !temEntrega" type="button" variant="outline" class="text-rose-600" @click="cancelando = true">
                        <Ban class="mr-2 size-4" /> Cancelar ordem
                    </Button>
                </div>

                <p v-if="temEntrega" class="text-sm text-muted-foreground">
                    Esta ordem já tem entrega registrada no estoque e não pode ser cancelada — estorne o recebimento antes.
                </p>

                <div v-if="cancelando" class="grid gap-3 rounded-lg bg-rose-50/50 p-4 dark:bg-rose-900/10">
                    <div class="flex items-start gap-2 text-sm text-rose-800 dark:text-rose-200">
                        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                        <p>Ordem cancelada não volta atrás — emita uma nova se precisar.</p>
                    </div>
                    <div class="grid gap-2">
                        <FormLabel for="ofn_motivo_cancelamento" :required="true">Motivo do cancelamento</FormLabel>
                        <Input id="ofn_motivo_cancelamento" v-model="formCancelar.ofn_motivo_cancelamento" placeholder="Ex.: fornecedor não entregou no prazo" />
                        <InputError :message="formCancelar.errors.ofn_motivo_cancelamento" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            :disabled="formCancelar.processing"
                            class="bg-rose-600 text-white hover:bg-rose-700"
                            @click="cancelar"
                        >
                            <LoaderCircle v-if="formCancelar.processing" class="mr-2 size-4 animate-spin" />
                            <Ban v-else class="mr-2 size-4" />
                            Confirmar cancelamento
                        </Button>
                        <Button type="button" variant="outline" @click="cancelando = false">Voltar</Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
