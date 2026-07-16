<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { PerguntaLinha, PerguntaOpcaoLinha } from '@/types/alimentacao';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, HelpCircle, Lock, LoaderCircle, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    perguntas: Paginated<PerguntaLinha>;
    questionarios: { tpq_id: number; tpq_nome: string; tpq_fl_ativo: boolean }[];
    tipos: Record<string, string>;
    filters: { search: string; tpq_id: string; situacao: string; per_page: number };
}>();

const search   = ref(props.filters.search ?? '');
const tpqId    = ref(props.filters.tpq_id ?? '');
const situacao = ref(props.filters.situacao ?? '');
const perPage  = ref(props.filters.per_page ?? 25);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        search: search.value, tpq_id: tpqId.value, situacao: situacao.value, per_page: perPage.value,
    };
    if (!resetPage) params.page = props.perguntas.current_page;
    router.get('/alimentacao/perguntas', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([tpqId, situacao, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Perguntas do Questionário', href: '/alimentacao/perguntas' }];

const linhas = computed(() => props.perguntas.data ?? []);

// Edição inline: uma pergunta por vez, com as opções junto.
const editandoId = ref<number | null>(null);
const criando = ref(false);

const form = useForm<Record<string, any>>({
    prg_tpq_id: null as number | null,
    prg_ordem: 1,
    prg_texto: '',
    prg_tipo_resposta: 'SIM_NAO',
    prg_fl_obrigatoria: true,
    prg_fl_ativo: true,
    opcoes: [] as PerguntaOpcaoLinha[],
    _method: undefined as string | undefined,
});

const ehMultipla = computed(() => form.prg_tipo_resposta === 'MULTIPLA');

const abrirNova = () => {
    criando.value = true;
    editandoId.value = null;
    form.clearErrors();
    form.prg_tpq_id = tpqId.value ? Number(tpqId.value) : (props.questionarios[0]?.tpq_id ?? null);
    form.prg_ordem = (linhas.value.at(-1)?.prg_ordem ?? 0) + 1;
    form.prg_texto = '';
    form.prg_tipo_resposta = 'SIM_NAO';
    form.prg_fl_obrigatoria = true;
    form.prg_fl_ativo = true;
    form.opcoes = [];
    form._method = undefined;
};

const abrirEdicao = (p: PerguntaLinha) => {
    editandoId.value = p.prg_id;
    criando.value = false;
    form.clearErrors();
    form.prg_tpq_id = p.prg_tpq_id;
    form.prg_ordem = p.prg_ordem;
    form.prg_texto = p.prg_texto;
    form.prg_tipo_resposta = p.prg_tipo_resposta;
    form.prg_fl_obrigatoria = p.prg_fl_obrigatoria;
    form.prg_fl_ativo = p.prg_fl_ativo;
    form.opcoes = p.opcoes.map((o) => ({ ...o }));
    form._method = 'put';
};

const fechar = () => { editandoId.value = null; criando.value = false; };

const addOpcao = () => form.opcoes.push({ pro_texto: '', pro_peso: null });
const removeOpcao = (i: number) => form.opcoes.splice(i, 1);

const salvar = () => {
    const opts = { preserveScroll: true, preserveState: true, onSuccess: () => fechar() };
    if (criando.value) {
        form.post('/alimentacao/perguntas', opts);
    } else if (editandoId.value) {
        form.post(`/alimentacao/perguntas/${editandoId.value}`, opts);
    }
};

const remover = (p: PerguntaLinha) => {
    if (!confirm(`Remover a pergunta "${p.prg_texto}"?`)) return;
    router.delete(`/alimentacao/perguntas/${p.prg_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Perguntas do Questionário" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Perguntas do Questionário</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Perguntas aplicadas nas visitas técnicas, agrupadas por tipo de questionário.
                        Editar uma pergunta não altera visitas já respondidas.
                    </p>
                </div>
                <Button
                    v-if="!isReadOnly && questionarios.length"
                    class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    @click="abrirNova"
                >
                    <Plus class="mr-2 size-4" /> Nova pergunta
                </Button>
            </div>

            <div
                v-if="questionarios.length === 0"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200"
            >
                Cadastre um <strong>Tipo de Questionário</strong> antes de criar perguntas.
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar pergunta..." class="pl-9" />
                </div>
                <select v-model="tpqId" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todos os questionários</option>
                    <option v-for="q in questionarios" :key="q.tpq_id" :value="String(q.tpq_id)">{{ q.tpq_nome }}</option>
                </select>
                <select v-model="situacao" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as situações</option>
                    <option value="ativo">Ativas</option>
                    <option value="inativo">Inativas</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                </div>
            </div>

            <!-- Formulário de nova pergunta -->
            <div v-if="criando" class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                <h2 class="text-lg font-semibold sm:col-span-6">Nova pergunta</h2>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="prg_tpq_id" :required="true">Questionário</FormLabel>
                    <select id="prg_tpq_id" v-model="form.prg_tpq_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                        <option v-for="q in questionarios" :key="q.tpq_id" :value="q.tpq_id">{{ q.tpq_nome }}</option>
                    </select>
                    <InputError :message="form.errors.prg_tpq_id" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="prg_tipo_resposta" :required="true">Tipo de resposta</FormLabel>
                    <select id="prg_tipo_resposta" v-model="form.prg_tipo_resposta" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                        <option v-for="(label, valor) in tipos" :key="valor" :value="valor">{{ label }}</option>
                    </select>
                    <InputError :message="form.errors.prg_tipo_resposta" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel for="prg_ordem" :required="true">Ordem</FormLabel>
                    <Input id="prg_ordem" type="number" min="1" step="1" v-model.number="form.prg_ordem" />
                    <InputError :message="form.errors.prg_ordem" />
                </div>

                <div class="grid gap-2 sm:col-span-6">
                    <FormLabel for="prg_texto" :required="true">Pergunta</FormLabel>
                    <textarea
                        id="prg_texto"
                        v-model="form.prg_texto"
                        rows="2"
                        placeholder="Ex.: A cozinha está limpa e organizada?"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    ></textarea>
                    <InputError :message="form.errors.prg_texto" />
                </div>

                <!-- Opções: só na múltipla escolha -->
                <div v-if="ehMultipla" class="grid gap-3 sm:col-span-6">
                    <FormLabel :required="true">Opções de resposta</FormLabel>
                    <div v-for="(o, i) in form.opcoes" :key="i" class="flex items-center gap-2">
                        <Input v-model="o.pro_texto" maxlength="150" placeholder="Texto da opção" class="flex-1" />
                        <Input v-model.number="o.pro_peso" type="number" step="0.01" placeholder="Peso" class="w-28" />
                        <Button type="button" variant="ghost" size="sm" class="text-rose-600" @click="removeOpcao(i)">
                            <X class="size-4" />
                        </Button>
                    </div>
                    <div>
                        <Button type="button" variant="outline" size="sm" @click="addOpcao">
                            <Plus class="mr-1 size-3.5" /> Adicionar opção
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">O peso é opcional e serve para pontuar a visita.</p>
                    <InputError :message="form.errors.opcoes" />
                </div>

                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-3">
                    <Switch id="prg_fl_obrigatoria" v-model="form.prg_fl_obrigatoria" />
                    <div>
                        <p class="text-sm font-medium leading-none">Obrigatória</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            A visita só pode ser marcada como realizada com esta pergunta respondida.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-3">
                    <Switch id="prg_fl_ativo" v-model="form.prg_fl_ativo" />
                    <div>
                        <p class="text-sm font-medium leading-none">Ativa</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">Inativa não entra em visitas novas.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:col-span-6">
                    <Button
                        type="button"
                        :disabled="form.processing"
                        class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        @click="salvar"
                    >
                        <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        <Check v-else class="mr-2 size-4" />
                        Salvar pergunta
                    </Button>
                    <Button type="button" variant="outline" @click="fechar">Cancelar</Button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3" style="width:60px">Ordem</th>
                            <th class="px-4 py-3">Pergunta</th>
                            <th class="px-4 py-3">Questionário</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3 text-center">Obrigatória</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="linhas.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">Nenhuma pergunta cadastrada.</td>
                        </tr>
                        <template v-for="p in linhas" :key="p.prg_id">
                            <tr class="transition-colors hover:bg-muted/30">
                                <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ p.prg_ordem }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-start gap-3">
                                        <div class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                            <HelpCircle class="size-4" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-medium">{{ p.prg_texto }}</span>
                                            <span v-if="p.opcoes.length" class="text-xs text-muted-foreground">
                                                {{ p.opcoes.map((o) => o.pro_texto).join(' · ') }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ p.questionario_nome }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ p.tipo_label }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="p.prg_fl_obrigatoria" class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Sim</span>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', p.prg_fl_ativo ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                        {{ p.prg_fl_ativo ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button v-if="!isReadOnly" variant="ghost" size="sm" @click="abrirEdicao(p)">
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            v-if="!isReadOnly"
                                            variant="ghost"
                                            size="sm"
                                            :disabled="p.usada"
                                            :title="p.usada ? 'Pergunta já respondida em visitas — inative-a' : 'Remover'"
                                            class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-40 dark:hover:bg-rose-900/30"
                                            @click="remover(p)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Edição inline -->
                            <tr v-if="editandoId === p.prg_id">
                                <td colspan="7" class="bg-muted/30 px-4 py-4">
                                    <div class="grid gap-4 sm:grid-cols-6 sm:items-start">
                                        <div
                                            v-if="p.usada"
                                            class="flex items-start gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800 sm:col-span-6 dark:border-sky-900/50 dark:bg-sky-900/20 dark:text-sky-200"
                                        >
                                            <Lock class="mt-0.5 size-3.5 shrink-0" />
                                            <p>
                                                Esta pergunta já foi respondida em visitas. Corrigir o texto é seguro — as
                                                visitas antigas guardam o texto do dia. O <strong>tipo de resposta</strong>,
                                                porém, não pode mudar: as respostas antigas deixariam de fazer sentido.
                                            </p>
                                        </div>

                                        <div class="grid gap-2 sm:col-span-3">
                                            <FormLabel :required="true">Questionário</FormLabel>
                                            <select v-model="form.prg_tpq_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                                                <option v-for="q in questionarios" :key="q.tpq_id" :value="q.tpq_id">{{ q.tpq_nome }}</option>
                                            </select>
                                            <InputError :message="form.errors.prg_tpq_id" />
                                        </div>

                                        <div class="grid gap-2 sm:col-span-2">
                                            <FormLabel :required="true">Tipo de resposta</FormLabel>
                                            <select
                                                v-model="form.prg_tipo_resposta"
                                                :disabled="p.usada"
                                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-background dark:text-foreground"
                                            >
                                                <option v-for="(label, valor) in tipos" :key="valor" :value="valor">{{ label }}</option>
                                            </select>
                                            <InputError :message="form.errors.prg_tipo_resposta" />
                                        </div>

                                        <div class="grid gap-2 sm:col-span-1">
                                            <FormLabel :required="true">Ordem</FormLabel>
                                            <Input type="number" min="1" step="1" v-model.number="form.prg_ordem" />
                                            <InputError :message="form.errors.prg_ordem" />
                                        </div>

                                        <div class="grid gap-2 sm:col-span-6">
                                            <FormLabel :required="true">Pergunta</FormLabel>
                                            <textarea
                                                v-model="form.prg_texto"
                                                rows="2"
                                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                                            ></textarea>
                                            <InputError :message="form.errors.prg_texto" />
                                        </div>

                                        <div v-if="ehMultipla" class="grid gap-3 sm:col-span-6">
                                            <FormLabel :required="true">Opções de resposta</FormLabel>
                                            <div v-for="(o, i) in form.opcoes" :key="i" class="flex items-center gap-2">
                                                <Input v-model="o.pro_texto" maxlength="150" placeholder="Texto da opção" class="flex-1" />
                                                <Input v-model.number="o.pro_peso" type="number" step="0.01" placeholder="Peso" class="w-28" />
                                                <Button type="button" variant="ghost" size="sm" class="text-rose-600" @click="removeOpcao(i)">
                                                    <X class="size-4" />
                                                </Button>
                                            </div>
                                            <div>
                                                <Button type="button" variant="outline" size="sm" @click="addOpcao">
                                                    <Plus class="mr-1 size-3.5" /> Adicionar opção
                                                </Button>
                                            </div>
                                            <InputError :message="form.errors.opcoes" />
                                        </div>

                                        <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-3">
                                            <Switch v-model="form.prg_fl_obrigatoria" />
                                            <p class="text-sm font-medium leading-none">Obrigatória</p>
                                        </div>

                                        <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-3">
                                            <Switch v-model="form.prg_fl_ativo" />
                                            <div>
                                                <p class="text-sm font-medium leading-none">Ativa</p>
                                                <p class="mt-0.5 text-xs text-muted-foreground">
                                                    Inativa some das visitas novas; as antigas seguem intactas.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 sm:col-span-6">
                                            <Button
                                                type="button"
                                                :disabled="form.processing"
                                                class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                                                @click="salvar"
                                            >
                                                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                                                <Check v-else class="mr-2 size-4" />
                                                Salvar
                                            </Button>
                                            <Button type="button" variant="outline" @click="fechar">Cancelar</Button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="perguntas.total > 0">{{ perguntas.from }}–{{ perguntas.to }} de {{ perguntas.total }} pergunta{{ perguntas.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="perguntas.last_page > 1" class="flex flex-wrap gap-1">
                        <a v-for="(link, i) in perguntas.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
