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
import type { SugestaoCardapio } from '@/types/alimentacao';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarCheck, ChevronLeft, LoaderCircle, Save } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    /** Escola do usuário quando ele tem lotação; nula para perfil global. */
    escolaInicial: { esc_id: number; esc_nome: string } | null;
    turnos: string[];
    refeicoes: string[];
    dt: string;
    sugestoes: SugestaoCardapio[];
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const form = useForm<Record<string, any>>({
    prd_esc_id: (props.escolaInicial?.esc_id ?? null) as number | null,
    prd_prt_id: null as number | null,
    prd_fun_id: null as number | null,
    prd_dt: props.dt,
    prd_turno: props.turnos[0] ?? 'MATUTINO',
    prd_refeicao: props.refeicoes[0] ?? '',
    prd_qtd_servida: '' as number | string,
    prd_obs: '',
});

const pratoLabel = ref<string | null>(null);
const responsavel = ref<{ id: number; label: string } | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Controle Diário da Preparação', href: '/alimentacao/preparacoes' },
    { title: 'Nova', href: '/alimentacao/preparacoes/create' },
];

// A sugestão do cardápio depende da data e da escola: mudou, recarrega.
const recarregarSugestoes = () => {
    router.get('/alimentacao/preparacoes/create',
        { dt: form.prd_dt, esc_id: form.prd_esc_id ?? '' },
        { preserveState: true, replace: true, only: ['sugestoes', 'dt', 'escolaAtual'] },
    );
};
watch(() => [form.prd_dt, form.prd_esc_id], ([novaDt], [antigaDt]) => {
    if (antigaDt === undefined) return;
    if (!novaDt) return;
    recarregarSugestoes();
});

// Clicar na sugestão preenche prato + turno + refeição de uma vez.
const usarSugestao = (s: SugestaoCardapio) => {
    form.prd_prt_id = s.prt_id;
    form.prd_turno = s.turno;
    form.prd_refeicao = s.refeicao;
    pratoLabel.value = s.prato;
};

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

const submit = () => {
    if (isReadOnly.value) return;
    form.post('/alimentacao/preparacoes', {
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    });
};
</script>

<template>
    <Head title="Nova preparação" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Nova preparação</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Os itens saem da ficha técnica do prato. Nada é baixado do estoque até você confirmar.
                    </p>
                </div>
                <Link href="/alimentacao/preparacoes">
                    <Button type="button" variant="outline"><ChevronLeft class="mr-1 size-4" /> Voltar</Button>
                </Link>
            </div>

            <!-- Sugestão do cardápio do dia -->
            <div
                v-if="sugestoes.length"
                class="grid gap-3 rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900/50 dark:bg-indigo-900/10"
            >
                <div class="flex items-center gap-2">
                    <CalendarCheck class="size-4 text-indigo-600 dark:text-indigo-400" />
                    <p class="text-sm font-medium text-indigo-900 dark:text-indigo-200">
                        O cardápio deste dia prevê:
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="(s, i) in sugestoes"
                        :key="i"
                        type="button"
                        class="rounded-lg border border-indigo-300 bg-background px-3 py-2 text-left text-sm transition-colors hover:bg-indigo-100 dark:border-indigo-800 dark:hover:bg-indigo-900/30"
                        @click="usarSugestao(s)"
                    >
                        <span class="font-medium">{{ s.prato }}</span>
                        <span class="block text-xs text-muted-foreground">{{ s.turno }} · {{ s.refeicao }}</span>
                    </button>
                </div>
                <p class="text-xs text-indigo-800/70 dark:text-indigo-300/70">
                    Clique para preencher. Você pode registrar um prato diferente do cardápio se foi o que a escola serviu.
                </p>
            </div>

            <form @submit.prevent="submit" novalidate class="grid gap-6">
                <fieldset :disabled="isReadOnly || form.processing" class="contents">
                    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel :required="true">Escola</FormLabel>
                            <EscolaCombobox
                                v-model="form.prd_esc_id"
                                :initial="escolaInicial"
                                placeholder="Buscar escola..."
                                :invalid="!!form.errors.prd_esc_id"
                            />
                            <InputError :message="form.errors.prd_esc_id" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <FormLabel for="prd_dt" :required="true">Data</FormLabel>
                            <Input id="prd_dt" type="date" v-model="form.prd_dt" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                            <InputError :message="form.errors.prd_dt" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <FormLabel for="prd_turno" :required="true">Turno</FormLabel>
                            <select id="prd_turno" v-model="form.prd_turno" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                                <option v-for="t in turnos" :key="t" :value="t">{{ t }}</option>
                            </select>
                            <InputError :message="form.errors.prd_turno" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <FormLabel for="prd_refeicao" :required="true">Refeição</FormLabel>
                            <select id="prd_refeicao" v-model="form.prd_refeicao" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                                <option v-for="r in refeicoes" :key="r" :value="r">{{ r }}</option>
                            </select>
                            <InputError :message="form.errors.prd_refeicao" />
                        </div>

                        <div class="grid gap-2 sm:col-span-4">
                            <FormLabel :required="true">Prato preparado</FormLabel>
                            <RemoteCombobox
                                v-model="form.prd_prt_id"
                                fetch-url="/api/alimentacao/pratos/search"
                                id-key="prt_id"
                                label-key="prt_nome"
                                placeholder="Buscar prato..."
                                :invalid="!!form.errors.prd_prt_id"
                                :selected-label="pratoLabel"
                                @selected="pratoLabel = $event?.label ?? null"
                            />
                            <p class="text-xs text-muted-foreground">
                                O prato precisa ter ficha técnica — é ela que diz o que baixar do estoque.
                            </p>
                            <InputError :message="form.errors.prd_prt_id" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <FormLabel for="prd_qtd_servida" :required="true">Porções servidas</FormLabel>
                            <Input id="prd_qtd_servida" type="number" min="1" step="1" inputmode="numeric" v-model.number="form.prd_qtd_servida" />
                            <p class="text-xs text-muted-foreground">Per capita × porções = consumo previsto.</p>
                            <InputError :message="form.errors.prd_qtd_servida" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel>Responsável pelo preparo</FormLabel>
                            <RemoteCombobox
                                v-model="form.prd_fun_id"
                                fetch-url="/api/funcionarios"
                                id-key="fun_id"
                                label-key="fun_nome"
                                placeholder="Buscar funcionário..."
                                :invalid="!!form.errors.prd_fun_id"
                                :selected-label="responsavel?.label ?? null"
                                @selected="responsavel = $event"
                            />
                            <InputError :message="form.errors.prd_fun_id" />
                        </div>

                        <div class="grid gap-2 sm:col-span-3">
                            <FormLabel for="prd_obs">Observação</FormLabel>
                            <Input id="prd_obs" v-model="form.prd_obs" placeholder="Opcional" />
                            <InputError :message="form.errors.prd_obs" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.prd_prt_id || !form.prd_qtd_servida"
                            class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        >
                            <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                            <Save v-else class="mr-2 size-4" />
                            Abrir preparação
                        </Button>
                    </div>
                </fieldset>
            </form>
        </div>
    </AppLayout>
</template>
