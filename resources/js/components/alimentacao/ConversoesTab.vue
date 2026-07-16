<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import type { UnidadeMedida } from '@/types/alimentacao';
import { router, useForm } from '@inertiajs/vue3';
import { ArrowRight, LoaderCircle, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface ConversaoLinha {
    cvu_id: number;
    cvu_umd_destino_id: number;
    cvu_fator: number;
    cvu_fl_ativo: boolean;
    destino_sigla: string | null;
    destino_nome: string | null;
}

const props = defineProps<{
    unidade: UnidadeMedida;
    conversoes: ConversaoLinha[];
    unidadesDestino: Pick<UnidadeMedida, 'umd_id' | 'umd_sigla' | 'umd_nome'>[];
}>();

const { isReadOnly } = usePermissao();

// Renderiza direto do prop — cópia em ref fica obsoleta após o reload do Inertia.
const linhas = computed(() => props.conversoes ?? []);

const base = computed(() => `/alimentacao/unidades-medida/${props.unidade.umd_id}/conversoes`);

const form = useForm<{
    cvu_umd_destino_id: number | null;
    cvu_fator: number | string;
    cvu_fl_ativo: boolean;
}>({
    cvu_umd_destino_id: null,
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

const remover = (linha: ConversaoLinha) => {
    if (!confirm(`Remover a conversão ${props.unidade.umd_sigla} → ${linha.destino_sigla}?`)) return;
    router.delete(`${base.value}/${linha.cvu_id}`, { preserveScroll: true, preserveState: true });
};

// Ex.: 1 CX = 30 KG. Mostra o efeito da regra em linguagem de quem cadastra.
const exemplo = computed(() => {
    const destino = props.unidadesDestino.find((u) => u.umd_id === form.cvu_umd_destino_id);
    if (!destino || !form.cvu_fator) return null;
    return `1 ${props.unidade.umd_sigla} = ${form.cvu_fator} ${destino.umd_sigla}`;
});
</script>

<template>
    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Conversões globais</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Regras que valem para <strong>qualquer item</strong> (ex.: 1 KG = 1000 G). Conversão que muda de item
                    para item — como quantos quilos vêm numa caixa — é cadastrada na tela do próprio item.
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Não é preciso cadastrar o sentido contrário: o sistema converte de volta sozinho.
                </p>
            </div>
            <RefreshButton />
        </div>

        <div class="overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3">Conversão</th>
                        <th class="px-4 py-3 text-right">Fator</th>
                        <th class="px-4 py-3 text-center">Situação</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="linhas.length === 0">
                        <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                            Nenhuma conversão cadastrada para esta unidade.
                        </td>
                    </tr>
                    <tr v-for="linha in linhas" :key="linha.cvu_id" class="transition-colors hover:bg-muted/30">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 font-medium">
                                <span>{{ unidade.umd_sigla }}</span>
                                <ArrowRight class="size-3.5 text-muted-foreground" />
                                <span>{{ linha.destino_sigla }}</span>
                                <span class="text-xs font-normal text-muted-foreground">({{ linha.destino_nome }})</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            1 {{ unidade.umd_sigla }} = {{ linha.cvu_fator }} {{ linha.destino_sigla }}
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
                <FormLabel for="cvu_umd_destino_id" :required="true">Converter para</FormLabel>
                <select
                    id="cvu_umd_destino_id"
                    v-model="form.cvu_umd_destino_id"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                >
                    <option :value="null" disabled>Selecione a unidade de destino...</option>
                    <option v-for="u in unidadesDestino" :key="u.umd_id" :value="u.umd_id">
                        {{ u.umd_sigla }} — {{ u.umd_nome }}
                    </option>
                </select>
                <InputError :message="form.errors.cvu_umd_destino_id" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="cvu_fator" :required="true">Fator</FormLabel>
                <Input
                    id="cvu_fator"
                    type="number"
                    step="0.000001"
                    min="0"
                    inputmode="decimal"
                    v-model.number="form.cvu_fator"
                    placeholder="1000"
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
