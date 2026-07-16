<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import type { ItemContratoDisponivel, OrdemFornecimento, OrdemItemLinha } from '@/types/alimentacao';
import { router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, Plus, Trash2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    ordem: OrdemFornecimento;
    itens: OrdemItemLinha[];
    itensContrato: ItemContratoDisponivel[];
    saldoEmpenho: { empenhado: number; consumido: number; saldo: number } | null;
    vlTotal: number;
}>();

const { isReadOnly } = usePermissao();

// Renderiza direto do prop — cópia em ref fica obsoleta após o reload do Inertia.
const linhas = computed(() => props.itens ?? []);
const base = computed(() => `/alimentacao/ordens-fornecimento/${props.ordem.ofn_id}/itens`);

// Ordem entregue ou cancelada é registro fechado — o backend também barra.
const editavel = computed(() => ['EMITIDA', 'PARCIAL'].includes(props.ordem.ofn_situacao));

const form = useForm<{
    cti_id: number | null;
    ofi_qtd: number | string;
    ofi_vl_unitario: number | string;
}>({
    cti_id: null,
    ofi_qtd: '',
    ofi_vl_unitario: '',
});

const itemEscolhido = computed(() => props.itensContrato.find((i) => i.cti_id === form.cti_id));

// Ao escolher o item, já traz o preço do contrato — é o que costuma valer.
watch(() => form.cti_id, () => {
    if (itemEscolhido.value && !form.ofi_vl_unitario) {
        form.ofi_vl_unitario = itemEscolhido.value.cti_vl_unitario;
    }
});

const adicionar = () => {
    if (isReadOnly.value) return;
    form.post(base.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => form.reset(),
    });
};

const remover = (linha: OrdemItemLinha) => {
    if (!confirm(`Remover "${linha.item_nome}" desta ordem?`)) return;
    router.delete(`${base.value}/${linha.ofi_id}`, { preserveScroll: true, preserveState: true });
};

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const qtd = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });

// Prévia do que este item vai custar, antes de gravar.
const valorPrevisto = computed(() => {
    const q = Number(form.ofi_qtd) || 0;
    const v = Number(form.ofi_vl_unitario) || 0;
    return q * v;
});
</script>

<template>
    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Itens pedidos</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Só é possível pedir item que está no contrato, dentro do saldo dele — e, se houver empenho
                    vinculado, dentro do saldo do empenho.
                </p>
            </div>
            <RefreshButton />
        </div>

        <div
            v-if="!editavel"
            class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200"
        >
            <Lock class="mt-0.5 size-4 shrink-0" />
            <p>Esta ordem está <strong>{{ ordem.ofn_situacao }}</strong> e seus itens não podem mais ser alterados.</p>
        </div>

        <div class="grid gap-3" :class="saldoEmpenho ? 'sm:grid-cols-4' : 'sm:grid-cols-1'">
            <div class="rounded-lg border bg-muted/30 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Total da ordem</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ brl(vlTotal) }}</p>
            </div>
            <template v-if="saldoEmpenho">
                <div class="rounded-lg border bg-muted/30 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Empenho</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ brl(saldoEmpenho.empenhado) }}</p>
                </div>
                <div class="rounded-lg border bg-muted/30 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Consumido</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ brl(saldoEmpenho.consumido) }}</p>
                </div>
                <div class="rounded-lg border bg-muted/30 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Saldo do empenho</p>
                    <p :class="['mt-1 text-lg font-semibold tabular-nums', saldoEmpenho.saldo <= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400']">
                        {{ brl(saldoEmpenho.saldo) }}
                    </p>
                </div>
            </template>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3 text-right">Pedido</th>
                        <th class="px-4 py-3 text-right">Entregue</th>
                        <th class="px-4 py-3 text-right">Vl. unitário</th>
                        <th class="px-4 py-3 text-right">Vl. total</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="linhas.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                            Nenhum item nesta ordem.
                        </td>
                    </tr>
                    <tr v-for="linha in linhas" :key="linha.ofi_id" class="transition-colors hover:bg-muted/30">
                        <td class="px-4 py-3 font-medium">{{ linha.item_nome }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ qtd(linha.ofi_qtd) }} {{ linha.umd_sigla }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                            {{ linha.ofi_qtd_entregue > 0 ? qtd(linha.ofi_qtd_entregue) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ brl(linha.ofi_vl_unitario) }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums">{{ brl(linha.vl_total) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end">
                                <Button
                                    v-if="!isReadOnly"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="!editavel || linha.ofi_qtd_entregue > 0"
                                    :title="linha.ofi_qtd_entregue > 0 ? 'Item já tem entrega registrada' : 'Remover'"
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

        <fieldset v-if="!isReadOnly && editavel" :disabled="form.processing" class="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-8 sm:items-start">
            <div class="grid gap-2 sm:col-span-4">
                <FormLabel for="cti_id" :required="true">Item do contrato</FormLabel>
                <select id="cti_id" v-model="form.cti_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option :value="null" disabled>Selecione o item...</option>
                    <option
                        v-for="i in itensContrato"
                        :key="i.cti_id"
                        :value="i.cti_id"
                        :disabled="i.qtd_saldo <= 0"
                    >
                        {{ i.item_nome }} — saldo: {{ qtd(i.qtd_saldo) }} {{ i.umd_sigla }}
                    </option>
                </select>
                <p v-if="itemEscolhido" class="text-xs text-muted-foreground">
                    Disponível no contrato: {{ qtd(itemEscolhido.qtd_saldo) }} {{ itemEscolhido.umd_sigla }}
                    · preço contratado: {{ brl(itemEscolhido.cti_vl_unitario) }}
                </p>
                <InputError :message="form.errors.cti_id" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="ofi_qtd" :required="true">Quantidade</FormLabel>
                <Input id="ofi_qtd" type="number" step="0.0001" min="0" inputmode="decimal" v-model.number="form.ofi_qtd" />
                <p v-if="valorPrevisto > 0" class="text-xs text-muted-foreground">{{ brl(valorPrevisto) }}</p>
                <InputError :message="form.errors.ofi_qtd" />
            </div>

            <div class="grid gap-2 sm:col-span-1">
                <FormLabel for="ofi_vl_unitario" :required="true">Vl. unit.</FormLabel>
                <Input id="ofi_vl_unitario" type="number" step="0.0001" min="0" inputmode="decimal" v-model.number="form.ofi_vl_unitario" />
                <InputError :message="form.errors.ofi_vl_unitario" />
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
