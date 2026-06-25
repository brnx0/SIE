<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CalendarClock, Loader2, Pencil, Plus, Search, Trash2, TriangleAlert, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }
interface Motivo { mbf_id: number; mbf_descricao: string }
interface Justificativa {
    jfa_id: number;
    jfa_mbf_id: number;
    motivo: string | null;
    dt_inicio: string;
    dt_fim: string;
    observacao: string | null;
}
interface Aluno {
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: string | null;
    justificativas: Justificativa[];
}
interface Vigente { label: string; dt_inicio: string; dt_fim: string }

const props = defineProps<{
    alunos: Aluno[];
    vigentes: Vigente[];
    filtros: { anl_id: number | null; esc_id: number | null; tur_id: number | null };
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    motivos: Motivo[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Justificativa de Falta', href: '/secretaria/justificativas-falta' },
];

// ── Filtros (recarrega via Inertia) ──────────────────────────────────────────
const anlId = ref<number | null>(props.filtros.anl_id ?? props.anosLetivos[0]?.anl_id ?? null);
const escId = ref<number | null>(props.filtros.esc_id ?? props.userEscola?.esc_id ?? null);
const turId = ref<number | null>(props.filtros.tur_id ?? null);
const busca = ref('');

const turmas = ref<{ tur_id: number; tur_nome: string; ser_nome: string | null }[]>([]);

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsMotivo = computed(() => props.motivos.map((m) => ({ id: m.mbf_id, label: m.mbf_descricao })));
const itemsTurma = computed(() => turmas.value.map((t) => ({ id: t.tur_id, label: (t.ser_nome ? t.ser_nome + ' - ' : '') + t.tur_nome })));

const getJson = async (url: string) => {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    return r.ok ? await r.json() : [];
};
const loadTurmas = async () => {
    turmas.value = [];
    if (!anlId.value || !escId.value) return;
    turmas.value = await getJson(`/secretaria/justificativas-falta/turmas?anl_id=${anlId.value}&esc_id=${escId.value}`);
};

const aplicar = () => {
    router.get('/secretaria/justificativas-falta', { anl_id: anlId.value, esc_id: escId.value, tur_id: turId.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

onMounted(loadTurmas);
watch([anlId, escId], () => { turId.value = null; loadTurmas(); aplicar(); });
watch(turId, () => aplicar());

const lista = computed(() => {
    const q = busca.value.trim().toLowerCase();
    const l = props.alunos ?? [];
    if (!q) return l;
    return l.filter((a) => a.aln_nome.toLowerCase().includes(q) || String(a.aln_nr_matricula ?? '').includes(q));
});
const totalJustificativas = computed(() => (props.alunos ?? []).reduce((s, a) => s + a.justificativas.length, 0));

const fmt = (s: string) => {
    if (!s) return '—';
    const [y, m, d] = s.substring(0, 10).split('-');
    return `${d}/${m}/${y}`;
};

// ── Modal (justifica por aluno) ──────────────────────────────────────────────
const showModal = ref(false);
const editId = ref<number | null>(null);
const alunoSel = ref<{ id: number; nome: string } | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = reactive({
    jfa_mbf_id: null as number | null,
    jfa_dt_inicio: '',
    jfa_dt_fim: '',
    jfa_observacao: '',
});

const vigentes = computed<Vigente[]>(() => props.vigentes ?? []);
const podeJustificar = computed(() => vigentes.value.length > 0);
// Janela total (união) p/ os limites dos campos de data; a validação garante caber em 1 trimestre.
const minInicio = computed(() => (vigentes.value.length ? [...vigentes.value].map((v) => v.dt_inicio).sort()[0] : ''));
const maxFim = computed(() => (vigentes.value.length ? [...vigentes.value].map((v) => v.dt_fim).sort().at(-1) ?? '' : ''));

const reset = () => {
    form.jfa_mbf_id = null;
    form.jfa_dt_inicio = '';
    form.jfa_dt_fim = '';
    form.jfa_observacao = '';
    errors.value = {};
};

const abrirNovo = (a: Aluno) => {
    if (!podeJustificar.value) return;
    editId.value = null;
    alunoSel.value = { id: a.aln_id, nome: a.aln_nome };
    reset();
    showModal.value = true;
};
const abrirEdit = (a: Aluno, j: Justificativa) => {
    editId.value = j.jfa_id;
    alunoSel.value = { id: a.aln_id, nome: a.aln_nome };
    errors.value = {};
    form.jfa_mbf_id = j.jfa_mbf_id;
    form.jfa_dt_inicio = j.dt_inicio?.substring(0, 10) ?? '';
    form.jfa_dt_fim = j.dt_fim?.substring(0, 10) ?? '';
    form.jfa_observacao = j.observacao ?? '';
    showModal.value = true;
};
const fechar = () => { showModal.value = false; editId.value = null; alunoSel.value = null; errors.value = {}; };

const salvar = () => {
    if (!alunoSel.value) return;
    processing.value = true;
    errors.value = {};
    const payload = {
        jfa_anl_id: anlId.value,
        jfa_esc_id: escId.value,
        jfa_tur_id: turId.value,
        jfa_aln_id: alunoSel.value.id,
        jfa_mbf_id: form.jfa_mbf_id,
        jfa_dt_inicio: form.jfa_dt_inicio,
        jfa_dt_fim: form.jfa_dt_fim,
        jfa_observacao: form.jfa_observacao || null,
    };
    const opts = {
        preserveScroll: true,
        preserveState: true,
        onError: (e: Record<string, string>) => { errors.value = e; },
        onFinish: () => { processing.value = false; },
        onSuccess: () => { fechar(); },
    };
    if (editId.value) {
        router.put(`/secretaria/justificativas-falta/${editId.value}`, payload, opts);
    } else {
        router.post('/secretaria/justificativas-falta', payload, opts);
    }
};

const remover = (a: Aluno, j: Justificativa) => {
    if (!confirm(`Remover a justificativa de ${a.aln_nome} (${fmt(j.dt_inicio)} a ${fmt(j.dt_fim)})?`)) return;
    router.delete(`/secretaria/justificativas-falta/${j.jfa_id}`, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <Head title="Justificativa de Falta" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-4 p-4 md:p-6">
            <div class="flex items-end justify-between gap-2">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Justificativa de Falta</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Selecione a turma e justifique as faltas dos alunos, um a um.</p>
                </div>
                <RefreshButton />
            </div>

            <!-- Filtros -->
            <div class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-4">
                <div class="grid gap-1.5">
                    <FormLabel>Ano Letivo</FormLabel>
                    <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Ano..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Escola</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" placeholder="Escola..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Turma</FormLabel>
                    <LocalCombobox v-model="turId" :items="itemsTurma" placeholder="Selecione a turma..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Buscar aluno</FormLabel>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="busca" placeholder="Nome ou matrícula..." class="pl-8" />
                    </div>
                </div>
            </div>

            <!-- Trimestres em andamento -->
            <div
                v-if="vigentes.length"
                class="flex flex-wrap items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                <CalendarClock class="size-4 shrink-0" />
                <span>
                    <strong>{{ vigentes.length > 1 ? 'Trimestres em andamento' : 'Trimestre em andamento' }}:</strong>
                    <template v-for="(v, i) in vigentes" :key="i">
                        {{ i ? ' · ' : ' ' }}<strong>{{ v.label }}</strong> ({{ fmt(v.dt_inicio) }} a {{ fmt(v.dt_fim) }})
                    </template>
                    — é possível justificar em <strong>qualquer um</strong>, dentro da janela dele (inclui extensão).
                </span>
            </div>
            <div
                v-else
                class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <span>Nenhum trimestre em andamento nesta data. Não é possível registrar justificativas (períodos encerrados são bloqueados).</span>
            </div>

            <!-- Sem turma -->
            <div v-if="!turId" class="rounded-xl border bg-card py-12 text-center text-sm text-muted-foreground">
                Selecione uma turma para listar os alunos.
            </div>

            <!-- Lista de alunos -->
            <div v-else class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-indigo-600 text-white">
                        <tr>
                            <th class="w-12 px-3 py-2 text-center font-semibold">#</th>
                            <th class="px-3 py-2 font-semibold">Aluno</th>
                            <th class="px-3 py-2 font-semibold">Justificativas</th>
                            <th class="w-40 px-3 py-2 text-right font-semibold">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!lista.length">
                            <td colspan="4" class="px-3 py-6 text-center text-muted-foreground">Nenhum aluno ativo nesta turma.</td>
                        </tr>
                        <tr v-for="(a, idx) in lista" :key="a.aln_id" :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                            <td class="px-3 py-2 text-center text-muted-foreground tabular-nums">{{ idx + 1 }}</td>
                            <td class="px-3 py-2 align-top">
                                <div class="font-medium">{{ a.aln_nome }}</div>
                                <div v-if="a.aln_nr_matricula" class="text-xs text-muted-foreground">Mat. {{ a.aln_nr_matricula }}</div>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div v-if="!a.justificativas.length" class="text-xs text-muted-foreground">Sem justificativas.</div>
                                <div v-else class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="j in a.justificativas"
                                        :key="j.jfa_id"
                                        class="group inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 py-1 pl-2.5 pr-1 text-xs text-indigo-800 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-200"
                                        :title="j.observacao || ''"
                                    >
                                        <span class="tabular-nums">{{ fmt(j.dt_inicio) }}–{{ fmt(j.dt_fim) }}</span>
                                        <span class="opacity-70">· {{ j.motivo }}</span>
                                        <button type="button" class="rounded p-0.5 hover:bg-indigo-200/70 dark:hover:bg-indigo-800/60" title="Editar" @click="abrirEdit(a, j)"><Pencil class="size-3" /></button>
                                        <button type="button" class="rounded p-0.5 text-rose-600 hover:bg-rose-100 dark:hover:bg-rose-900/40" title="Remover" @click="remover(a, j)"><Trash2 class="size-3" /></button>
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right align-top">
                                <Button
                                    type="button"
                                    size="sm"
                                    class="bg-indigo-600 hover:bg-indigo-700"
                                    :disabled="!podeJustificar"
                                    :title="podeJustificar ? 'Adicionar justificativa' : 'Nenhum trimestre vigente'"
                                    @click="abrirNovo(a)"
                                >
                                    <Plus class="mr-1 size-4" /> Justificar
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="lista.length" class="border-t px-3 py-2 text-xs text-muted-foreground">
                    {{ lista.length }} aluno(s) · {{ totalJustificativas }} justificativa(s) lançada(s).
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="fechar">
            <div class="w-full max-w-lg rounded-2xl bg-card shadow-2xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <div>
                        <h2 class="text-base font-semibold">{{ editId ? 'Editar' : 'Nova' }} Justificativa</h2>
                        <p class="text-xs text-muted-foreground">{{ alunoSel?.nome }}</p>
                    </div>
                    <Button variant="ghost" size="sm" @click="fechar"><X class="size-5" /></Button>
                </div>
                <div class="grid gap-4 p-5">
                    <div v-if="vigentes.length" class="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                        Justifique dentro de um trimestre em andamento:
                        <template v-for="(v, i) in vigentes" :key="i"><strong>{{ i ? ' · ' : ' ' }}{{ v.label }}</strong> ({{ fmt(v.dt_inicio) }} a {{ fmt(v.dt_fim) }})</template>.
                    </div>
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Motivo</FormLabel>
                        <LocalCombobox v-model="form.jfa_mbf_id" :items="itemsMotivo" placeholder="Selecione o motivo..." :invalid="!!errors.jfa_mbf_id" />
                        <InputError :message="errors.jfa_mbf_id" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Data início</FormLabel>
                            <Input
                                v-model="form.jfa_dt_inicio"
                                type="date"
                                :min="minInicio"
                                :max="maxFim"
                                :class="{ 'border-red-500': errors.jfa_dt_inicio }"
                            />
                            <InputError :message="errors.jfa_dt_inicio" />
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Data fim</FormLabel>
                            <Input
                                v-model="form.jfa_dt_fim"
                                type="date"
                                :min="form.jfa_dt_inicio || minInicio"
                                :max="maxFim"
                                :class="{ 'border-red-500': errors.jfa_dt_fim }"
                            />
                            <InputError :message="errors.jfa_dt_fim" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <FormLabel>Observação</FormLabel>
                        <textarea
                            v-model="form.jfa_observacao"
                            rows="2"
                            maxlength="500"
                            placeholder="Detalhe a justificativa (opcional)..."
                            class="w-full resize-y rounded-md border bg-background p-2.5 text-sm outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-950"
                        ></textarea>
                        <InputError :message="errors.jfa_observacao" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t px-5 py-3">
                    <Button type="button" variant="outline" @click="fechar">Cancelar</Button>
                    <Button type="button" class="bg-indigo-600 hover:bg-indigo-700" :disabled="processing" @click="salvar">
                        <Loader2 v-if="processing" class="mr-2 size-4 animate-spin" />
                        Salvar
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
