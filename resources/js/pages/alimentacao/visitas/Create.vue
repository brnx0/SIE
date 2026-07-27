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
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarPlus, ChevronLeft, LoaderCircle, TriangleAlert } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    escolaInicial: { esc_id: number; esc_nome: string } | null;
    tiposVisita: { tpv_id: number; tpv_nome: string }[];
    questionarios: { tpq_id: number; tpq_nome: string; perguntas: number }[];
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const form = useForm<Record<string, any>>({
    vis_esc_id: (props.escolaInicial?.esc_id ?? null) as number | null,
    vis_tpv_id: (props.tiposVisita[0]?.tpv_id ?? null) as number | null,
    vis_tpq_id: (props.questionarios[0]?.tpq_id ?? null) as number | null,
    vis_fun_id: null as number | null,
    vis_dt_programada: new Date().toISOString().slice(0, 10),
    vis_obs: '',
});

const tecnico = ref<{ id: number; label: string } | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Visitas Técnicas', href: '/alimentacao/visitas' },
    { title: 'Programar', href: '/alimentacao/visitas/create' },
];

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

const submit = () => {
    if (isReadOnly.value) return;
    form.post('/alimentacao/visitas', {
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    });
};

const faltaCadastro = !props.tiposVisita.length || !props.questionarios.length;
</script>

<template>
    <Head title="Programar visita" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Programar visita</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        O questionário é montado na hora, com as perguntas ativas do tipo escolhido.
                    </p>
                </div>
                <Link href="/alimentacao/visitas">
                    <Button type="button" variant="outline"><ChevronLeft class="mr-1 size-4" /> Voltar</Button>
                </Link>
            </div>

            <div
                v-if="faltaCadastro"
                class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <p>
                    <template v-if="!tiposVisita.length">Cadastre um <strong>Tipo de Visita Técnica</strong>.</template>
                    <template v-if="!questionarios.length">
                        Nenhum questionário tem perguntas ativas — cadastre as <strong>Perguntas</strong> antes de programar.
                    </template>
                </p>
            </div>

            <form v-else @submit.prevent="submit" novalidate class="grid gap-6">
                <fieldset :disabled="isReadOnly || form.processing" class="contents">
                    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel :required="true">Escola</FormLabel>
                            <EscolaCombobox
                                v-model="form.vis_esc_id"
                                :initial="escolaInicial"
                                placeholder="Buscar escola..."
                                :invalid="!!form.errors.vis_esc_id"
                            />
                            <InputError :message="form.errors.vis_esc_id" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel for="vis_tpv_id" :required="true">Tipo de visita</FormLabel>
                            <select id="vis_tpv_id" v-model="form.vis_tpv_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                                <option :value="null" disabled>Selecione...</option>
                                <option v-for="t in tiposVisita" :key="t.tpv_id" :value="t.tpv_id">{{ t.tpv_nome }}</option>
                            </select>
                            <InputError :message="form.errors.vis_tpv_id" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel for="vis_tpq_id" :required="true">Questionário a aplicar</FormLabel>
                            <select id="vis_tpq_id" v-model="form.vis_tpq_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                                <option :value="null" disabled>Selecione...</option>
                                <option v-for="q in questionarios" :key="q.tpq_id" :value="q.tpq_id">
                                    {{ q.tpq_nome }} ({{ q.perguntas }} pergunta{{ q.perguntas !== 1 ? 's' : '' }})
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                As perguntas são copiadas para a visita: mudar o questionário depois não altera esta.
                            </p>
                            <InputError :message="form.errors.vis_tpq_id" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel for="vis_dt_programada" :required="true">Data programada</FormLabel>
                            <Input id="vis_dt_programada" type="date" v-model="form.vis_dt_programada" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                            <InputError :message="form.errors.vis_dt_programada" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel>Técnico responsável</FormLabel>
                            <RemoteCombobox
                                v-model="form.vis_fun_id"
                                fetch-url="/api/funcionarios"
                                id-key="fun_id"
                                label-key="fun_nome"
                                placeholder="Buscar funcionário..."
                                :invalid="!!form.errors.vis_fun_id"
                                :selected-label="tecnico?.label ?? null"
                                @selected="tecnico = $event"
                            />
                            <p class="text-xs text-muted-foreground">Quem realiza a visita não poderá atestá-la.</p>
                            <InputError :message="form.errors.vis_fun_id" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel for="vis_obs">Observação</FormLabel>
                            <Input id="vis_obs" v-model="form.vis_obs" placeholder="Opcional" />
                            <InputError :message="form.errors.vis_obs" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.vis_esc_id || !form.vis_tpq_id"
                            class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        >
                            <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                            <CalendarPlus v-else class="mr-2 size-4" />
                            Programar visita
                        </Button>
                    </div>
                </fieldset>
            </form>
        </div>
    </AppLayout>
</template>
