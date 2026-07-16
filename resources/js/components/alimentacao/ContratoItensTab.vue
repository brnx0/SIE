<script setup lang="ts">
import RemoteCombobox from '@/components/common/RemoteCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import type { Contrato, ContratoItemLinha, UnidadeMedida } from '@/types/alimentacao';
import { router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    contrato: Contrato;
    itens: ContratoItemLinha[];
    unidades: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
    totais: { valor_itens: number; valor_ordens: number };
}>();

const { isReadOnly } = usePermissao();

// Renderiza direto do prop — cópia em ref fica obsoleta após o reload do Inertia.
const linhas = computed(() => props.itens ?? []);
const base = computed(() => `/alimentacao/contratos/${props.contrato.ctr_id}/itens`);

const form = useForm<{
    cti_itm_id: number | null;
    cti_umd_id: number | null;
    cti_qtd: number | string;
    cti_vl_unitario: number | string;
}>({
    cti_itm_id: null,
    cti_umd_id: null,
    cti_qtd: '',
    cti_vl_unitario: '',
});

const itemLabel = ref<string | null>(null);

// Sugere a unidade de controle do item — é a que sempre converte.
const onItemSelected = async (item: { id: number; label: string } | null) => {
    itemLabel.value = item?.label ?? null;
    if (!item) return;
    const res = await fetch(`/api/alimentacao/itens/search?incluir_ids=${item.id}&q=`, { headers: { Accept: 'application/json' } });
    if (!res.ok) return;
    const achado = (await res.json()).find((i: { itm_id: number }) => i.itm_id === item.id);
    if (achado && !form.cti_umd_id) form.cti_umd_id = achado.itm_umd_id;
};

const adicionar = () => {
    if (isReadOnly.value) return;
    form.post(base.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { form.reset(); itemLabel.value = null; },
    });
};

const remover = (linha: ContratoItemLinha) => {
    if (!confirm(`Remover "${linha.item_nome}" do contrato?`)) return;
    router.delete(`${base.value}/${linha.cti_id}`, { preserveScroll: true, preserveState: true });
};

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const qtd = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });

const valorItens = computed(() => props.totais?.valor_itens ?? 0);
const valorContrato = computed(() => Number(props.contrato.ctr_vl_total ?? 0));

// Soma dos itens ≠ valor do contrato não é erro (pode haver arredondamento ou
// itens não detalhados), mas quem cadastra precisa enxergar a diferença.
const divergencia = computed(() => valorItens.value - valorContrato.value);
</script>

<template>
    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Itens contratados</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    O que foi contratado, com quantidade e preço unitário. As ordens de fornecimento só podem pedir
                    o que está aqui, e dentro do saldo.
                </p>
            </div>
            <RefreshButton />
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border bg-muted/30 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Soma dos itens</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ brl(valorItens) }}</p>
            </div>
            <div class="rounded-lg border bg-muted/30 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Valor do contrato</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ brl(valorContrato) }}</p>
                <p
                    v-if="linhas.length && Math.abs(divergencia) > 0.009"
                    class="mt-0.5 text-xs text-amber-600 dark:text-amber-400"
                >
                    {{ divergencia > 0 ? 'Itens excedem o contrato em ' : 'Faltam ' }}{{ brl(Math.abs(divergencia)) }}
                </p>
            </div>
            <div class="rounded-lg border bg-muted/30 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Já pedido em ordens</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ brl(totais?.valor_ordens ?? 0) }}</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3 text-right">Contratado</th>
                        <th class="px-4 py-3 text-right">Pedido</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                        <th class="px-4 py-3 text-right">Vl. unitário</th>
                        <th class="px-4 py-3 text-right">Vl. total</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="linhas.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">
                            Nenhum item contratado. Sem itens, não é possível emitir ordens de fornecimento.
                        </td>
                    </tr>
                    <tr v-for="linha in linhas" :key="linha.cti_id" class="transition-colors hover:bg-muted/30">
                        <td class="px-4 py-3 font-medium">{{ linha.item_nome }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ qtd(linha.cti_qtd) }} {{ linha.umd_sigla }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ qtd(linha.qtd_pedida) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <span :class="linha.qtd_saldo <= 0 ? 'font-medium text-rose-600 dark:text-rose-400' : 'font-medium text-emerald-700 dark:text-emerald-400'">
                                {{ qtd(linha.qtd_saldo) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ brl(linha.cti_vl_unitario) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ brl(linha.vl_total) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end">
                                <Button
                                    v-if="!isReadOnly"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="linha.qtd_pedida > 0"
                                    :title="linha.qtd_pedida > 0 ? 'Item já pedido em ordens' : 'Remover'"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-40 dark:hover:bg-rose-900/30"
                                    @click="remover(linha)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <fieldset v-if="!isReadOnly" :disabled="form.processing" class="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-8 sm:items-start">
            <div class="grid gap-2 sm:col-span-3">
                <FormLabel :required="true">Item</FormLabel>
                <RemoteCombobox
                    v-model="form.cti_itm_id"
                    fetch-url="/api/alimentacao/itens/search"
                    id-key="itm_id"
                    label-key="itm_nome"
                    placeholder="Buscar item..."
                    :invalid="!!form.errors.cti_itm_id"
                    :selected-label="itemLabel"
                    @selected="onItemSelected"
                />
                <InputError :message="form.errors.cti_itm_id" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="cti_qtd" :required="true">Quantidade</FormLabel>
                <Input id="cti_qtd" type="number" step="0.0001" min="0" inputmode="decimal" v-model.number="form.cti_qtd" />
                <InputError :message="form.errors.cti_qtd" />
            </div>

            <div class="grid gap-2 sm:col-span-1">
                <FormLabel for="cti_umd_id" :required="true">Unid.</FormLabel>
                <select id="cti_umd_id" v-model="form.cti_umd_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option :value="null" disabled>...</option>
                    <option v-for="u in unidades" :key="u.umd_id" :value="u.umd_id">{{ u.umd_sigla }}</option>
                </select>
                <InputError :message="form.errors.cti_umd_id" />
            </div>

            <div class="grid gap-2 sm:col-span-1">
                <FormLabel for="cti_vl_unitario" :required="true">Vl. unit.</FormLabel>
                <Input id="cti_vl_unitario" type="number" step="0.0001" min="0" inputmode="decimal" v-model.number="form.cti_vl_unitario" />
                <InputError :message="form.errors.cti_vl_unitario" />
            </div>

            <div class="flex sm:col-span-1 sm:pt-7">
                <Button type="button" class="w-full bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400" @click="adicionar">
                    <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                    <Plus v-else class="mr-2 size-4" />
                    Incluir
                </Button>
            </div>
        </fieldset>
    </div>
</template>
