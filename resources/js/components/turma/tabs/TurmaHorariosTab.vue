<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type {
    DisciplinaResumo,
    GradeHorarioResumo,
    ProfessorResumo,
    Turma,
    TurmaHorario,
} from '@/types/turma';
import { DIAS_SEMANA } from '@/types/turma';
import { router } from '@inertiajs/vue3';
import { AlertTriangle, Loader2, Plus, Printer, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    turma: Turma;
    gradeHorarios: GradeHorarioResumo[];
    horarios: TurmaHorario[];
    professoresDisponiveis: ProfessorResumo[];
    disciplinas: DisciplinaResumo[];
}>();

const list = ref<TurmaHorario[]>(props.horarios ?? []);
watch(() => props.horarios, (v) => { list.value = v ?? []; });

// Dias ativos da turma
const diasAtivos = computed(() =>
    DIAS_SEMANA.filter((d) => props.turma.tur_dias_funcionamento?.includes(d.value as any)),
);

// Lookup: `${dia}:${grh_id}` → TurmaHorario
const horarioMap = computed(() => {
    const m = new Map<string, TurmaHorario>();
    for (const trh of list.value) {
        m.set(`${trh.trh_dia}:${trh.trh_grh_id}`, trh);
    }
    return m;
});

const professoresItems = computed(() =>
    props.professoresDisponiveis.map((p) => ({ id: p.fun_id, label: p.fun_nome })),
);

const disciplinasItems = computed(() =>
    props.disciplinas.map((d) => ({ id: d.dis_id, label: d.dis_nome })),
);

// Form state
const showFormKey = ref<string | null>(null); // `${dia}:${grh_id}`
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = reactive({
    trh_grh_id: null as number | null,
    trh_dia: '',
    trh_fun_id: null as number | null,
    trh_dis_id: null as number | null,
    trh_fl_tc: false,
});

const openForm = (dia: string, grhId: number) => {
    const key = `${dia}:${grhId}`;
    form.trh_grh_id = grhId;
    form.trh_dia = dia;
    form.trh_fun_id = null;
    form.trh_dis_id = null;
    form.trh_fl_tc = false;
    errors.value = {};
    showFormKey.value = key;
};

const cancelForm = () => {
    showFormKey.value = null;
    errors.value = {};
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    router.post(`/turmas/${props.turma.tur_id}/horarios`, form as Record<string, any>, {
        preserveScroll: true,
        onSuccess: () => cancelForm(),
        onError: (e) => { errors.value = e; },
        onFinish: () => { processing.value = false; },
    });
};

const remove = (trh: TurmaHorario) => {
    const label = `${trh.funcionario?.fun_nome} / ${trh.disciplina?.dis_nome}`;
    if (!confirm(`Remover "${label}" deste horário?`)) return;
    router.delete(`/turmas/${props.turma.tur_id}/horarios/${trh.trh_id}`, { preserveScroll: true });
};

const fmtHora = (h: string) => h.substring(0, 5);

const gradeVazia = computed(() => props.gradeHorarios.length === 0);
</script>

<template>
    <div class="grid gap-6">
        <!-- Ações -->
        <div v-if="!gradeVazia" class="flex justify-end">
            <Button as-child variant="outline" size="sm" class="h-8 text-xs">
                <a :href="`/turmas/${turma.tur_id}/horarios/grade-pdf`" target="_blank">
                    <Printer class="mr-1 size-3.5" /> Imprimir grade
                </a>
            </Button>
        </div>

        <!-- Aviso sem grade -->
        <div
            v-if="gradeVazia"
            class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <span>
                Nenhum horário cadastrado na grade para o segmento desta turma.
                Configure os horários em <strong>Parâmetros → Grade de Horários</strong>.
            </span>
        </div>

        <!-- Grid de dias -->
        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="dia in diasAtivos"
                :key="dia.value"
                class="rounded-xl border bg-card shadow-sm"
            >
                <!-- Header do dia -->
                <div class="border-b bg-indigo-600 px-4 py-2">
                    <span class="text-sm font-semibold uppercase tracking-wide text-white">{{ dia.label }}</span>
                </div>

                <!-- Tabela de tempos -->
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/30">
                            <th class="px-3 py-2 text-left text-xs font-medium text-muted-foreground">Tempo</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-muted-foreground">Horário</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-muted-foreground">Professor / Disciplina</th>
                            <th class="w-10 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="grh in gradeHorarios" :key="grh.grh_id">
                            <!-- Linha de dado ou form inline -->
                            <tr class="border-b last:border-0">
                                <td class="px-3 py-2 text-muted-foreground">{{ grh.grh_ordem }}º</td>
                                <td class="px-3 py-2 tabular-nums font-medium">{{ fmtHora(grh.grh_hora) }}</td>

                                <template v-if="horarioMap.has(`${dia.value}:${grh.grh_id}`)">
                                    <!-- Alocado -->
                                    <td class="px-3 py-2">
                                        <div class="text-xs font-medium">{{ horarioMap.get(`${dia.value}:${grh.grh_id}`)?.funcionario?.fun_nome ?? '—' }}</div>
                                        <div class="text-xs text-muted-foreground">{{ horarioMap.get(`${dia.value}:${grh.grh_id}`)?.disciplina?.dis_nome ?? '—' }}</div>
                                        <span
                                            v-if="horarioMap.get(`${dia.value}:${grh.grh_id}`)?.trh_fl_tc"
                                            class="rounded bg-sky-100 px-1 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-900/40 dark:text-sky-300"
                                        >TC</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <button
                                            type="button"
                                            class="rounded p-1 hover:bg-muted"
                                            @click="remove(horarioMap.get(`${dia.value}:${grh.grh_id}`)!)"
                                        >
                                            <Trash2 class="size-3.5 text-rose-500" />
                                        </button>
                                    </td>
                                </template>

                                <template v-else-if="showFormKey === `${dia.value}:${grh.grh_id}`">
                                    <!-- Form inline ativo -->
                                    <td colspan="2" class="px-3 py-2"></td>
                                </template>

                                <template v-else>
                                    <!-- Vazio — botão atribuir -->
                                    <td class="px-3 py-2 text-xs text-muted-foreground italic">—</td>
                                    <td class="px-3 py-2">
                                        <button
                                            type="button"
                                            class="rounded p-1 hover:bg-muted"
                                            title="Atribuir professor"
                                            @click="openForm(dia.value, grh.grh_id)"
                                        >
                                            <Plus class="size-3.5 text-indigo-600" />
                                        </button>
                                    </td>
                                </template>
                            </tr>

                            <!-- Form inline expandido abaixo da linha -->
                            <tr v-if="showFormKey === `${dia.value}:${grh.grh_id}`" class="border-b bg-indigo-50/50 dark:bg-indigo-900/10">
                                <td colspan="4" class="px-3 py-3">
                                    <div class="grid gap-3">
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div class="grid min-w-0 gap-1">
                                                <FormLabel :required="true">Professor</FormLabel>
                                                <LocalCombobox
                                                    :model-value="form.trh_fun_id"
                                                    :items="professoresItems"
                                                    :invalid="!!errors.trh_fun_id"
                                                    placeholder="Buscar professor..."
                                                    @update:model-value="(v) => (form.trh_fun_id = v)"
                                                />
                                                <InputError :message="errors.trh_fun_id" />
                                            </div>
                                            <div class="grid min-w-0 gap-1">
                                                <FormLabel :required="true">Disciplina</FormLabel>
                                                <LocalCombobox
                                                    :model-value="form.trh_dis_id"
                                                    :items="disciplinasItems"
                                                    :invalid="!!errors.trh_dis_id"
                                                    placeholder="Buscar disciplina..."
                                                    @update:model-value="(v) => (form.trh_dis_id = v)"
                                                />
                                                <InputError :message="errors.trh_dis_id" />
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <Switch id="trh_fl_tc" v-model="form.trh_fl_tc" />
                                            <Label for="trh_fl_tc" class="text-xs font-normal">Tempo Compartilhado (TC)</Label>
                                        </div>

                                        <div class="flex justify-end gap-2">
                                            <Button type="button" variant="outline" size="sm" class="h-7 text-xs" @click="cancelForm">
                                                <X class="mr-1 size-3" /> Cancelar
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                class="h-7 bg-indigo-600 text-xs text-white hover:bg-indigo-700"
                                                :disabled="processing"
                                                @click="submit"
                                            >
                                                <Loader2 v-if="processing" class="mr-1 size-3 animate-spin" />
                                                Salvar
                                            </Button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
