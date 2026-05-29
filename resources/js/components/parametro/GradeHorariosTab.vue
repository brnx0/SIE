<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { GradeHorario, SegmentoResumo } from '@/types/parametro';
import { router } from '@inertiajs/vue3';
import { Check, Clock, Loader2, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    gradeHorarios: GradeHorario[];
    segmentos: SegmentoResumo[];
}>();

const porSegmento = computed(() => {
    const map = new Map<number, { segmento: SegmentoResumo; horarios: GradeHorario[] }>();
    for (const seg of props.segmentos) {
        map.set(seg.seg_id, { segmento: seg, horarios: [] });
    }
    for (const grh of props.gradeHorarios) {
        const entry = map.get(grh.grh_seg_id);
        if (entry) entry.horarios.push(grh);
    }
    return [...map.values()];
});

const showForm = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const TURNOS = [
    { value: 'm', label: 'Manhã' },
    { value: 't', label: 'Tarde' },
    { value: 'n', label: 'Noite' },
] as const;

const turnoLabel = (v: string) => TURNOS.find((t) => t.value === v)?.label ?? v;

const form = reactive({
    grh_seg_id: null as number | null,
    grh_turno: 'm' as 'm' | 't' | 'n',
    grh_hora: '',
    grh_ordem: '' as number | '',
});

const selectClass = (hasError: boolean) =>
    `flex h-9 w-full rounded-md border bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring ${hasError ? 'border-red-500 ring-1 ring-red-500' : 'border-input'}`;

const recalcOrdem = () => {
    if (form.grh_seg_id) {
        const existing = props.gradeHorarios.filter(
            (g) => g.grh_seg_id === form.grh_seg_id && g.grh_turno === form.grh_turno,
        );
        form.grh_ordem = existing.length + 1;
    } else {
        form.grh_ordem = '';
    }
};

const openForm = (segId?: number) => {
    cancelEdit();
    form.grh_seg_id = segId ?? null;
    form.grh_turno = 'm';
    form.grh_hora = '';
    recalcOrdem();
    errors.value = {};
    showForm.value = true;
};

const cancelForm = () => {
    showForm.value = false;
    errors.value = {};
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    router.post('/parametros/grade-horarios', form as Record<string, any>, {
        preserveScroll: true,
        onSuccess: () => {
            form.grh_hora = '';
            recalcOrdem();
        },
        onError: (e) => {
            errors.value = e;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

const remove = (grh: GradeHorario) => {
    if (!confirm(`Remover horário ${grh.grh_hora} do segmento?`)) return;
    router.delete(`/parametros/grade-horarios/${grh.grh_id}`, { preserveScroll: true });
};

// --- edição inline ---
const editingId = ref<number | null>(null);
const editProcessing = ref(false);
const editErrors = ref<Record<string, string>>({});
const editForm = reactive({ grh_turno: 'm' as 'm' | 't' | 'n', grh_hora: '', grh_ordem: '' as number | '' });

const startEdit = (grh: GradeHorario) => {
    cancelForm();
    editingId.value = grh.grh_id;
    editForm.grh_turno = grh.grh_turno as 'm' | 't' | 'n';
    editForm.grh_hora = grh.grh_hora.substring(0, 5);
    editForm.grh_ordem = grh.grh_ordem;
    editErrors.value = {};
};

const cancelEdit = () => {
    editingId.value = null;
    editErrors.value = {};
};

const saveEdit = (grh: GradeHorario) => {
    editProcessing.value = true;
    editErrors.value = {};

    router.put(`/parametros/grade-horarios/${grh.grh_id}`, editForm as Record<string, any>, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
        onError: (e) => {
            editErrors.value = e;
        },
        onFinish: () => {
            editProcessing.value = false;
        },
    });
};

const fmtHora = (h: string) => h.substring(0, 5);
</script>

<template>
    <div class="grid gap-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Grade de Horários por Segmento</h3>
            <Button v-if="!showForm" type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" @click="openForm()">
                <Plus class="mr-2 size-4" /> Novo Horário
            </Button>
        </div>

        <!-- Formulário inline novo -->
        <div v-if="showForm" class="rounded-lg border bg-background p-4">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-sm font-semibold">Novo Horário</h4>
                <button type="button" class="rounded p-1 hover:bg-muted" @click="cancelForm">
                    <X class="size-4" />
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-4">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Segmento</FormLabel>
                    <select v-model="form.grh_seg_id" :class="selectClass(!!errors.grh_seg_id)" @change="recalcOrdem">
                        <option :value="null">Selecione...</option>
                        <option v-for="seg in segmentos" :key="seg.seg_id" :value="seg.seg_id">
                            {{ seg.seg_nome_reduzido }}
                        </option>
                    </select>
                    <InputError :message="errors.grh_seg_id" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Turno</FormLabel>
                    <select v-model="form.grh_turno" :class="selectClass(!!errors.grh_turno)" @change="recalcOrdem">
                        <option v-for="t in TURNOS" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <InputError :message="errors.grh_turno" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Horário (HH:MM)</FormLabel>
                    <Input v-model="form.grh_hora" type="time" :class="{ 'border-red-500': errors.grh_hora }" />
                    <InputError :message="errors.grh_hora" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ordem</FormLabel>
                    <Input v-model.number="form.grh_ordem" type="number" min="1" max="99" placeholder="1" />
                    <InputError :message="errors.grh_ordem" />
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="cancelForm">Cancelar</Button>
                <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" :disabled="processing" @click="submit">
                    <Loader2 v-if="processing" class="mr-2 size-4 animate-spin" />
                    Adicionar
                </Button>
            </div>
        </div>

        <!-- Estado vazio -->
        <div
            v-if="gradeHorarios.length === 0 && !showForm"
            class="rounded-xl border-2 border-dashed bg-card py-10 text-center text-sm text-muted-foreground"
        >
            Nenhum horário cadastrado. Clique em "Novo Horário" para começar.
        </div>

        <!-- Grade por segmento -->
        <div
            v-for="{ segmento, horarios } in porSegmento"
            :key="segmento.seg_id"
            class="rounded-xl border bg-card shadow-sm"
        >
            <div class="flex items-center justify-between border-b bg-muted/30 px-4 py-3">
                <div class="flex items-center gap-2">
                    <Clock class="size-4 text-indigo-600" />
                    <span class="text-sm font-semibold">{{ segmento.seg_nome_reduzido }}</span>
                    <span class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                        {{ horarios.length }} tempo{{ horarios.length !== 1 ? 's' : '' }}
                    </span>
                </div>
                <Button type="button" size="sm" variant="outline" class="h-7 text-xs" @click="openForm(segmento.seg_id)">
                    <Plus class="mr-1 size-3" /> Adicionar
                </Button>
            </div>

            <div v-if="horarios.length === 0" class="px-4 py-6 text-center text-xs text-muted-foreground">
                Nenhum horário cadastrado para este segmento.
            </div>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="w-16 px-4 py-2 text-left font-medium text-muted-foreground">Ordem</th>
                        <th class="w-24 px-4 py-2 text-left font-medium text-muted-foreground">Turno</th>
                        <th class="px-4 py-2 text-left font-medium text-muted-foreground">Horário</th>
                        <th class="w-20 px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Linha em modo edição -->
                    <template v-for="grh in horarios" :key="grh.grh_id">
                        <tr v-if="editingId === grh.grh_id" class="border-b bg-indigo-50/40 dark:bg-indigo-900/10">
                            <td class="px-4 py-2">
                                <Input
                                    v-model.number="editForm.grh_ordem"
                                    type="number"
                                    min="1"
                                    max="99"
                                    class="h-7 w-16 text-sm"
                                    :class="{ 'border-red-500': editErrors.grh_ordem }"
                                />
                            </td>
                            <td class="px-4 py-2">
                                <select
                                    v-model="editForm.grh_turno"
                                    :class="selectClass(!!editErrors.grh_turno)"
                                    class="h-7 w-24 text-xs"
                                >
                                    <option v-for="t in TURNOS" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex flex-col gap-1">
                                    <Input
                                        v-model="editForm.grh_hora"
                                        type="time"
                                        class="h-7 w-32 text-sm"
                                        :class="{ 'border-red-500': editErrors.grh_hora }"
                                    />
                                    <InputError :message="editErrors.grh_hora ?? editErrors.grh_turno ?? editErrors.grh_ordem" />
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded p-1.5 hover:bg-green-100 dark:hover:bg-green-900/30"
                                        title="Salvar"
                                        :disabled="editProcessing"
                                        @click="saveEdit(grh)"
                                    >
                                        <Loader2 v-if="editProcessing" class="size-4 animate-spin text-indigo-500" />
                                        <Check v-else class="size-4 text-green-600" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded p-1.5 hover:bg-muted"
                                        title="Cancelar"
                                        @click="cancelEdit"
                                    >
                                        <X class="size-4 text-muted-foreground" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Linha normal -->
                        <tr v-else class="border-b last:border-0 hover:bg-muted/20">
                            <td class="px-4 py-2 text-muted-foreground">{{ grh.grh_ordem }}º</td>
                            <td class="px-4 py-2">
                                <span class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                    {{ turnoLabel(grh.grh_turno) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 font-medium tabular-nums">{{ fmtHora(grh.grh_hora) }}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded p-1.5 hover:bg-muted"
                                        title="Editar"
                                        @click="startEdit(grh)"
                                    >
                                        <Pencil class="size-4 text-indigo-500" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded p-1.5 hover:bg-muted"
                                        title="Remover"
                                        @click="remove(grh)"
                                    >
                                        <Trash2 class="size-4 text-rose-500" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
