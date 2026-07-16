<script setup lang="ts">
import EscolaCombobox from '@/components/funcionario/EscolaCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Calculator, ChevronLeft, Info, LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    /** Escola do usuário quando ele tem lotação; nula para perfil global. */
    escolaInicial: { esc_id: number; esc_nome: string } | null;
    anosLetivos: { anl_id: number; anl_ano: number }[];
    meses: Record<number, string>;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const hoje = new Date();

const form = useForm<Record<string, any>>({
    dmd_esc_id: (props.escolaInicial?.esc_id ?? null) as number | null,
    dmd_anl_id: (props.anosLetivos[0]?.anl_id ?? null) as number | null,
    dmd_mes: hoje.getMonth() + 1,
    dmd_ano: hoje.getFullYear(),
    dmd_obs: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Demanda de Merenda', href: '/alimentacao/demandas' },
    { title: 'Nova', href: '/alimentacao/demandas/create' },
];

const submit = () => {
    if (isReadOnly.value) return;
    form.post('/alimentacao/demandas', {
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    });
};
</script>

<template>
    <Head title="Nova demanda" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Nova demanda</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        O cálculo é automático a partir do cardápio da unidade.
                    </p>
                </div>
                <Link href="/alimentacao/demandas">
                    <Button type="button" variant="outline"><ChevronLeft class="mr-1 size-4" /> Voltar</Button>
                </Link>
            </div>

            <div class="flex items-start gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-900/50 dark:bg-sky-900/20 dark:text-sky-200">
                <Info class="mt-0.5 size-4 shrink-0" />
                <div>
                    <p>Ao gravar, o sistema calcula sozinho:</p>
                    <ul class="mt-1 list-disc pl-4 text-xs">
                        <li>pega o cardápio vigente da escola no mês (o próprio dela tem precedência sobre o da rede);</li>
                        <li>percorre os dias letivos, pulando feriados e contando sábado letivo como o dia que ele repõe;</li>
                        <li>multiplica o per capita da ficha técnica pelos alunos matriculados naquele turno.</li>
                    </ul>
                    <p class="mt-1 text-xs">
                        Sem cardápio, sem grade montada ou sem alunos, o cálculo é recusado e explica o motivo.
                    </p>
                </div>
            </div>

            <form @submit.prevent="submit" novalidate class="grid gap-6">
                <fieldset :disabled="isReadOnly || form.processing" class="contents">
                    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel :required="true">Escola</FormLabel>
                            <EscolaCombobox
                                v-model="form.dmd_esc_id"
                                :initial="escolaInicial"
                                placeholder="Buscar escola..."
                                :invalid="!!form.errors.dmd_esc_id"
                            />
                            <InputError :message="form.errors.dmd_esc_id" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel for="dmd_anl_id" :required="true">Ano letivo</FormLabel>
                            <select id="dmd_anl_id" v-model="form.dmd_anl_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                                <option :value="null" disabled>Selecione...</option>
                                <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">{{ a.anl_ano }}</option>
                            </select>
                            <p class="text-xs text-muted-foreground">Define o calendário e as matrículas usadas no cálculo.</p>
                            <InputError :message="form.errors.dmd_anl_id" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel for="dmd_mes" :required="true">Mês</FormLabel>
                            <select id="dmd_mes" v-model="form.dmd_mes" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                                <option v-for="(nome, num) in meses" :key="num" :value="Number(num)">{{ nome }}</option>
                            </select>
                            <InputError :message="form.errors.dmd_mes" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel for="dmd_ano" :required="true">Ano</FormLabel>
                            <Input id="dmd_ano" type="number" min="2000" max="2099" step="1" inputmode="numeric" v-model.number="form.dmd_ano" />
                            <InputError :message="form.errors.dmd_ano" />
                        </div>

                        <div class="grid gap-2 sm:col-span-6">
                            <FormLabel for="dmd_obs">Observação</FormLabel>
                            <Input id="dmd_obs" v-model="form.dmd_obs" placeholder="Opcional" />
                            <InputError :message="form.errors.dmd_obs" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.dmd_esc_id || !form.dmd_anl_id"
                            class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        >
                            <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                            <Calculator v-else class="mr-2 size-4" />
                            Calcular demanda
                        </Button>
                    </div>
                </fieldset>
            </form>
        </div>
    </AppLayout>
</template>
