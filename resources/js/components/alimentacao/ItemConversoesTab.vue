<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import type { Item, ItemConversaoLinha, UnidadeMedida } from '@/types/alimentacao';
import { router, useForm } from '@inertiajs/vue3';
import { ArrowRight, LoaderCircle, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    item: Item;
    conversoes: ItemConversaoLinha[];
    unidades: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
}>();

const { isReadOnly } = usePermissao();

// Renderiza direto do prop — cópia em ref fica obsoleta após o reload do Inertia.
const linhas = computed(() => props.conversoes ?? []);

const base = computed(() => `/alimentacao/itens/${props.item.itm_id}/conversoes`);

const unidadeBase = computed(() => props.unidades.find((u) => u.umd_id === props.item.itm_umd_id));

// A unidade de controle não pode ser origem de si mesma.
const origensDisponiveis = computed(() =>
    props.unidades.filter((u) => u.umd_id !== props.item.itm_umd_id),
);

const form = useForm<{
    cvu_umd_origem_id: number | null;
    cvu_fator: number | string;
    cvu_fl_ativo: boolean;
}>({
    cvu_umd_origem_id: null,
    cvu_fator: '',
    cvu_fl_ativo: true,
});

const adicionar = () => {
    if (isReadOnly.value) return;
    form.post(base.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => form.reset(),
    });
};

const remover = (linha: ItemConversaoLinha) => {
    if (!confirm(`Remover a conversão ${linha.origem_sigla} → ${unidadeBase?.value?.umd_sigla}?`)) return;
    router.delete(`${base.value}/${linha.cvu_id}`, { preserveScroll: true, preserveState: true });
};

const exemplo = computed(() => {
    const origem = origensDisponiveis.value.find((u) => u.umd_id === form.cvu_umd_origem_id);
    if (!origem || !form.cvu_fator || !unidadeBase.value) return null;
    return `1 ${origem.umd_sigla} de ${props.item.itm_nome} = ${form.cvu_fator} ${unidadeBase.value.umd_sigla}`;
});
</script>

<template>
    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Conversões deste item</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Quanto rende cada embalagem deste item na unidade de controle
                    <strong>{{ unidadeBase?.umd_sigla ?? '—' }}</strong> — ex.: 1 caixa = 30 KG. É o que permite
                    receber em caixa e dar baixa em quilo sem o saldo ficar torto.
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Conversões que valem para qualquer item (1 KG = 1000 G) ficam na tela da unidade de medida.
                    Não é preciso cadastrar o sentido contrário.
                </p>
            </div>
            <RefreshButton />
        </div>

        <div class="overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3">Conversão</th>
                        <th class="px-4 py-3 text-right">Equivalência</th>
                        <th class="px-4 py-3 text-center">Situação</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="linhas.length === 0">
                        <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                            Nenhuma conversão específica cadastrada para este item.
                        </td>
                    </tr>
                    <tr v-for="linha in linhas" :key="linha.cvu_id" class="transition-colors hover:bg-muted/30">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 font-medium">
                                <span>{{ linha.origem_sigla }}</span>
                                <ArrowRight class="size-3.5 text-muted-foreground" />
                                <span>{{ unidadeBase?.umd_sigla }}</span>
                                <span class="text-xs font-normal text-muted-foreground">({{ linha.origem_nome }})</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            1 {{ linha.origem_sigla }} = {{ linha.cvu_fator }} {{ unidadeBase?.umd_sigla }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', linha.cvu_fl_ativo ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                {{ linha.cvu_fl_ativo ? 'Ativa' : 'Inativa' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end">
                                <Button
                                    v-if="!isReadOnly"
                                    variant="ghost"
                                    size="sm"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
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

        <fieldset v-if="!isReadOnly" :disabled="form.processing" class="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-6 sm:items-start">
            <div class="grid gap-2 sm:col-span-3">
                <FormLabel for="cvu_umd_origem_id" :required="true">Embalagem / unidade recebida</FormLabel>
                <select
                    id="cvu_umd_origem_id"
                    v-model="form.cvu_umd_origem_id"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                >
                    <option :value="null" disabled>Selecione a unidade...</option>
                    <option v-for="u in origensDisponiveis" :key="u.umd_id" :value="u.umd_id">
                        {{ u.umd_sigla }} — {{ u.umd_nome }}
                    </option>
                </select>
                <InputError :message="form.errors.cvu_umd_origem_id" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="cvu_fator" :required="true">Equivale a ({{ unidadeBase?.umd_sigla ?? '—' }})</FormLabel>
                <Input
                    id="cvu_fator"
                    type="number"
                    step="0.000001"
                    min="0"
                    inputmode="decimal"
                    v-model.number="form.cvu_fator"
                    placeholder="30"
                />
                <InputError :message="form.errors.cvu_fator" />
                <p v-if="exemplo" class="text-xs text-muted-foreground">{{ exemplo }}</p>
            </div>

            <div class="flex sm:col-span-1 sm:pt-7">
                <Button
                    type="button"
                    class="w-full bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    @click="adicionar"
                >
                    <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                    <Plus v-else class="mr-2 size-4" />
                    Adicionar
                </Button>
            </div>
        </fieldset>
    </div>
</template>
