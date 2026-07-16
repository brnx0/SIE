<script setup lang="ts">
import RemoteCombobox from '@/components/common/RemoteCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import type { BreadcrumbItem } from '@/types';
import type { RespostaLinha, VisitaCabecalho } from '@/types/alimentacao';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Ban, Check, ChevronLeft, CircleCheck, Lock, LoaderCircle, ShieldCheck, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    visita: VisitaCabecalho;
    respostas: RespostaLinha[];
    editavel: boolean;
    podeAtestar: boolean;
}>();

const { isReadOnly } = usePermissao();

// Renderiza direto do prop — cópia em ref fica obsoleta após o reload do Inertia.
const linhas = computed(() => props.respostas ?? []);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Visitas Técnicas', href: '/alimentacao/visitas' },
    { title: props.visita.escola_nome ?? 'Visita', href: `/alimentacao/visitas/${props.visita.vis_id}/edit` },
]);

const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

// Resposta pergunta a pergunta.
const respondendoId = ref<number | null>(null);
const formResposta = useForm<Record<string, any>>({
    vre_resposta: '',
    vre_pro_id: null as number | null,
    vre_obs: '',
    _method: 'put',
});

const abrirResposta = (r: RespostaLinha) => {
    respondendoId.value = r.vre_id;
    formResposta.clearErrors();
    formResposta.vre_resposta = r.resposta ?? '';
    formResposta.vre_pro_id = r.pro_id;
    formResposta.vre_obs = r.obs ?? '';
};

const salvarResposta = (r: RespostaLinha) => {
    // Na múltipla, a resposta guardada é o id da opção — é o que o backend confere.
    if (r.tipo === 'MULTIPLA') {
        formResposta.vre_resposta = formResposta.vre_pro_id ? String(formResposta.vre_pro_id) : '';
    }
    formResposta.post(`/alimentacao/visitas/${props.visita.vis_id}/respostas/${r.vre_id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { respondendoId.value = null; },
    });
};

const respondidas = computed(() => linhas.value.filter((r) => r.respondida).length);
const obrigatoriasPendentes = computed(() => linhas.value.filter((r) => r.obrigatoria && !r.respondida).length);

// Realização
const formRealizar = useForm<Record<string, any>>({
    vis_dt_realizada: props.visita.vis_dt_realizada ?? new Date().toISOString().slice(0, 10),
});
const realizar = () => {
    formRealizar.post(`/alimentacao/visitas/${props.visita.vis_id}/realizar`, { preserveScroll: true });
};

// Ateste
const atestando = ref(false);
const atestador = ref<{ id: number; label: string } | null>(null);
const formAtestar = useForm<Record<string, any>>({
    vis_fun_ateste_id: null as number | null,
    vis_dt_ateste: new Date().toISOString().slice(0, 10),
    vis_parecer: '',
});
const atestar = () => {
    formAtestar.post(`/alimentacao/visitas/${props.visita.vis_id}/atestar`, {
        preserveScroll: true,
        onSuccess: () => { atestando.value = false; },
    });
};

// Cancelamento
const cancelando = ref(false);
const formCancelar = useForm<Record<string, any>>({ vis_motivo_cancelamento: '' });
const cancelar = () => {
    formCancelar.post(`/alimentacao/visitas/${props.visita.vis_id}/cancelar`, {
        preserveScroll: true,
        onSuccess: () => { cancelando.value = false; },
    });
};
</script>

<template>
    <Head :title="`Visita — ${visita.escola_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ visita.escola_nome }}</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ visita.tipo_visita }} · Programada para {{ dataBr(visita.vis_dt_programada) }}
                        <template v-if="visita.tecnico_nome"> · Técnico: {{ visita.tecnico_nome }}</template>
                    </p>
                </div>
                <Link href="/alimentacao/visitas">
                    <Button type="button" variant="outline"><ChevronLeft class="mr-1 size-4" /> Voltar</Button>
                </Link>
            </div>

            <!-- Situação -->
            <div
                v-if="visita.vis_situacao === 'ATESTADA'"
                class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200"
            >
                <ShieldCheck class="mt-0.5 size-4 shrink-0" />
                <div>
                    <p>
                        Visita <strong>atestada</strong> por {{ visita.atestador_nome }} em {{ dataBr(visita.vis_dt_ateste) }}.
                        O registro está fechado e não pode mais ser alterado.
                    </p>
                    <p v-if="visita.vis_parecer" class="mt-1 italic">{{ visita.vis_parecer }}</p>
                </div>
            </div>

            <div
                v-else-if="visita.vis_situacao === 'CANCELADA'"
                class="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-200"
            >
                <Ban class="mt-0.5 size-4 shrink-0" />
                <p>Visita <strong>cancelada</strong> — {{ visita.vis_motivo_cancelamento }}</p>
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Questionário</p>
                    <p class="mt-1 truncate text-sm font-medium">{{ visita.questionario_nome }}</p>
                </div>
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Respondidas</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ respondidas }} / {{ linhas.length }}</p>
                </div>
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Obrigatórias pendentes</p>
                    <p :class="['mt-1 text-lg font-semibold tabular-nums', obrigatoriasPendentes > 0 && 'text-amber-600 dark:text-amber-400']">
                        {{ obrigatoriasPendentes }}
                    </p>
                </div>
            </div>

            <!-- Questionário -->
            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Questionário</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            As perguntas foram copiadas quando a visita foi programada: alterar o cadastro depois não
                            muda o que está aqui.
                        </p>
                    </div>
                    <RefreshButton />
                </div>

                <div class="divide-y rounded-lg border">
                    <div v-if="linhas.length === 0" class="px-4 py-8 text-center text-muted-foreground">
                        Esta visita não tem questionário.
                    </div>

                    <div v-for="(r, i) in linhas" :key="r.vre_id" class="px-4 py-3">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm font-medium">
                                    {{ i + 1 }}. {{ r.pergunta }}
                                    <span v-if="r.obrigatoria" class="text-rose-500">*</span>
                                </p>
                                <p :class="['mt-1 text-sm', r.respondida ? 'font-medium' : 'text-muted-foreground']">
                                    {{ r.texto_resposta }}
                                </p>
                                <p v-if="r.obs" class="mt-0.5 text-xs italic text-muted-foreground">{{ r.obs }}</p>
                            </div>
                            <Button
                                v-if="!isReadOnly && editavel"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="abrirResposta(r)"
                            >
                                <Check class="mr-1 size-3.5" /> {{ r.respondida ? 'Alterar' : 'Responder' }}
                            </Button>
                            <Lock v-else-if="!editavel" class="size-4 shrink-0 text-muted-foreground" />
                        </div>

                        <!-- Painel de resposta -->
                        <div v-if="respondendoId === r.vre_id" class="mt-3 grid gap-3 rounded-lg bg-muted/30 p-4">
                            <div v-if="r.tipo === 'SIM_NAO'" class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    :variant="formResposta.vre_resposta === 'SIM' ? 'default' : 'outline'"
                                    :class="formResposta.vre_resposta === 'SIM' ? 'bg-emerald-600 text-white hover:bg-emerald-700' : ''"
                                    @click="formResposta.vre_resposta = 'SIM'"
                                >
                                    Sim
                                </Button>
                                <Button
                                    type="button"
                                    :variant="formResposta.vre_resposta === 'NAO' ? 'default' : 'outline'"
                                    :class="formResposta.vre_resposta === 'NAO' ? 'bg-rose-600 text-white hover:bg-rose-700' : ''"
                                    @click="formResposta.vre_resposta = 'NAO'"
                                >
                                    Não
                                </Button>
                            </div>

                            <div v-else-if="r.tipo === 'NOTA'" class="grid gap-2 sm:max-w-40">
                                <FormLabel :for="`nota_${r.vre_id}`">Nota (0 a 10)</FormLabel>
                                <Input :id="`nota_${r.vre_id}`" type="number" min="0" max="10" step="0.1" inputmode="decimal" v-model="formResposta.vre_resposta" />
                            </div>

                            <div v-else-if="r.tipo === 'MULTIPLA'" class="grid gap-2">
                                <FormLabel>Escolha uma opção</FormLabel>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="o in r.opcoes"
                                        :key="o.pro_id"
                                        type="button"
                                        :variant="formResposta.vre_pro_id === o.pro_id ? 'default' : 'outline'"
                                        :class="formResposta.vre_pro_id === o.pro_id ? 'bg-indigo-600 text-white hover:bg-indigo-700' : ''"
                                        @click="formResposta.vre_pro_id = o.pro_id"
                                    >
                                        {{ o.pro_texto }}
                                    </Button>
                                </div>
                                <InputError :message="formResposta.errors.vre_pro_id" />
                            </div>

                            <div v-else class="grid gap-2">
                                <FormLabel :for="`texto_${r.vre_id}`">Resposta</FormLabel>
                                <textarea
                                    :id="`texto_${r.vre_id}`"
                                    v-model="formResposta.vre_resposta"
                                    rows="2"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                                ></textarea>
                            </div>

                            <InputError :message="formResposta.errors.vre_resposta" />

                            <div class="grid gap-2">
                                <FormLabel :for="`obs_${r.vre_id}`">Observação</FormLabel>
                                <Input :id="`obs_${r.vre_id}`" v-model="formResposta.vre_obs" placeholder="Opcional" />
                            </div>

                            <div class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    :disabled="formResposta.processing"
                                    class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                                    @click="salvarResposta(r)"
                                >
                                    <LoaderCircle v-if="formResposta.processing" class="mr-2 size-4 animate-spin" />
                                    <Check v-else class="mr-2 size-4" />
                                    Salvar resposta
                                </Button>
                                <Button type="button" variant="outline" @click="respondendoId = null">Cancelar</Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Realizar -->
            <div
                v-if="!isReadOnly && visita.vis_situacao === 'PROGRAMADA'"
                class="flex flex-wrap items-end justify-between gap-4 rounded-xl border bg-card p-6 shadow-sm"
            >
                <div>
                    <p class="text-sm font-medium">Marcar como realizada</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Exige todas as perguntas obrigatórias respondidas.
                        <span v-if="obrigatoriasPendentes > 0" class="text-amber-600 dark:text-amber-400">
                            Faltam {{ obrigatoriasPendentes }}.
                        </span>
                    </p>
                    <InputError :message="formRealizar.errors.situacao" />
                </div>
                <div class="flex items-end gap-2">
                    <div class="grid gap-2">
                        <FormLabel for="vis_dt_realizada">Data da realização</FormLabel>
                        <Input id="vis_dt_realizada" type="date" class="w-44" v-model="formRealizar.vis_dt_realizada" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                    </div>
                    <Button
                        type="button"
                        :disabled="formRealizar.processing || obrigatoriasPendentes > 0"
                        class="bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-40"
                        @click="realizar"
                    >
                        <LoaderCircle v-if="formRealizar.processing" class="mr-2 size-4 animate-spin" />
                        <CircleCheck v-else class="mr-2 size-4" />
                        Registrar realização
                    </Button>
                </div>
            </div>

            <!-- Atestar -->
            <div v-if="!isReadOnly && podeAtestar" class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium">Atestar a visita</p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            O ateste é conferência de outra pessoa: quem realizou a visita não pode atestá-la.
                            Depois de atestada, a visita não muda mais.
                        </p>
                    </div>
                    <Button v-if="!atestando" type="button" variant="outline" @click="atestando = true">
                        <ShieldCheck class="mr-2 size-4" /> Atestar
                    </Button>
                </div>

                <div v-if="atestando" class="grid gap-4 rounded-lg bg-muted/30 p-4 sm:grid-cols-6 sm:items-start">
                    <div class="grid gap-2 sm:col-span-3">
                        <FormLabel :required="true">Quem atesta</FormLabel>
                        <RemoteCombobox
                            v-model="formAtestar.vis_fun_ateste_id"
                            fetch-url="/api/funcionarios"
                            id-key="fun_id"
                            label-key="fun_nome"
                            placeholder="Buscar funcionário..."
                            :invalid="!!formAtestar.errors.vis_fun_ateste_id"
                            :selected-label="atestador?.label ?? null"
                            @selected="atestador = $event"
                        />
                        <InputError :message="formAtestar.errors.vis_fun_ateste_id" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FormLabel for="vis_dt_ateste" :required="true">Data do ateste</FormLabel>
                        <Input id="vis_dt_ateste" type="date" v-model="formAtestar.vis_dt_ateste" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                        <InputError :message="formAtestar.errors.vis_dt_ateste" />
                    </div>

                    <div class="grid gap-2 sm:col-span-6">
                        <FormLabel for="vis_parecer">Parecer</FormLabel>
                        <textarea
                            id="vis_parecer"
                            v-model="formAtestar.vis_parecer"
                            rows="3"
                            placeholder="Opcional — conclusão da conferência"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                        ></textarea>
                        <InputError :message="formAtestar.errors.vis_parecer" />
                    </div>

                    <div class="flex items-center gap-2 sm:col-span-6">
                        <Button
                            type="button"
                            :disabled="formAtestar.processing || !formAtestar.vis_fun_ateste_id"
                            class="bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-40"
                            @click="atestar"
                        >
                            <LoaderCircle v-if="formAtestar.processing" class="mr-2 size-4 animate-spin" />
                            <ShieldCheck v-else class="mr-2 size-4" />
                            Confirmar ateste
                        </Button>
                        <Button type="button" variant="outline" @click="atestando = false">Cancelar</Button>
                    </div>
                </div>
            </div>

            <!-- Cancelar -->
            <div
                v-if="!isReadOnly && editavel"
                class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium">Cancelar a visita</p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            A visita fica registrada como cancelada, com o motivo — não some do histórico.
                        </p>
                    </div>
                    <Button v-if="!cancelando" type="button" variant="outline" class="text-rose-600" @click="cancelando = true">
                        <Ban class="mr-2 size-4" /> Cancelar visita
                    </Button>
                </div>

                <div v-if="cancelando" class="grid gap-3 rounded-lg bg-rose-50/50 p-4 dark:bg-rose-900/10">
                    <div class="flex items-start gap-2 text-sm text-rose-800 dark:text-rose-200">
                        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                        <p>Visita cancelada não pode ser retomada — programe uma nova se precisar.</p>
                    </div>
                    <div class="grid gap-2">
                        <FormLabel for="vis_motivo_cancelamento" :required="true">Motivo do cancelamento</FormLabel>
                        <Input id="vis_motivo_cancelamento" v-model="formCancelar.vis_motivo_cancelamento" placeholder="Ex.: escola em reforma" />
                        <InputError :message="formCancelar.errors.vis_motivo_cancelamento" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            :disabled="formCancelar.processing"
                            class="bg-rose-600 text-white hover:bg-rose-700"
                            @click="cancelar"
                        >
                            <LoaderCircle v-if="formCancelar.processing" class="mr-2 size-4 animate-spin" />
                            <Ban v-else class="mr-2 size-4" />
                            Confirmar cancelamento
                        </Button>
                        <Button type="button" variant="outline" @click="cancelando = false">Voltar</Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
