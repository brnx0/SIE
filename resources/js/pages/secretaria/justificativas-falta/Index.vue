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
import { Loader2, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }
interface Motivo { mbf_id: number; mbf_descricao: string }
interface Justificativa {
    jfa_id: number;
    jfa_esc_id: number;
    jfa_tur_id: number;
    jfa_aln_id: number;
    jfa_mbf_id: number;
    aluno: string | null;
    aln_nr_matricula: string | null;
    motivo: string | null;
    dt_inicio: string;
    dt_fim: string;
    observacao: string | null;
}

const props = defineProps<{
    justificativas: Justificativa[];
    filtros: { anl_id: number | null; esc_id: number | null; tur_id: number | null; aln_id: number | null };
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
const busca = ref('');

const itemsAno = computed(() => props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) })));
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsMotivo = computed(() => props.motivos.map((m) => ({ id: m.mbf_id, label: m.mbf_descricao })));

const aplicar = () => {
    router.get('/secretaria/justificativas-falta', { anl_id: anlId.value, esc_id: escId.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
watch([anlId, escId], aplicar);

const lista = computed(() => {
    const q = busca.value.trim().toLowerCase();
    const l = props.justificativas ?? [];
    if (!q) return l;
    return l.filter((j) => (j.aluno ?? '').toLowerCase().includes(q) || String(j.aln_nr_matricula ?? '').includes(q));
});

const fmtData = (s: string) => {
    if (!s) return '—';
    const [y, m, d] = s.substring(0, 10).split('-');
    return `${d}/${m}/${y}`;
};

// ── Modal (criar/editar) ─────────────────────────────────────────────────────
const showModal = ref(false);
const editId = ref<number | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const turmas = ref<{ tur_id: number; tur_nome: string; ser_nome: string | null }[]>([]);
const alunos = ref<{ aln_id: number; aln_nome: string }[]>([]);

const form = reactive({
    jfa_esc_id: null as number | null,
    jfa_tur_id: null as number | null,
    jfa_aln_id: null as number | null,
    jfa_mbf_id: null as number | null,
    jfa_dt_inicio: '',
    jfa_dt_fim: '',
    jfa_observacao: '',
});

const itemsTurma = computed(() => turmas.value.map((t) => ({ id: t.tur_id, label: (t.ser_nome ? t.ser_nome + ' - ' : '') + t.tur_nome })));
const itemsAluno = computed(() => alunos.value.map((a) => ({ id: a.aln_id, label: a.aln_nome })));

const getJson = async (url: string) => {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    return r.ok ? await r.json() : [];
};

let ignoreTurmaWatch = false;
const loadTurmas = async () => {
    turmas.value = [];
    if (!anlId.value || !form.jfa_esc_id) return;
    turmas.value = await getJson(`/secretaria/justificativas-falta/turmas?anl_id=${anlId.value}&esc_id=${form.jfa_esc_id}`);
};
const loadAlunos = async () => {
    alunos.value = [];
    if (!form.jfa_tur_id) return;
    alunos.value = await getJson(`/secretaria/justificativas-falta/alunos?tur_id=${form.jfa_tur_id}`);
};
watch(() => form.jfa_esc_id, () => {
    if (ignoreTurmaWatch) return;
    form.jfa_tur_id = null;
    form.jfa_aln_id = null;
    turmas.value = [];
    alunos.value = [];
    loadTurmas();
});
watch(() => form.jfa_tur_id, () => {
    if (ignoreTurmaWatch) return;
    form.jfa_aln_id = null;
    loadAlunos();
});

const abrirNovo = async () => {
    editId.value = null;
    errors.value = {};
    ignoreTurmaWatch = true;
    form.jfa_esc_id = escId.value;
    form.jfa_tur_id = null;
    form.jfa_aln_id = null;
    form.jfa_mbf_id = null;
    form.jfa_dt_inicio = '';
    form.jfa_dt_fim = '';
    form.jfa_observacao = '';
    alunos.value = [];
    showModal.value = true;
    await loadTurmas();
    ignoreTurmaWatch = false;
};

const abrirEdit = async (j: Justificativa) => {
    editId.value = j.jfa_id;
    errors.value = {};
    ignoreTurmaWatch = true;
    form.jfa_esc_id = j.jfa_esc_id;
    form.jfa_tur_id = j.jfa_tur_id;
    form.jfa_aln_id = j.jfa_aln_id;
    form.jfa_mbf_id = j.jfa_mbf_id;
    form.jfa_dt_inicio = j.dt_inicio?.substring(0, 10) ?? '';
    form.jfa_dt_fim = j.dt_fim?.substring(0, 10) ?? '';
    form.jfa_observacao = j.observacao ?? '';
    showModal.value = true;
    await Promise.all([loadTurmas(), loadAlunos()]);
    ignoreTurmaWatch = false;
};

const fechar = () => { showModal.value = false; editId.value = null; errors.value = {}; };

const salvar = () => {
    processing.value = true;
    errors.value = {};
    const payload = {
        jfa_anl_id: anlId.value,
        jfa_esc_id: form.jfa_esc_id,
        jfa_tur_id: form.jfa_tur_id,
        jfa_aln_id: form.jfa_aln_id,
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

const remover = (j: Justificativa) => {
    if (!confirm(`Remover a justificativa de ${j.aluno} (${fmtData(j.dt_inicio)} a ${fmtData(j.dt_fim)})?`)) return;
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
                    <p class="text-sm text-slate-500 dark:text-slate-400">Registre o abono de faltas do aluno por período.</p>
                </div>
                <div class="flex items-center gap-2">
                    <RefreshButton />
                    <Button type="button" class="bg-indigo-600 hover:bg-indigo-700" :disabled="!anlId || !escId" @click="abrirNovo">
                        <Plus class="mr-2 size-4" /> Nova Justificativa
                    </Button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel>Ano Letivo</FormLabel>
                    <LocalCombobox v-model="anlId" :items="itemsAno" placeholder="Ano..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Escola</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" placeholder="Escola..." />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Buscar aluno</FormLabel>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="busca" placeholder="Nome ou matrícula..." class="pl-8" />
                    </div>
                </div>
            </div>

            <!-- Listagem -->
            <div class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-indigo-600 text-white">
                        <tr>
                            <th class="px-3 py-2 font-semibold">Aluno</th>
                            <th class="w-44 px-3 py-2 font-semibold">Período</th>
                            <th class="px-3 py-2 font-semibold">Motivo</th>
                            <th class="px-3 py-2 font-semibold">Observação</th>
                            <th class="w-28 px-3 py-2 text-right font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!lista.length">
                            <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">Nenhuma justificativa encontrada.</td>
                        </tr>
                        <tr v-for="(j, idx) in lista" :key="j.jfa_id" :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ j.aluno }}</div>
                                <div v-if="j.aln_nr_matricula" class="text-xs text-muted-foreground">Mat. {{ j.aln_nr_matricula }}</div>
                            </td>
                            <td class="px-3 py-2 tabular-nums">{{ fmtData(j.dt_inicio) }} – {{ fmtData(j.dt_fim) }}</td>
                            <td class="px-3 py-2">{{ j.motivo }}</td>
                            <td class="px-3 py-2 text-muted-foreground">{{ j.observacao || '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex justify-end gap-1">
                                    <Button type="button" variant="ghost" size="sm" @click="abrirEdit(j)" aria-label="Editar"><Pencil class="size-4" /></Button>
                                    <Button type="button" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remover(j)" aria-label="Remover"><Trash2 class="size-4" /></Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="fechar">
            <div class="w-full max-w-xl rounded-2xl bg-card shadow-2xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h2 class="text-base font-semibold">{{ editId ? 'Editar' : 'Nova' }} Justificativa</h2>
                    <Button variant="ghost" size="sm" @click="fechar"><X class="size-5" /></Button>
                </div>
                <div class="grid gap-4 p-5">
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Escola</FormLabel>
                        <LocalCombobox v-model="form.jfa_esc_id" :items="itemsEscola" placeholder="Selecione a escola..." :invalid="!!errors.jfa_esc_id" />
                        <InputError :message="errors.jfa_esc_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Turma</FormLabel>
                        <LocalCombobox v-model="form.jfa_tur_id" :items="itemsTurma" placeholder="Selecione a turma..." :invalid="!!errors.jfa_tur_id" />
                        <InputError :message="errors.jfa_tur_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Aluno</FormLabel>
                        <LocalCombobox v-model="form.jfa_aln_id" :items="itemsAluno" placeholder="Selecione o aluno..." :invalid="!!errors.jfa_aln_id" />
                        <InputError :message="errors.jfa_aln_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Motivo</FormLabel>
                        <LocalCombobox v-model="form.jfa_mbf_id" :items="itemsMotivo" placeholder="Selecione o motivo..." :invalid="!!errors.jfa_mbf_id" />
                        <InputError :message="errors.jfa_mbf_id" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Data início</FormLabel>
                            <Input v-model="form.jfa_dt_inicio" type="date" :class="{ 'border-red-500': errors.jfa_dt_inicio }" />
                            <InputError :message="errors.jfa_dt_inicio" />
                        </div>
                        <div class="grid gap-1.5">
                            <FormLabel :required="true">Data fim</FormLabel>
                            <Input v-model="form.jfa_dt_fim" type="date" :min="form.jfa_dt_inicio || undefined" :class="{ 'border-red-500': errors.jfa_dt_fim }" />
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
