<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useErrorFocus } from '@/composables/useErrorFocus';
import { usePermissao } from '@/composables/usePermissao';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Loader2, RotateCcw, Save, Tags } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface Termo {
    opcao: string;
    descricao: string;
    sigla: string;
    descricao_padrao: string;
    sigla_padrao: string;
    updated_at: string | null;
    updated_by: string | null;
}

const props = defineProps<{ termos: Termo[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Termos da Avaliação Diagnóstica', href: '/diario/termos-diagnostico' },
];

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

// Chaves planas (descricao_<opcao>, sigla_<opcao>) = id do input = chave do erro.
const valoresDe = (termos: Termo[]) =>
    Object.fromEntries(termos.flatMap((t) => [[`descricao_${t.opcao}`, t.descricao], [`sigla_${t.opcao}`, t.sigla]]));

const form = useForm<Record<string, string>>(valoresDe(props.termos));

// Recarga (RefreshButton / pós-gravação) traz os valores do servidor. Só quando
// eles MUDAM: no erro de validação o redirect devolve os mesmos termos e o que o
// usuário digitou precisa continuar no form.
let ultimoServidor = JSON.stringify(valoresDe(props.termos));
watch(() => props.termos, (t) => {
    const v = valoresDe(t);
    const serializado = JSON.stringify(v);
    if (serializado === ultimoServidor) return;
    ultimoServidor = serializado;
    Object.assign(form, v);
    form.defaults(v);
});

// Mesma paleta do lançamento (DiagnosticoPanel) — só para orientar a coordenação.
const COR: Record<string, string> = {
    autonomia: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
    apoio: 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-300',
    nao_realiza: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
    nao_trabalha: 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

const fmtDateTime = (d: string | null) =>
    d ? new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : null;

const restaurarPadrao = (t: Termo) => {
    form[`descricao_${t.opcao}`] = t.descricao_padrao;
    form[`sigla_${t.opcao}`] = t.sigla_padrao;
};

const salvar = () => {
    form.put('/diario/termos-diagnostico', {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => focusFirstError(Object.keys(errors)),
    });
};

const algumErro = computed(() => Object.keys(form.errors).length > 0);
</script>

<template>
    <Head title="Termos da Avaliação Diagnóstica" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-4 p-4 md:p-6">
            <!-- Cabeçalho + Salvar no topo -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        Termos da Avaliação Diagnóstica
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Nomenclaturas apresentadas nos resultados da avaliação diagnóstica, para toda a rede.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <RefreshButton />
                    <Button
                        v-if="!isReadOnly"
                        type="button"
                        class="bg-indigo-600 hover:bg-indigo-700"
                        :disabled="form.processing || !form.isDirty"
                        @click="salvar"
                    >
                        <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        <Save v-else class="mr-2 size-4" />
                        Salvar
                    </Button>
                </div>
            </div>

            <div class="flex items-start gap-2 rounded-lg border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-900 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-200">
                <Tags class="mt-0.5 size-4 shrink-0" />
                <span>
                    Alterar um termo muda apenas <b>como</b> o resultado é exibido (lançamento, relatórios e app).
                    Os resultados já registrados não são modificados.
                </span>
            </div>

            <div class="overflow-hidden rounded-lg border bg-card shadow-sm">
                <div
                    v-for="t in termos"
                    :key="t.opcao"
                    class="grid grid-cols-1 gap-3 border-b px-4 py-4 last:border-b-0 md:grid-cols-12 md:items-start"
                >
                    <div class="flex items-center gap-3 md:col-span-3 md:pt-7">
                        <span :class="['inline-flex min-w-9 items-center justify-center rounded px-1.5 py-1 text-xs font-bold', COR[t.opcao]]">
                            {{ form[`sigla_${t.opcao}`] || '?' }}
                        </span>
                        <div class="min-w-0">
                            <div class="text-xs text-muted-foreground">Padrão do sistema</div>
                            <div class="text-sm">{{ t.descricao_padrao }}</div>
                        </div>
                    </div>

                    <div class="md:col-span-5">
                        <FormLabel :for="`descricao_${t.opcao}`" required>Descrição</FormLabel>
                        <Input
                            :id="`descricao_${t.opcao}`"
                            v-model="form[`descricao_${t.opcao}`]"
                            maxlength="60"
                            :readonly="isReadOnly"
                            :class="form.errors[`descricao_${t.opcao}`] ? 'border-rose-500' : ''"
                        />
                        <InputError :message="form.errors[`descricao_${t.opcao}`]" />
                    </div>

                    <div class="md:col-span-2">
                        <FormLabel :for="`sigla_${t.opcao}`" required>Sigla</FormLabel>
                        <Input
                            :id="`sigla_${t.opcao}`"
                            v-model="form[`sigla_${t.opcao}`]"
                            maxlength="5"
                            :readonly="isReadOnly"
                            :class="form.errors[`sigla_${t.opcao}`] ? 'border-rose-500' : ''"
                        />
                        <InputError :message="form.errors[`sigla_${t.opcao}`]" />
                    </div>

                    <div class="flex flex-col gap-1 md:col-span-2 md:items-end md:pt-6">
                        <Button
                            v-if="!isReadOnly"
                            type="button"
                            variant="ghost"
                            size="sm"
                            title="Voltar ao termo padrão do sistema"
                            @click="restaurarPadrao(t)"
                        >
                            <RotateCcw class="mr-1 size-3.5" /> Padrão
                        </Button>
                        <!-- Só depois da 1ª alteração pela tela (o registro inicial vem da migration). -->
                        <span v-if="t.updated_by" class="text-[11px] text-muted-foreground md:text-right">
                            Alterado em {{ fmtDateTime(t.updated_at) }} · {{ t.updated_by }}
                        </span>
                    </div>
                </div>
            </div>

            <p v-if="algumErro" class="text-sm text-rose-600">Corrija os campos destacados para salvar.</p>
        </div>
    </AppLayout>
</template>
