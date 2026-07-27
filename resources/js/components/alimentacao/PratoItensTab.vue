<script setup lang="ts">
import RemoteCombobox from '@/components/common/RemoteCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import type { Prato, PratoItemLinha, UnidadeMedida } from '@/types/alimentacao';
import { router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Trash2, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    prato: Prato;
    itens: PratoItemLinha[];
    unidades: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
}>();

const { isReadOnly } = usePermissao();

// Renderiza direto do prop — cópia em ref fica obsoleta após o reload do Inertia.
const linhas = computed(() => props.itens ?? []);

const base = computed(() => `/alimentacao/pratos/${props.prato.prt_id}/itens`);

const form = useForm<{
    pri_itm_id: number | null;
    pri_umd_id: number | null;
    pri_qtd_per_capita: number | string;
}>({
    pri_itm_id: null,
    pri_umd_id: null,
    pri_qtd_per_capita: '',
});

// Ao escolher o item, já sugere a unidade de controle dele — é a que sempre
// converte, então o caminho feliz não esbarra na validação de conversão.
const itemSelecionado = ref<{ id: number; label: string } | null>(null);
const onItemSelected = async (item: { id: number; label: string } | null) => {
    itemSelecionado.value = item;
    if (!item) return;

    const res = await fetch(`/api/alimentacao/itens/search?incluir_ids=${item.id}&q=`, {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) return;
    const encontrados = await res.json();
    const achado = encontrados.find((i: { itm_id: number }) => i.itm_id === item.id);
    if (achado && !form.pri_umd_id) form.pri_umd_id = achado.itm_umd_id;
};

const adicionar = () => {
    if (isReadOnly.value) return;
    form.post(base.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            itemSelecionado.value = null;
        },
    });
};

const remover = (linha: PratoItemLinha) => {
    if (!confirm(`Remover "${linha.item_nome}" da ficha técnica?`)) return;
    router.delete(`${base.value}/${linha.pri_id}`, { preserveScroll: true, preserveState: true });
};

const formatar = (n: number) => String(n).replace('.', ',');
</script>

<template>
    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Ficha técnica</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Itens que compõem o prato e quanto de cada um vai <strong>por aluno</strong>. É daqui que saem o
                    cálculo da demanda (alunos × per capita × dias) e a baixa de estoque a cada preparação.
                </p>
            </div>
            <RefreshButton />
        </div>

        <div
            v-if="linhas.length === 0"
            class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            <p>
                Sem ficha técnica, este prato não calcula demanda nem dá baixa no estoque quando for preparado.
                Ele até pode entrar no cardápio, mas não vai movimentar nada.
            </p>
        </div>

        <div v-else class="overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3 text-right">Por aluno</th>
                        <th class="px-4 py-3 text-center">Controlado em</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="linha in linhas" :key="linha.pri_id" class="transition-colors hover:bg-muted/30">
                        <td class="px-4 py-3 font-medium">{{ linha.item_nome }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ formatar(linha.pri_qtd_per_capita) }} {{ linha.umd_sigla }}
                        </td>
                        <td class="px-4 py-3 text-center text-muted-foreground">{{ linha.item_umd_sigla }}</td>
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
                <FormLabel :required="true">Item</FormLabel>
                <RemoteCombobox
                    v-model="form.pri_itm_id"
                    fetch-url="/api/alimentacao/itens/search"
                    id-key="itm_id"
                    label-key="itm_nome"
                    placeholder="Buscar item..."
                    :invalid="!!form.errors.pri_itm_id"
                    :selected-label="itemSelecionado?.label ?? null"
                    @selected="onItemSelected"
                />
                <InputError :message="form.errors.pri_itm_id" />
            </div>

            <div class="grid gap-2 sm:col-span-1">
                <FormLabel for="pri_qtd_per_capita" :required="true">Por aluno</FormLabel>
                <Input
                    id="pri_qtd_per_capita"
                    type="number"
                    step="0.0001"
                    min="0"
                    inputmode="decimal"
                    v-model.number="form.pri_qtd_per_capita"
                    placeholder="0,1"
                />
                <InputError :message="form.errors.pri_qtd_per_capita" />
            </div>

            <div class="grid gap-2 sm:col-span-1">
                <FormLabel for="pri_umd_id" :required="true">Unidade</FormLabel>
                <select
                    id="pri_umd_id"
                    v-model="form.pri_umd_id"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                >
                    <option :value="null" disabled>...</option>
                    <option v-for="u in unidades" :key="u.umd_id" :value="u.umd_id">{{ u.umd_sigla }}</option>
                </select>
                <InputError :message="form.errors.pri_umd_id" />
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
