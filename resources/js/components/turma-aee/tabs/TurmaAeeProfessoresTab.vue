<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { Button } from '@/components/ui/button';
import type { ProfessorResumo, Turma, TurmaProfessor } from '@/types/turma';
import { router } from '@inertiajs/vue3';
import { Loader2, Plus, RefreshCw, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    turma: Turma;
    professores: TurmaProfessor[];
    professoresDisponiveis: ProfessorResumo[];
}>();

const list = computed<TurmaProfessor[]>(() => props.professores ?? []);

const showForm = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const refreshing = ref(false);
const refresh = () => {
    refreshing.value = true;
    router.reload({
        only: ['professores', 'flash'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { refreshing.value = false; },
    });
};

const form = reactive<{ tup_fun_id: number | null }>({ tup_fun_id: null });

// Filtra do combobox os já alocados
const idsAlocados = computed(() => new Set(list.value.map((p) => p.tup_fun_id)));
const professoresItems = computed(() =>
    props.professoresDisponiveis
        .filter((p) => !idsAlocados.value.has(p.fun_id))
        .map((p) => ({ id: p.fun_id, label: p.fun_nome })),
);

const resetForm = () => {
    form.tup_fun_id = null;
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

    router.post(`/turmas-aee/${props.turma.tur_id}/professores`, form as Record<string, any>, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => resetForm(),
        onError: (e) => { errors.value = e; },
        onFinish: () => { processing.value = false; },
    });
};

const remove = (tup: TurmaProfessor) => {
    const label = tup.funcionario?.fun_nome ?? 'Professor';
    if (!confirm(`Remover alocação "${label}"?`)) return;

    router.delete(`/turmas-aee/${props.turma.tur_id}/professores/${tup.tup_id}`, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <div class="grid gap-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Professores Alocados</h3>
            <div class="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" :disabled="refreshing" title="Atualizar lista" @click="refresh">
                    <RefreshCw :class="['mr-1 size-4', refreshing && 'animate-spin']" /> Atualizar
                </Button>
                <Button v-if="!showForm" type="button" size="sm" variant="outline" @click="openForm">
                    <Plus class="mr-1 size-4" /> Adicionar Professor
                </Button>
            </div>
        </div>

        <!-- Formulário de adição -->
        <div v-if="showForm" class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-5 shadow-sm dark:border-indigo-800 dark:bg-indigo-900/20">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-sm font-medium">Nova Alocação</h4>
                <button type="button" class="rounded p-1 hover:bg-muted" @click="resetForm">
                    <X class="size-4" />
                </button>
            </div>

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
                <p v-if="professoresItems.length === 0" class="text-xs text-amber-600">
                    Nenhum professor disponível para alocar.
                </p>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="resetForm">Cancelar</Button>
                <Button type="button" size="sm" :disabled="processing" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="submit">
                    <Loader2 v-if="processing" class="mr-1 size-4 animate-spin" />
                    Adicionar
                </Button>
            </div>
        </div>

        <!-- Vazio -->
        <div v-if="list.length === 0 && !showForm" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
            Nenhum professor alocado. Clique em "Adicionar Professor" para começar.
        </div>

        <!-- Lista -->
        <div v-if="list.length > 0" class="rounded-xl border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/30">
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Professor</th>
                        <th class="w-12 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tup in list" :key="tup.tup_id" class="border-b last:border-0 hover:bg-muted/20">
                        <td class="px-4 py-3 font-medium">{{ tup.funcionario?.fun_nome ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <button type="button" class="rounded p-1.5 hover:bg-muted" title="Remover alocação" @click="remove(tup)">
                                <Trash2 class="size-4 text-rose-500" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
