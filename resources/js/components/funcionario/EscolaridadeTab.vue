<script setup lang="ts">
import CursoFormacaoCombobox from '@/components/funcionario/CursoFormacaoCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ESCOLARIDADES, NIVEIS_EXIGEM_CURSO } from '@/lib/funcionarioEnums';
import type {
    CursoFormacaoSuperior,
    EscolaridadeFormData,
    Funcionario,
    FuncionarioEscolaridade,
} from '@/types/funcionario';
import { router } from '@inertiajs/vue3';
import { Edit3, GraduationCap, Loader2, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    funcionario?: Funcionario;
    modelValue?: EscolaridadeFormData[];
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: EscolaridadeFormData[]): void;
}>();

const nivelLabel = (n: number | '' | null | undefined): string =>
    ESCOLARIDADES.find((e) => e.value === Number(n))?.label ?? '—';

const exigeCurso = (n: number | '' | null | undefined): boolean =>
    NIVEIS_EXIGEM_CURSO.includes(Number(n));

const novaLinha = (): EscolaridadeFormData => ({
    fes_nivel: '',
    fes_ano_inicio: '',
    fes_ano_fim: '',
    fes_cfs_id: null,
    curso: null,
});

// ─── Modo CREATE (lista inline no formulário do funcionário) ─────────────

const rows = computed<EscolaridadeFormData[]>(() => props.modelValue ?? []);

const patchRow = (i: number, patch: Partial<EscolaridadeFormData>) => {
    emit(
        'update:modelValue',
        rows.value.map((r, idx) => (idx === i ? { ...r, ...patch } : r)),
    );
};

const addRow = () => {
    emit('update:modelValue', [...rows.value, novaLinha()]);
};

const removeRow = (i: number) => {
    if (rows.value.length <= 1) return;
    emit(
        'update:modelValue',
        rows.value.filter((_, idx) => idx !== i),
    );
};

const onCreateNivel = (i: number, value: string) => {
    const nivel = value === '' ? '' : (Number(value) as number);
    const patch: Partial<EscolaridadeFormData> = { fes_nivel: nivel };
    // Ao mudar para um nível que não exige curso, limpa o curso selecionado.
    if (!exigeCurso(nivel)) {
        patch.fes_cfs_id = null;
        patch.curso = null;
    }
    patchRow(i, patch);
};

const errText = (i: number, field: string): string | undefined =>
    props.errors?.[`escolaridades.${i}.${field}`];

// ─── Modo EDIT (CRUD via rotas próprias) ─────────────────────────────────

const escolaridades = computed<FuncionarioEscolaridade[]>(
    () => props.funcionario?.escolaridades ?? [],
);

const showForm = ref(false);
const editingId = ref<number | null>(null);
const processing = ref(false);
const errs = ref<Record<string, string>>({});
const cursoInitial = ref<CursoFormacaoSuperior | null>(null);

const form = reactive<EscolaridadeFormData>(novaLinha());

const formExigeCurso = computed(() => exigeCurso(form.fes_nivel));

const resetForm = () => {
    Object.assign(form, novaLinha());
    cursoInitial.value = null;
    errs.value = {};
    editingId.value = null;
    showForm.value = false;
};

const openNew = () => {
    resetForm();
    showForm.value = true;
};

const openEdit = (esc: FuncionarioEscolaridade) => {
    form.fes_nivel = esc.fes_nivel;
    form.fes_ano_inicio = esc.fes_ano_inicio ?? '';
    form.fes_ano_fim = esc.fes_ano_fim ?? '';
    form.fes_cfs_id = esc.fes_cfs_id;
    form.curso = esc.curso ?? null;
    cursoInitial.value = esc.curso ?? null;
    errs.value = {};
    editingId.value = esc.fes_id;
    showForm.value = true;
};

const onEditNivel = (value: string) => {
    form.fes_nivel = value === '' ? '' : (Number(value) as number);
    if (!exigeCurso(form.fes_nivel)) {
        form.fes_cfs_id = null;
        form.curso = null;
        cursoInitial.value = null;
    }
};

const onCursoSelect = (curso: CursoFormacaoSuperior | null) => {
    form.fes_cfs_id = curso?.cfs_id ?? null;
    form.curso = curso;
    cursoInitial.value = curso;
};

const numOuNull = (v: number | '' | null): number | null =>
    v === '' || v === null ? null : Number(v);

// Limita o ano a no máximo 4 dígitos numéricos.
const ano4 = (v: string | number): number | '' => {
    const digits = String(v ?? '').replace(/\D/g, '').slice(0, 4);
    return digits === '' ? '' : Number(digits);
};

const submit = () => {
    if (!props.funcionario) return;
    processing.value = true;
    errs.value = {};

    const data = {
        fes_nivel: form.fes_nivel === '' ? null : Number(form.fes_nivel),
        fes_ano_inicio: numOuNull(form.fes_ano_inicio),
        fes_ano_fim: numOuNull(form.fes_ano_fim),
        fes_cfs_id: form.fes_cfs_id ?? null,
    };

    const base = `/funcionarios/${props.funcionario.fun_id}/escolaridades`;
    const url = editingId.value ? `${base}/${editingId.value}` : base;
    const method = editingId.value ? 'put' : 'post';

    router[method](url, data, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => resetForm(),
        onError: (errors) => {
            errs.value = errors;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

const remove = (esc: FuncionarioEscolaridade) => {
    if (!props.funcionario) return;
    if (!confirm(`Remover a escolaridade "${nivelLabel(esc.fes_nivel)}"?`)) return;

    router.delete(`/funcionarios/${props.funcionario.fun_id}/escolaridades/${esc.fes_id}`, {
        preserveScroll: true,
        preserveState: true,
    });
};

const periodo =(ini: number | null | '', fim: number | null | ''): string => {
    const a = ini || '';
    const b = fim || '';
    if (!a && !b) return '—';
    return `${a || '…'} – ${b || '…'}`;
};
</script>

<template>
    <!-- ══════════ MODO CREATE ══════════ -->
    <div v-if="mode === 'create'" class="grid gap-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold">Escolaridades</h3>
                <p class="text-xs text-muted-foreground">
                    Informe ao menos uma escolaridade. O curso é obrigatório para níveis de ensino superior.
                </p>
            </div>
            <Button type="button" variant="outline" size="sm" @click="addRow">
                <Plus class="mr-1 size-4" /> Adicionar
            </Button>
        </div>

        <div
            v-for="(row, i) in rows"
            :key="i"
            class="grid gap-4 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2"
        >
            <div class="flex items-center justify-between sm:col-span-2">
                <span class="text-xs font-semibold text-muted-foreground">Escolaridade {{ i + 1 }}</span>
                <button
                    type="button"
                    :disabled="rows.length <= 1"
                    class="rounded p-1 text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-rose-950/40"
                    title="Remover"
                    @click="removeRow(i)"
                >
                    <Trash2 class="size-4" />
                </button>
            </div>

            <div class="grid gap-2">
                <FormLabel :required="true">Nível de escolaridade</FormLabel>
                <select
                    :value="row.fes_nivel === '' ? '' : row.fes_nivel"
                    class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                    @change="(e) => onCreateNivel(i, (e.target as HTMLSelectElement).value)"
                >
                    <option value="">Selecione...</option>
                    <option v-for="opt in ESCOLARIDADES" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
                <InputError :message="errText(i, 'fes_nivel')" />
            </div>

            <div class="grid gap-2">
                <FormLabel :required="exigeCurso(row.fes_nivel)">Curso</FormLabel>
                <CursoFormacaoCombobox
                    :model-value="row.fes_cfs_id"
                    :initial="row.curso ?? null"
                    :invalid="!!errText(i, 'fes_cfs_id')"
                    :placeholder="exigeCurso(row.fes_nivel) ? 'Buscar curso...' : 'Opcional para este nível'"
                    @update:model-value="(v) => patchRow(i, { fes_cfs_id: v })"
                    @select="(c) => patchRow(i, { curso: c })"
                />
                <InputError :message="errText(i, 'fes_cfs_id')" />
            </div>

            <div class="grid gap-2">
                <FormLabel>Ano inicial</FormLabel>
                <Input
                    type="text"
                    inputmode="numeric"
                    maxlength="4"
                    placeholder="Ex.: 2015"
                    :model-value="row.fes_ano_inicio ?? ''"
                    @update:model-value="(v) => patchRow(i, { fes_ano_inicio: ano4(v) })"
                />
                <InputError :message="errText(i, 'fes_ano_inicio')" />
            </div>

            <div class="grid gap-2">
                <FormLabel>Ano final</FormLabel>
                <Input
                    type="text"
                    inputmode="numeric"
                    maxlength="4"
                    placeholder="Ex.: 2019"
                    :model-value="row.fes_ano_fim ?? ''"
                    @update:model-value="(v) => patchRow(i, { fes_ano_fim: ano4(v) })"
                />
                <InputError :message="errText(i, 'fes_ano_fim')" />
            </div>
        </div>

        <p v-if="errors?.escolaridades" class="text-sm text-rose-600">{{ errors.escolaridades }}</p>
    </div>

    <!-- ══════════ MODO EDIT ══════════ -->
    <div v-else class="grid gap-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold">Escolaridades</h3>
                <p class="text-xs text-muted-foreground">
                    O funcionário deve ter ao menos uma escolaridade cadastrada.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <RefreshButton />
                <Button type="button" variant="outline" size="sm" @click="openNew">
                    <Plus class="mr-1 size-4" /> Nova escolaridade
                </Button>
            </div>
        </div>

        <!-- Formulário inline (novo / edição) -->
        <div v-if="showForm" class="grid gap-4 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2">
            <div class="flex items-center justify-between sm:col-span-2">
                <span class="text-sm font-semibold">
                    {{ editingId ? 'Editar escolaridade' : 'Nova escolaridade' }}
                </span>
                <button type="button" class="rounded p-1 hover:bg-muted" title="Cancelar" @click="resetForm">
                    <X class="size-4" />
                </button>
            </div>

            <div class="grid gap-2">
                <FormLabel :required="true">Nível de escolaridade</FormLabel>
                <select
                    :value="form.fes_nivel === '' ? '' : form.fes_nivel"
                    class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                    @change="(e) => onEditNivel((e.target as HTMLSelectElement).value)"
                >
                    <option value="">Selecione...</option>
                    <option v-for="opt in ESCOLARIDADES" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
                <InputError :message="errs.fes_nivel" />
            </div>

            <div class="grid gap-2">
                <FormLabel :required="formExigeCurso">Curso</FormLabel>
                <CursoFormacaoCombobox
                    :model-value="form.fes_cfs_id"
                    :initial="cursoInitial"
                    :invalid="!!errs.fes_cfs_id"
                    :placeholder="formExigeCurso ? 'Buscar curso...' : 'Opcional para este nível'"
                    @update:model-value="(v) => (form.fes_cfs_id = v)"
                    @select="onCursoSelect"
                />
                <InputError :message="errs.fes_cfs_id" />
            </div>

            <div class="grid gap-2">
                <FormLabel>Ano inicial</FormLabel>
                <Input
                    type="text"
                    inputmode="numeric"
                    maxlength="4"
                    placeholder="Ex.: 2015"
                    :model-value="form.fes_ano_inicio ?? ''"
                    @update:model-value="(v) => (form.fes_ano_inicio = ano4(v))"
                />
                <InputError :message="errs.fes_ano_inicio" />
            </div>

            <div class="grid gap-2">
                <FormLabel>Ano final</FormLabel>
                <Input
                    type="text"
                    inputmode="numeric"
                    maxlength="4"
                    placeholder="Ex.: 2019"
                    :model-value="form.fes_ano_fim ?? ''"
                    @update:model-value="(v) => (form.fes_ano_fim = ano4(v))"
                />
                <InputError :message="errs.fes_ano_fim" />
            </div>

            <div class="flex justify-end gap-2 sm:col-span-2">
                <Button type="button" variant="outline" @click="resetForm">Cancelar</Button>
                <Button
                    type="button"
                    :disabled="processing"
                    class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
                    @click="submit"
                >
                    <Loader2 v-if="processing" class="mr-2 size-4 animate-spin" />
                    {{ editingId ? 'Salvar' : 'Adicionar' }}
                </Button>
            </div>
        </div>

        <!-- Lista -->
        <div
            v-if="escolaridades.length === 0"
            class="rounded-xl border-2 border-dashed bg-card py-10 text-center text-sm text-muted-foreground shadow-sm"
        >
            Nenhuma escolaridade cadastrada.
        </div>

        <div
            v-for="esc in escolaridades"
            :key="esc.fes_id"
            class="flex items-start justify-between gap-4 rounded-xl border bg-card p-4 shadow-sm"
        >
            <div class="flex items-start gap-3">
                <GraduationCap class="mt-0.5 size-5 shrink-0 text-sky-600 dark:text-sky-400" />
                <div class="grid gap-0.5">
                    <span class="text-sm font-semibold">{{ nivelLabel(esc.fes_nivel) }}</span>
                    <span v-if="esc.curso" class="text-sm">
                        <span class="font-mono text-xs text-muted-foreground">{{ esc.curso.cfs_codigo }}</span>
                        — {{ esc.curso.cfs_nome }}
                    </span>
                    <span class="text-xs text-muted-foreground">
                        Período: {{ periodo(esc.fes_ano_inicio, esc.fes_ano_fim) }}
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="rounded p-1.5 text-slate-500 hover:bg-muted"
                    title="Editar"
                    @click="openEdit(esc)"
                >
                    <Edit3 class="size-4" />
                </button>
                <button
                    type="button"
                    class="rounded p-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                    title="Remover"
                    @click="remove(esc)"
                >
                    <Trash2 class="size-4" />
                </button>
            </div>
        </div>
    </div>
</template>
