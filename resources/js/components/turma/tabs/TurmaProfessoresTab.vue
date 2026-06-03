<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { Button } from '@/components/ui/button';
import type { DisciplinaResumo, ProfessorResumo, Turma, TurmaProfessor, TurmaProfessorApoio } from '@/types/turma';
import { router } from '@inertiajs/vue3';
import { Check, Loader2, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    turma: Turma;
    professores: TurmaProfessor[];
    professoresApoio: TurmaProfessorApoio[];
    professoresDisponiveis: ProfessorResumo[];
    disciplinas: DisciplinaResumo[];
}>();

const list = ref<TurmaProfessor[]>(props.professores ?? []);

watch(
    () => props.professores,
    (v) => {
        list.value = v ?? [];
    },
);

const showForm = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = reactive<{ tup_fun_id: number | null; tup_dis_id: number | null }>({
    tup_fun_id: null,
    tup_dis_id: null,
});

const professoresItems = computed(() =>
    props.professoresDisponiveis.map((p) => ({ id: p.fun_id, label: p.fun_nome })),
);

const disciplinasItems = computed(() =>
    props.disciplinas.map((d) => ({ id: d.dis_id, label: d.dis_nome })),
);

const resetForm = () => {
    form.tup_fun_id = null;
    form.tup_dis_id = null;
    errors.value = {};
    showForm.value = false;
};

const openForm = () => {
    resetForm();
    showForm.value = true;
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    router.post(`/turmas/${props.turma.tur_id}/professores`, form as Record<string, any>, {
        preserveScroll: true,
        onSuccess: () => resetForm(),
        onError: (e) => {
            errors.value = e;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

const remove = (tup: TurmaProfessor) => {
    const label = `${tup.funcionario?.fun_nome ?? 'Professor'} / ${tup.disciplina?.dis_nome ?? 'Disciplina'}`;
    if (!confirm(`Remover alocação "${label}"?`)) return;

    router.delete(`/turmas/${props.turma.tur_id}/professores/${tup.tup_id}`, {
        preserveScroll: true,
    });
};

// ── Professores de Apoio ──────────────────────────────────────────────────────
const listApoio = ref<TurmaProfessorApoio[]>(props.professoresApoio ?? []);
watch(() => props.professoresApoio, (v) => { listApoio.value = v ?? []; });

const showFormApoio    = ref(false);
const processingApoio  = ref(false);
const errorsApoio      = ref<Record<string, string>>({});

const formApoio = reactive<{ tpa_fun_id: number | null; tpa_obs: string }>({
    tpa_fun_id: null,
    tpa_obs: '',
});

// Filtra do combobox os já alocados
const idsApoioAlocados = computed(() => new Set(listApoio.value.map(a => a.tpa_fun_id)));
const professoresApoioItems = computed(() =>
    props.professoresDisponiveis
        .filter(p => !idsApoioAlocados.value.has(p.fun_id))
        .map(p => ({ id: p.fun_id, label: p.fun_nome })),
);

const resetFormApoio = () => {
    formApoio.tpa_fun_id = null;
    formApoio.tpa_obs    = '';
    errorsApoio.value    = {};
    showFormApoio.value  = false;
};

const openFormApoio = () => {
    resetFormApoio();
    showFormApoio.value = true;
};

const submitApoio = () => {
    processingApoio.value = true;
    errorsApoio.value     = {};

    router.post(`/turmas/${props.turma.tur_id}/professores-apoio`, formApoio as Record<string, any>, {
        preserveScroll: true,
        onSuccess: () => resetFormApoio(),
        onError:   (e) => { errorsApoio.value = e; },
        onFinish:  () => { processingApoio.value = false; },
    });
};

// Edit inline da observação
const editApoioId  = ref<number | null>(null);
const editApoioObs = ref<string>('');
const savingEdit   = ref<number | null>(null);

const startEditApoio = (apoio: TurmaProfessorApoio) => {
    editApoioId.value  = apoio.tpa_id;
    editApoioObs.value = apoio.tpa_obs ?? '';
};

const cancelEditApoio = () => {
    editApoioId.value  = null;
    editApoioObs.value = '';
};

const saveEditApoio = (apoio: TurmaProfessorApoio) => {
    savingEdit.value = apoio.tpa_id;
    router.put(
        `/turmas/${props.turma.tur_id}/professores-apoio/${apoio.tpa_id}`,
        { tpa_obs: editApoioObs.value },
        {
            preserveScroll: true,
            onSuccess: () => cancelEditApoio(),
            onFinish: () => { savingEdit.value = null; },
        },
    );
};

const removeApoio = (apoio: TurmaProfessorApoio) => {
    const label = apoio.funcionario?.fun_nome ?? 'Professor de apoio';
    if (!confirm(`Remover "${label}" da lista de apoio?`)) return;

    router.delete(`/turmas/${props.turma.tur_id}/professores-apoio/${apoio.tpa_id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="grid gap-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Professores Alocados</h3>
            <Button v-if="!showForm" type="button" size="sm" variant="outline" @click="openForm">
                <Plus class="mr-1 size-4" /> Adicionar Professor
            </Button>
        </div>

        <!-- Formulário de adição -->
        <div v-if="showForm" class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-5 shadow-sm dark:border-indigo-800 dark:bg-indigo-900/20">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-sm font-medium">Nova Alocação</h4>
                <button type="button" class="rounded p-1 hover:bg-muted" @click="resetForm">
                    <X class="size-4" />
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Professor</FormLabel>
                    <LocalCombobox
                        :model-value="form.tup_fun_id"
                        :items="professoresItems"
                        :invalid="!!errors.tup_fun_id"
                        placeholder="Buscar professor..."
                        @update:model-value="(v) => (form.tup_fun_id = v)"
                    />
                    <InputError :message="errors.tup_fun_id" />
                    <p v-if="professoresDisponiveis.length === 0" class="text-xs text-amber-600">
                        Nenhum funcionário com lotação nesta escola.
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Disciplina</FormLabel>
                    <LocalCombobox
                        :model-value="form.tup_dis_id"
                        :items="disciplinasItems"
                        :invalid="!!errors.tup_dis_id"
                        placeholder="Buscar disciplina..."
                        @update:model-value="(v) => (form.tup_dis_id = v)"
                    />
                    <InputError :message="errors.tup_dis_id" />
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="resetForm">Cancelar</Button>
                <Button
                    type="button"
                    size="sm"
                    :disabled="processing"
                    class="bg-indigo-600 text-white hover:bg-indigo-700"
                    @click="submit"
                >
                    <Loader2 v-if="processing" class="mr-1 size-4 animate-spin" />
                    Adicionar
                </Button>
            </div>
        </div>

        <!-- Estado vazio -->
        <div
            v-if="list.length === 0 && !showForm"
            class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm"
        >
            Nenhum professor alocado. Clique em "Adicionar Professor" para começar.
        </div>

        <!-- Lista -->
        <div v-if="list.length > 0" class="rounded-xl border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/30">
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Professor</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Disciplina</th>
                        <th class="w-12 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="tup in list"
                        :key="tup.tup_id"
                        class="border-b last:border-0 hover:bg-muted/20"
                    >
                        <td class="px-4 py-3 font-medium">{{ tup.funcionario?.fun_nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ tup.disciplina?.dis_nome ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="rounded p-1.5 hover:bg-muted"
                                title="Remover alocação"
                                @click="remove(tup)"
                            >
                                <Trash2 class="size-4 text-rose-500" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ──────────── Professores de Apoio ──────────── -->
        <div class="mt-4 border-t pt-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Professores de Apoio</h3>
                <Button v-if="!showFormApoio" type="button" size="sm" variant="outline" @click="openFormApoio">
                    <Plus class="mr-1 size-4" /> Adicionar Professor de Apoio
                </Button>
            </div>

            <!-- Formulário -->
            <div v-if="showFormApoio" class="mt-4 rounded-xl border border-fuchsia-200 bg-fuchsia-50/50 p-5 shadow-sm dark:border-fuchsia-800 dark:bg-fuchsia-900/20">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-sm font-medium">Novo Professor de Apoio</h4>
                    <button type="button" class="rounded p-1 hover:bg-muted" @click="resetFormApoio">
                        <X class="size-4" />
                    </button>
                </div>

                <div class="grid gap-4">
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Professor</FormLabel>
                        <LocalCombobox
                            :model-value="formApoio.tpa_fun_id"
                            :items="professoresApoioItems"
                            :invalid="!!errorsApoio.tpa_fun_id"
                            placeholder="Buscar professor..."
                            @update:model-value="(v) => (formApoio.tpa_fun_id = v)"
                        />
                        <InputError :message="errorsApoio.tpa_fun_id" />
                        <p v-if="professoresApoioItems.length === 0" class="text-xs text-amber-600">
                            Todos os professores disponíveis já estão alocados como apoio.
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <FormLabel>Observação</FormLabel>
                        <textarea
                            v-model="formApoio.tpa_obs"
                            rows="3"
                            maxlength="500"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-fuchsia-500 resize-none"
                            placeholder="Observações sobre o apoio (opcional)..."
                        />
                        <InputError :message="errorsApoio.tpa_obs" />
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" @click="resetFormApoio">Cancelar</Button>
                    <Button
                        type="button"
                        size="sm"
                        :disabled="processingApoio"
                        class="bg-fuchsia-600 text-white hover:bg-fuchsia-700"
                        @click="submitApoio"
                    >
                        <Loader2 v-if="processingApoio" class="mr-1 size-4 animate-spin" />
                        Adicionar
                    </Button>
                </div>
            </div>

            <!-- Vazio -->
            <div
                v-if="listApoio.length === 0 && !showFormApoio"
                class="mt-4 rounded-xl border-2 border-dashed bg-card py-10 text-center text-sm text-muted-foreground shadow-sm"
            >
                Nenhum professor de apoio alocado.
            </div>

            <!-- Lista -->
            <div v-if="listApoio.length > 0" class="mt-4 rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/30">
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Professor</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Observação</th>
                            <th class="w-12 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="apoio in listApoio"
                            :key="apoio.tpa_id"
                            class="border-b last:border-0 hover:bg-muted/20"
                        >
                            <td class="px-4 py-3 font-medium">{{ apoio.funcionario?.fun_nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground text-xs">
                                <textarea
                                    v-if="editApoioId === apoio.tpa_id"
                                    v-model="editApoioObs"
                                    rows="2"
                                    maxlength="500"
                                    class="w-full rounded-md border border-input bg-background px-2 py-1 text-xs shadow-sm focus:outline-none focus:ring-2 focus:ring-fuchsia-500 resize-none"
                                />
                                <span v-else class="whitespace-pre-wrap">{{ apoio.tpa_obs || '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <template v-if="editApoioId === apoio.tpa_id">
                                        <button
                                            type="button"
                                            class="rounded p-1.5 hover:bg-muted"
                                            title="Salvar"
                                            :disabled="savingEdit === apoio.tpa_id"
                                            @click="saveEditApoio(apoio)"
                                        >
                                            <Loader2 v-if="savingEdit === apoio.tpa_id" class="size-4 animate-spin text-fuchsia-600" />
                                            <Check v-else class="size-4 text-emerald-600" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded p-1.5 hover:bg-muted"
                                            title="Cancelar"
                                            @click="cancelEditApoio"
                                        >
                                            <X class="size-4 text-muted-foreground" />
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button
                                            type="button"
                                            class="rounded p-1.5 hover:bg-muted"
                                            title="Editar observação"
                                            @click="startEditApoio(apoio)"
                                        >
                                            <Pencil class="size-4 text-fuchsia-600" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded p-1.5 hover:bg-muted"
                                            title="Remover apoio"
                                            @click="removeApoio(apoio)"
                                        >
                                            <Trash2 class="size-4 text-rose-500" />
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
