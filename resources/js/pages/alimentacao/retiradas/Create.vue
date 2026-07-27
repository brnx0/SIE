<script setup lang="ts">
import EscolaCombobox from '@/components/funcionario/EscolaCombobox.vue';
import RemoteCombobox from '@/components/common/RemoteCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { BreadcrumbItem } from '@/types';
import type { ItemComSaldo } from '@/types/alimentacao';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PackageMinus, Plus, Trash2, TriangleAlert } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    /** Escola do estoque em uso; nula = estoque central. */
    escolaAtual: { esc_id: number; esc_nome: string } | null;
    podeVerCentral: boolean;
    tiposRetirada: { tpr_id: number; tpr_nome: string }[];
    itens: ItemComSaldo[];
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

interface Linha { itm_id: number; qtd: number; umd_id: number }

const form = useForm<Record<string, any>>({
    esc_id: (props.escolaAtual?.esc_id ?? null) as number | null,
    tpr_id: null as number | null,
    fun_id: null as number | null,
    responsavel: '',
    dt: new Date().toISOString().slice(0, 10),
    obs: '',
    itens: [] as Linha[],
});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Retirada de Itens', href: '/alimentacao/retiradas' }];

// Central e escola são dois estoques distintos — ver nota em estoque/Index.vue.
const modo = ref<'central' | 'escola'>(props.escolaAtual ? 'escola' : 'central');
watch(modo, (m) => {
    if (m === 'central') form.esc_id = null;
});

// Trocar de estoque muda a lista de itens com saldo: recarrega e zera o carrinho,
// senão sobraria item que não existe no novo estoque.
watch(() => form.esc_id, (novo, antigo) => {
    if (antigo === undefined || novo === antigo) return;
    form.itens = [];
    router.get('/alimentacao/retiradas/create', { esc_id: novo ?? '' }, { preserveState: true, replace: true });
});

const itmId = ref<number | null>(null);
const qtd   = ref<number | string>('');
const responsavel = ref<{ id: number; label: string } | null>(null);

// O responsável pode ser um funcionário cadastrado ou, quando não houver, um nome
// digitado à mão. Só um dos dois vai no payload — o outro é sempre zerado.
const naoCadastrado = ref(false);
watch(naoCadastrado, (livre) => {
    if (livre) {
        form.fun_id = null;
        responsavel.value = null;
    } else {
        form.responsavel = '';
    }
});

const itemEscolhido = computed(() => props.itens.find((i) => i.itm_id === itmId.value));

const jaNoCarrinho = (id: number) =>
    (form.itens as Linha[]).filter((l: Linha) => l.itm_id === id).reduce((s: number, l: Linha) => s + Number(l.qtd), 0);

const disponivel = computed(() =>
    itemEscolhido.value ? itemEscolhido.value.saldo - jaNoCarrinho(itemEscolhido.value.itm_id) : 0,
);

const erroLinha = ref<string | null>(null);

const adicionar = () => {
    erroLinha.value = null;
    const item = itemEscolhido.value;
    const q = Number(qtd.value);

    if (!item || !q || q <= 0) {
        erroLinha.value = 'Escolha o item e informe uma quantidade maior que zero.';
        return;
    }
    if (q > disponivel.value) {
        erroLinha.value = `Há ${fmt(disponivel.value)} ${item.umd_sigla} deste item em estoque${
            jaNoCarrinho(item.itm_id) > 0 ? ' (descontando o que já está nesta retirada)' : ''
        }.`;
        return;
    }

    form.itens.push({ itm_id: item.itm_id, qtd: q, umd_id: item.itm_umd_id });
    itmId.value = null;
    qtd.value = '';
};

const remover = (i: number) => form.itens.splice(i, 1);

const nomeDe = (id: number) => props.itens.find((i) => i.itm_id === id)?.itm_nome ?? '';
const siglaDe = (id: number) => props.itens.find((i) => i.itm_id === id)?.umd_sigla ?? '';
const fmt = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

const submit = () => {
    if (isReadOnly.value) return;
    form.post('/alimentacao/retiradas', {
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    });
};
</script>

<template>
    <Head title="Retirada de Itens" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Retirada de Itens</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Baixa de item que sai do estoque e não volta — uso na cozinha, descarte, vencimento.
                        Os lotes saem por validade mais próxima (FEFO).
                    </p>
                </div>
                <Link href="/alimentacao/estoque">
                    <Button type="button" variant="outline"><ChevronLeft class="mr-1 size-4" /> Ver estoque</Button>
                </Link>
            </div>

            <div
                v-if="itens.length === 0"
                class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <p>Este estoque não tem saldo de nenhum item.</p>
            </div>

            <fieldset v-else :disabled="isReadOnly || form.processing" class="contents">
                <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel>Estoque</FormLabel>
                        <div v-if="podeVerCentral" class="flex items-center gap-1 rounded-lg border bg-muted/40 p-1">
                            <button
                                type="button"
                                :class="['flex-1 rounded-md px-3 py-1.5 text-xs font-medium transition-colors', modo === 'central' ? 'bg-indigo-600 text-white' : 'text-muted-foreground hover:bg-muted']"
                                @click="modo = 'central'"
                            >
                                Central
                            </button>
                            <button
                                type="button"
                                :class="['flex-1 rounded-md px-3 py-1.5 text-xs font-medium transition-colors', modo === 'escola' ? 'bg-indigo-600 text-white' : 'text-muted-foreground hover:bg-muted']"
                                @click="modo = 'escola'"
                            >
                                Escola
                            </button>
                        </div>
                        <EscolaCombobox
                            v-if="!podeVerCentral || modo === 'escola'"
                            v-model="form.esc_id"
                            :initial="escolaAtual"
                            placeholder="Buscar escola..."
                            :invalid="!!form.errors.esc_id"
                        />
                        <InputError :message="form.errors.esc_id" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel for="tpr_id" :required="true">Tipo de retirada</FormLabel>
                        <select id="tpr_id" v-model="form.tpr_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                            <option :value="null" disabled>Selecione o motivo...</option>
                            <option v-for="t in tiposRetirada" :key="t.tpr_id" :value="t.tpr_id">{{ t.tpr_nome }}</option>
                        </select>
                        <InputError :message="form.errors.tpr_id" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel for="dt" :required="true">Data</FormLabel>
                        <Input id="dt" type="date" v-model="form.dt" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                        <InputError :message="form.errors.dt" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <div class="flex items-center justify-between gap-2">
                            <FormLabel>Responsável pela retirada</FormLabel>
                            <label class="flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground">
                                <input type="checkbox" v-model="naoCadastrado" class="size-3.5 rounded border-input" />
                                Não cadastrado no sistema
                            </label>
                        </div>
                        <RemoteCombobox
                            v-if="!naoCadastrado"
                            v-model="form.fun_id"
                            fetch-url="/api/funcionarios"
                            id-key="fun_id"
                            label-key="fun_nome"
                            placeholder="Buscar funcionário..."
                            :invalid="!!form.errors.fun_id"
                            :selected-label="responsavel?.label ?? null"
                            @selected="responsavel = $event"
                        />
                        <Input
                            v-else
                            v-model="form.responsavel"
                            maxlength="120"
                            placeholder="Nome de quem retirou"
                            :class="form.errors.responsavel ? 'border-red-500' : ''"
                        />
                        <InputError :message="form.errors.fun_id || form.errors.responsavel" />
                    </div>

                    <div class="grid gap-2 sm:col-span-3">
                        <FormLabel for="obs">Observação</FormLabel>
                        <Input id="obs" v-model="form.obs" placeholder="Opcional" />
                        <InputError :message="form.errors.obs" />
                    </div>
                </div>

                <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
                    <div>
                        <h2 class="text-lg font-semibold">Itens retirados</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            O saldo mostrado já desconta o que está nesta retirada.
                        </p>
                    </div>

                    <div class="overflow-hidden rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3 text-right">Quantidade</th>
                                    <th class="px-4 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-if="form.itens.length === 0">
                                    <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">
                                        Nenhum item nesta retirada.
                                    </td>
                                </tr>
                                <tr v-for="(l, i) in form.itens" :key="i" class="transition-colors hover:bg-muted/30">
                                    <td class="px-4 py-3 font-medium">{{ nomeDe(l.itm_id) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ fmt(l.qtd) }} {{ siglaDe(l.itm_id) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end">
                                            <Button type="button" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remover(i)">
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <InputError :message="form.errors.itens" />

                    <div class="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-6 sm:items-start">
                        <div class="grid gap-2 sm:col-span-4">
                            <FormLabel for="itm_id">Item</FormLabel>
                            <select id="itm_id" v-model="itmId" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                                <option :value="null" disabled>Selecione o item...</option>
                                <option v-for="i in itens" :key="i.itm_id" :value="i.itm_id">
                                    {{ i.itm_nome }} — saldo: {{ fmt(i.saldo) }} {{ i.umd_sigla }}
                                </option>
                            </select>
                            <p v-if="itemEscolhido" class="text-xs text-muted-foreground">
                                Disponível: {{ fmt(disponivel) }} {{ itemEscolhido.umd_sigla }}
                            </p>
                        </div>

                        <div class="grid gap-2 sm:col-span-1">
                            <FormLabel for="qtd">Quantidade</FormLabel>
                            <Input id="qtd" type="number" step="0.0001" min="0" inputmode="decimal" v-model.number="qtd" />
                        </div>

                        <div class="flex sm:col-span-1 sm:pt-7">
                            <Button type="button" class="w-full bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400" @click="adicionar">
                                <Plus class="mr-2 size-4" /> Incluir
                            </Button>
                        </div>

                        <p v-if="erroLinha" class="text-sm text-rose-600 dark:text-rose-400 sm:col-span-6">{{ erroLinha }}</p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button
                        type="button"
                        :disabled="form.processing || form.itens.length === 0 || !form.tpr_id"
                        class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        @click="submit"
                    >
                        <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        <PackageMinus v-else class="mr-2 size-4" />
                        Registrar retirada
                    </Button>
                </div>
            </fieldset>
        </div>
    </AppLayout>
</template>
