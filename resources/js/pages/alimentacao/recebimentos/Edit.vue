<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import type { BreadcrumbItem } from '@/types';
import type { RecebimentoItemLinha } from '@/types/alimentacao';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, ChevronLeft, CircleCheck, Lock, LoaderCircle, PackageCheck, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Cabecalho {
    rcb_id: number;
    rcb_dt: string | null;
    rcb_tipo: string;
    rcb_situacao: string;
    rcb_nr_nota: string | null;
    rcb_obs: string | null;
    ordem_numero: string | null;
    contrato: string | null;
    fornecedor_nome: string | null;
}

const props = defineProps<{
    recebimento: Cabecalho;
    itens: RecebimentoItemLinha[];
    marcas: { mrc_id: number; mrc_nome: string }[];
    emConferencia: boolean;
}>();

const { isReadOnly } = usePermissao();

// Renderiza direto do prop — cópia em ref fica obsoleta após o reload do Inertia.
const linhas = computed(() => props.itens ?? []);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Recebimento de Itens', href: '/alimentacao/recebimentos' },
    { title: props.recebimento.ordem_numero ?? 'Recebimento', href: `/alimentacao/recebimentos/${props.recebimento.rcb_id}/edit` },
]);

const qtd = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });
const brl = (v: number | null) => (v === null ? '—' : v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }));
const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

// Conferência item a item: cada linha abre seu próprio painel.
const conferindoId = ref<number | null>(null);

const formItem = useForm<Record<string, any>>({
    rci_qtd_recebida: '',
    rci_qtd_rejeitada: 0,
    rci_motivo_rejeicao: '',
    lote_codigo: '',
    lote_validade: '',
    lote_mrc_id: null,
    _method: 'put',
});

const abrirConferencia = (l: RecebimentoItemLinha) => {
    conferindoId.value = l.rci_id;
    formItem.clearErrors();
    formItem.rci_qtd_recebida    = l.qtd_recebida || l.qtd_prevista;
    formItem.rci_qtd_rejeitada   = l.qtd_rejeitada || 0;
    formItem.rci_motivo_rejeicao = l.motivo_rejeicao ?? '';
    formItem.lote_codigo         = l.lote_codigo ?? '';
    formItem.lote_validade       = l.lote_validade ?? '';
    formItem.lote_mrc_id         = l.lote_mrc_id ?? null;
};

const salvarItem = (l: RecebimentoItemLinha) => {
    formItem.post(`/alimentacao/recebimentos/${props.recebimento.rcb_id}/itens/${l.rci_id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { conferindoId.value = null; },
    });
};

// Sugere a validade a partir do prazo do item, quando cadastrado.
const sugerirValidade = (l: RecebimentoItemLinha) => {
    if (!l.validade_dias) return;
    const d = new Date();
    d.setDate(d.getDate() + l.validade_dias);
    formItem.lote_validade = d.toISOString().slice(0, 10);
};

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

const formAceite = useForm({});
const aceitar = () => {
    if (!confirm('Aceitar o recebimento? Os itens conferidos vão dar entrada no estoque central, e isso não pode ser desfeito por aqui.')) return;
    formAceite.post(`/alimentacao/recebimentos/${props.recebimento.rcb_id}/aceitar`, { preserveScroll: true });
};

const conferidos = computed(() => linhas.value.filter((l) => l.qtd_recebida > 0 || l.qtd_rejeitada > 0).length);
const totalEntrada = computed(() =>
    linhas.value.reduce((s, l) => s + l.qtd_recebida * (l.vl_unitario ?? 0), 0),
);
const semLote = computed(() => linhas.value.some((l) => l.qtd_recebida > 0 && !l.lot_id));
</script>

<template>
    <Head :title="`Recebimento ${recebimento.ordem_numero}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        Recebimento — ordem {{ recebimento.ordem_numero }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ recebimento.fornecedor_nome }}
                        <template v-if="recebimento.contrato"> · Contrato {{ recebimento.contrato }}</template>
                        · {{ dataBr(recebimento.rcb_dt) }}
                        <template v-if="recebimento.rcb_nr_nota"> · Nota {{ recebimento.rcb_nr_nota }}</template>
                    </p>
                </div>
                <Link href="/alimentacao/recebimentos">
                    <Button type="button" variant="outline"><ChevronLeft class="mr-1 size-4" /> Voltar</Button>
                </Link>
            </div>

            <!-- Situação -->
            <div
                v-if="!emConferencia"
                class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200"
            >
                <CircleCheck class="mt-0.5 size-4 shrink-0" />
                <p>
                    Recebimento <strong>{{ recebimento.rcb_situacao }}</strong>. Os itens conferidos já deram entrada no
                    estoque central e não podem mais ser alterados — para corrigir, estorne os movimentos no histórico
                    de movimentação.
                </p>
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Itens conferidos</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ conferidos }} / {{ linhas.length }}</p>
                </div>
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Valor a dar entrada</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ brl(totalEntrada) }}</p>
                </div>
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Tipo</p>
                    <p class="mt-1 text-lg font-semibold">
                        {{ recebimento.rcb_tipo === 'AGRICULTURA_FAMILIAR' ? 'Agricultura familiar' : 'Ordinário' }}
                    </p>
                </div>
            </div>

            <!-- Itens -->
            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Conferência</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Informe quanto chegou de cada item e o lote. Só o que for aceito entra no estoque —
                            o rejeitado fica registrado com o motivo, mas não vira saldo.
                        </p>
                    </div>
                    <RefreshButton />
                </div>

                <div
                    v-if="emConferencia && semLote"
                    class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <p>Há item recebido sem lote informado. Sem lote não há controle de validade nem baixa por FEFO.</p>
                </div>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3 text-right">Previsto</th>
                                <th class="px-4 py-3 text-right">Recebido</th>
                                <th class="px-4 py-3 text-right">Rejeitado</th>
                                <th class="px-4 py-3">Lote</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template v-for="l in linhas" :key="l.rci_id">
                                <tr class="transition-colors hover:bg-muted/30">
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col">
                                            <span class="font-medium">{{ l.item_nome }}</span>
                                            <span v-if="l.item_perecivel" class="text-xs text-amber-600 dark:text-amber-400">
                                                Perecível<template v-if="l.validade_dias"> · validade {{ l.validade_dias }}d</template>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                        {{ qtd(l.qtd_prevista) }} {{ l.umd_sigla }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <span :class="l.qtd_recebida > 0 && 'font-medium text-emerald-700 dark:text-emerald-400'">
                                            {{ l.qtd_recebida > 0 ? qtd(l.qtd_recebida) : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <div v-if="l.qtd_rejeitada > 0" class="flex flex-col items-end">
                                            <span class="font-medium text-rose-600 dark:text-rose-400">{{ qtd(l.qtd_rejeitada) }}</span>
                                            <span class="text-xs text-muted-foreground">{{ l.motivo_rejeicao }}</span>
                                        </div>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        <div v-if="l.lot_id" class="flex flex-col">
                                            <span>{{ l.lote_codigo ?? 'sem código' }}</span>
                                            <span v-if="l.lote_validade" class="text-xs tabular-nums">Val.: {{ dataBr(l.lote_validade) }}</span>
                                        </div>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end">
                                            <Button
                                                v-if="!isReadOnly && emConferencia"
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="abrirConferencia(l)"
                                            >
                                                <Check class="mr-1 size-3.5" /> Conferir
                                            </Button>
                                            <Lock v-else-if="!emConferencia" class="size-4 text-muted-foreground" />
                                        </div>
                                    </td>
                                </tr>

                                <!-- Painel de conferência do item -->
                                <tr v-if="conferindoId === l.rci_id">
                                    <td colspan="6" class="bg-muted/30 px-4 py-4">
                                        <div class="grid gap-4 sm:grid-cols-6 sm:items-start">
                                            <div class="sm:col-span-6">
                                                <p class="text-sm font-medium">{{ l.item_nome }}</p>
                                                <p class="text-xs text-muted-foreground">
                                                    Previsto nesta ordem: {{ qtd(l.qtd_prevista) }} {{ l.umd_sigla }}
                                                    <template v-if="l.vl_unitario"> · {{ brl(l.vl_unitario) }} por {{ l.umd_sigla }}</template>
                                                </p>
                                            </div>

                                            <div class="grid gap-2 sm:col-span-1">
                                                <FormLabel for="rci_qtd_recebida" :required="true">Recebido</FormLabel>
                                                <Input id="rci_qtd_recebida" type="number" step="0.0001" min="0" inputmode="decimal" v-model.number="formItem.rci_qtd_recebida" />
                                                <InputError :message="formItem.errors.rci_qtd_recebida" />
                                            </div>

                                            <div class="grid gap-2 sm:col-span-1">
                                                <FormLabel for="rci_qtd_rejeitada">Rejeitado</FormLabel>
                                                <Input id="rci_qtd_rejeitada" type="number" step="0.0001" min="0" inputmode="decimal" v-model.number="formItem.rci_qtd_rejeitada" />
                                                <InputError :message="formItem.errors.rci_qtd_rejeitada" />
                                            </div>

                                            <div class="grid gap-2 sm:col-span-4">
                                                <FormLabel for="rci_motivo_rejeicao" :required="Number(formItem.rci_qtd_rejeitada) > 0">
                                                    Motivo da rejeição
                                                </FormLabel>
                                                <Input
                                                    id="rci_motivo_rejeicao"
                                                    v-model="formItem.rci_motivo_rejeicao"
                                                    maxlength="120"
                                                    :disabled="Number(formItem.rci_qtd_rejeitada) <= 0"
                                                    placeholder="Ex.: embalagem violada, produto fora do padrão"
                                                />
                                                <InputError :message="formItem.errors.rci_motivo_rejeicao" />
                                            </div>

                                            <div class="grid gap-2 sm:col-span-2">
                                                <FormLabel for="lote_codigo">Lote</FormLabel>
                                                <Input id="lote_codigo" v-model="formItem.lote_codigo" maxlength="40" placeholder="Código do lote" />
                                                <InputError :message="formItem.errors.lote_codigo" />
                                            </div>

                                            <div class="grid gap-2 sm:col-span-2">
                                                <FormLabel for="lote_validade">Validade</FormLabel>
                                                <div class="flex items-center gap-2">
                                                    <Input id="lote_validade" type="date" v-model="formItem.lote_validade" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                                                    <Button
                                                        v-if="l.validade_dias"
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        :title="`Hoje + ${l.validade_dias} dias, conforme o prazo cadastrado no item`"
                                                        @click="sugerirValidade(l)"
                                                    >
                                                        +{{ l.validade_dias }}d
                                                    </Button>
                                                </div>
                                                <InputError :message="formItem.errors.lote_validade" />
                                            </div>

                                            <div class="grid gap-2 sm:col-span-2">
                                                <FormLabel for="lote_mrc_id">Marca</FormLabel>
                                                <select id="lote_mrc_id" v-model="formItem.lote_mrc_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                                                    <option :value="null">—</option>
                                                    <option v-for="m in marcas" :key="m.mrc_id" :value="m.mrc_id">{{ m.mrc_nome }}</option>
                                                </select>
                                                <InputError :message="formItem.errors.lote_mrc_id" />
                                            </div>

                                            <div class="flex items-center gap-2 sm:col-span-6">
                                                <Button
                                                    type="button"
                                                    :disabled="formItem.processing"
                                                    class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                                                    @click="salvarItem(l)"
                                                >
                                                    <LoaderCircle v-if="formItem.processing" class="mr-2 size-4 animate-spin" />
                                                    <Check v-else class="mr-2 size-4" />
                                                    Salvar conferência
                                                </Button>
                                                <Button type="button" variant="outline" @click="conferindoId = null">Cancelar</Button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Aceite -->
            <div v-if="emConferencia && !isReadOnly" class="flex items-center justify-between rounded-xl border bg-card p-6 shadow-sm">
                <div>
                    <p class="text-sm font-medium">Aceitar o recebimento</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Dá entrada no estoque central do que foi conferido e atualiza o entregue na ordem. Não dá para desfazer por aqui.
                    </p>
                </div>
                <Button
                    type="button"
                    :disabled="formAceite.processing || conferidos === 0"
                    :title="conferidos === 0 ? 'Confira ao menos um item antes de aceitar' : ''"
                    class="bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-40"
                    @click="aceitar"
                >
                    <LoaderCircle v-if="formAceite.processing" class="mr-2 size-4 animate-spin" />
                    <PackageCheck v-else class="mr-2 size-4" />
                    Aceitar e dar entrada
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
