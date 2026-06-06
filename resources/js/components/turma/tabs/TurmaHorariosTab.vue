<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
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
import { Loader2, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    turma: Turma;
    horarios: TurmaHorario[];
    gradeHorarios: GradeHorarioResumo[];
    professoresDisponiveis: ProfessorResumo[];
    disciplinas: DisciplinaResumo[];
}>();

const horasOpts = computed(() =>
    (props.gradeHorarios ?? []).map(g => g.grh_hora.substring(0, 5)),
);

const TEMPOS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10] as const;

const list = computed<TurmaHorario[]>(() => props.horarios ?? []);

const diasAtivos = computed(() =>
    DIAS_SEMANA.filter((d) => props.turma.tur_dias_funcionamento?.includes(d.value as any)),
);

// Lookup: `${dia}:${tempo}` → TurmaHorario
const horarioMap = computed(() => {
    const m = new Map<string, TurmaHorario>();
    for (const trh of list.value) {
        m.set(`${trh.trh_dia}:${trh.trh_tempo}`, trh);
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
const showFormKey = ref<string | null>(null); // `${dia}:${tempo}`
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = reactive({
    trh_tempo:  null as number | null,
    trh_hora:   '' as string,
    trh_dia:    '',
    trh_fun_id: null as number | null,
    trh_dis_id: null as number | null,
    trh_fl_tc:  false,
});

const openForm = (dia: string, tempo: number) => {
    const key = `${dia}:${tempo}`;
    form.trh_tempo  = tempo;
    form.trh_hora   = '';
    form.trh_dia    = dia;
    form.trh_fun_id = null;
    form.trh_dis_id = null;
    form.trh_fl_tc  = false;
    errors.value    = {};
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
        preserveState: true,
        onSuccess: () => cancelForm(),
        onError: (e) => { errors.value = e; },
        onFinish: () => { processing.value = false; },
    });
};

const remove = (trh: TurmaHorario) => {
    const label = `${trh.funcionario?.fun_nome} / ${trh.disciplina?.dis_nome}`;
    if (!confirm(`Remover "${label}" do ${trh.trh_tempo}º tempo?`)) return;
    router.delete(`/turmas/${props.turma.tur_id}/horarios/${trh.trh_id}`, { preserveScroll: true, preserveState: true });
};
</script>

<template>
  <div class="grid gap-4">
    <div class="flex justify-end">
        <RefreshButton />
    </div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
                        <th class="px-3 py-2 text-center text-xs font-medium text-muted-foreground w-10">Tempo</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-muted-foreground">Professor / Disciplina</th>
                        <th class="w-8 px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="tempo in TEMPOS" :key="tempo">
                        <tr class="border-b last:border-0">
                            <td class="px-3 py-2 text-center font-semibold text-muted-foreground">{{ tempo }}º</td>

                            <template v-if="horarioMap.has(`${dia.value}:${tempo}`)">
                                <!-- Alocado -->
                                <td class="px-3 py-2">
                                    <div class="text-xs font-medium">{{ horarioMap.get(`${dia.value}:${tempo}`)?.funcionario?.fun_nome ?? '—' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ horarioMap.get(`${dia.value}:${tempo}`)?.disciplina?.dis_nome ?? '—' }}</div>
                                    <div v-if="horarioMap.get(`${dia.value}:${tempo}`)?.trh_hora" class="text-xs text-indigo-600 tabular-nums">
                                        {{ horarioMap.get(`${dia.value}:${tempo}`)?.trh_hora?.substring(0, 5) }}
                                    </div>
                                    <span
                                        v-if="horarioMap.get(`${dia.value}:${tempo}`)?.trh_fl_tc"
                                        class="rounded bg-sky-100 px-1 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-900/40 dark:text-sky-300"
                                    >TC</span>
                                </td>
                                <td class="px-2 py-2">
                                    <button
                                        type="button"
                                        class="rounded p-1 hover:bg-muted"
                                        @click="remove(horarioMap.get(`${dia.value}:${tempo}`)!)"
                                    >
                                        <Trash2 class="size-3.5 text-rose-500" />
                                    </button>
                                </td>
                            </template>

                            <template v-else-if="showFormKey === `${dia.value}:${tempo}`">
                                <td colspan="2" class="px-3 py-2 text-xs text-muted-foreground italic">preenchendo...</td>
                            </template>

                            <template v-else>
                                <td class="px-3 py-2 text-xs text-muted-foreground italic">—</td>
                                <td class="px-2 py-2">
                                    <button
                                        type="button"
                                        class="rounded p-1 hover:bg-muted"
                                        title="Atribuir professor"
                                        @click="openForm(dia.value, tempo)"
                                    >
                                        <Plus class="size-3.5 text-indigo-600" />
                                    </button>
                                </td>
                            </template>
                        </tr>

                        <!-- Form inline expandido -->
                        <tr v-if="showFormKey === `${dia.value}:${tempo}`" class="border-b bg-indigo-50/50 dark:bg-indigo-900/10">
                            <td colspan="3" class="px-3 py-3">
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

                                    <InputError :message="errors.trh_tempo" />

                                    <div class="grid gap-1">
                                        <FormLabel>Horário (opcional)</FormLabel>
                                        <select
                                            v-model="form.trh_hora"
                                            :disabled="horasOpts.length === 0"
                                            class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 w-36"
                                        >
                                            <option value="">{{ horasOpts.length === 0 ? 'Sem grade cadastrada' : '—' }}</option>
                                            <option v-for="h in horasOpts" :key="h" :value="h">{{ h }}</option>
                                        </select>
                                        <InputError :message="errors.trh_hora" />
                                        <p v-if="horasOpts.length === 0" class="text-xs text-amber-600">
                                            Cadastre a grade de horários do segmento em Parâmetros.
                                        </p>
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
