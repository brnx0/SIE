<script setup lang="ts">
import DisciplinaLookup from '@/components/disciplina/DisciplinaLookup.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AnoLetivo, DisciplinaResumo, GradeDisciplinar, SegmentoResumo } from '@/types/parametro';
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, CheckCircle2, Copy, LoaderCircle, Pencil, Plus, RefreshCw, Save, Trash2, X, XCircle } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    segmentos: SegmentoResumo[];
}>();

interface SerieResumo {
    ser_id: number;
    ser_nome: string;
}

const fAnlId = ref<number | ''>('');
const fSegId = ref<number | ''>('');
const fSerId = ref<number | ''>('');

const series        = ref<SerieResumo[]>([]);
const grade         = ref<GradeDisciplinar[]>([]);
const loadingGrade  = ref(false);
const loadingSeries = ref(false);

// Default ano letivo = maior anl_ano
onMounted(() => {
    if (props.anosLetivos.length > 0) {
        fAnlId.value = props.anosLetivos[0].anl_id;
    }
});

// Carrega séries do segmento ao selecionar segmento
const carregarSeries = async () => {
    if (!fSegId.value) {
        series.value = [];
        fSerId.value = '';
        return;
    }
    loadingSeries.value = true;
    try {
        const res = await fetch(`/api/series?seg_id=${fSegId.value}`);
        series.value = await res.json();
    } finally {
        loadingSeries.value = false;
    }
};

watch(fSegId, () => {
    fSerId.value = '';
    carregarSeries();
});

// Carrega grade ao ter todos os filtros
const carregarGrade = async () => {
    if (!fAnlId.value || !fSegId.value || !fSerId.value) {
        grade.value = [];
        return;
    }
    loadingGrade.value = true;
    try {
        const params = new URLSearchParams({
            anl_id: String(fAnlId.value),
            seg_id: String(fSegId.value),
            ser_id: String(fSerId.value),
        });
        const res = await fetch(`/api/grade-disciplinar?${params}`);
        grade.value = await res.json();
    } finally {
        loadingGrade.value = false;
    }
};

watch([fAnlId, fSegId, fSerId], carregarGrade);

// Disciplinas disponíveis (todas ativas — vínculo com segmento é feito nesta tela)
const disciplinas        = ref<DisciplinaResumo[]>([]);
const loadingDisciplinas = ref(false);

const buscarDisciplinas = async () => {
    loadingDisciplinas.value = true;
    try {
        const res = await fetch('/api/disciplinas/search');
        disciplinas.value = await res.json();
    } finally {
        loadingDisciplinas.value = false;
    }
};

// Pré-carrega ao montar
onMounted(() => buscarDisciplinas());

// IDs já vinculados → bloqueio no lookup (exceto a própria sendo editada)
const disabledIds = computed(() =>
    grade.value
        .filter((g) => g.grd_dis_id !== editing.value?.grd_dis_id)
        .map((g) => g.grd_dis_id),
);

// Formulário inline
const showForm = ref(false);
const editing  = ref<GradeDisciplinar | null>(null);
const formData = reactive({
    grd_dis_id:           null as number | null,
    grd_nome_alternativo: '',
    grd_fl_ativo:         true,
});
const errors     = ref<Record<string, string>>({});
const processing = ref(false);

const abrirCriar = () => {
    editing.value = null;
    formData.grd_dis_id = null;
    formData.grd_nome_alternativo = '';
    formData.grd_fl_ativo = true;
    errors.value = {};
    if (disciplinas.value.length === 0) buscarDisciplinas();
    showForm.value = true;
};

const abrirEditar = (g: GradeDisciplinar) => {
    editing.value = g;
    formData.grd_dis_id = g.grd_dis_id;
    formData.grd_nome_alternativo = g.grd_nome_alternativo ?? '';
    formData.grd_fl_ativo = g.grd_fl_ativo;
    errors.value = {};
    // Garante que a disciplina atual aparece na lista (caso fora do filtro de segmento)
    if (g.disciplina && !disciplinas.value.some((d) => d.dis_id === g.disciplina!.dis_id)) {
        disciplinas.value = [g.disciplina, ...disciplinas.value];
    }
    if (disciplinas.value.length === 0) buscarDisciplinas();
    showForm.value = true;
};

const cancelar = () => {
    showForm.value = false;
    editing.value = null;
    errors.value = {};
};

const salvar = () => {
    if (!fAnlId.value || !fSegId.value || !fSerId.value) return;
    processing.value = true;
    errors.value = {};

    const payload: Record<string, any> = {
        grd_anl_id:           fAnlId.value,
        grd_seg_id:           fSegId.value,
        grd_ser_id:           fSerId.value,
        grd_dis_id:           formData.grd_dis_id,
        grd_nome_alternativo: formData.grd_nome_alternativo || null,
        grd_fl_ativo:         formData.grd_fl_ativo,
    };

    const onOk = () => {
        showForm.value = false;
        editing.value = null;
        carregarGrade();
    };
    const onErr = (errs: Record<string, string>) => { errors.value = errs; };
    const onEnd = () => { processing.value = false; };

    if (editing.value) {
        payload._method = 'put';
        router.post(`/parametros/grade-disciplinar/${editing.value.grd_id}`, payload, {
            preserveScroll: true, onSuccess: onOk, onError: onErr, onFinish: onEnd,
        });
    } else {
        router.post('/parametros/grade-disciplinar', payload, {
            preserveScroll: true, onSuccess: onOk, onError: onErr, onFinish: onEnd,
        });
    }
};

const remover = (g: GradeDisciplinar) => {
    if (!confirm(`Remover "${g.disciplina?.dis_nome ?? 'disciplina'}" da grade?`)) return;
    router.delete(`/parametros/grade-disciplinar/${g.grd_id}`, {
        preserveScroll: true,
        onSuccess: () => carregarGrade(),
    });
};

const reordenar = (g: GradeDisciplinar, direcao: 'up' | 'down') => {
    router.patch(`/parametros/grade-disciplinar/${g.grd_id}/ordem`, { direcao }, {
        preserveScroll: true,
        onSuccess: () => carregarGrade(),
    });
};

// Clone do ano anterior — só aparece quando combinação está vazia + existe ano anterior
const anoAnterior = computed<AnoLetivo | null>(() => {
    if (!fAnlId.value) return null;
    const atual = props.anosLetivos.find((a) => a.anl_id === fAnlId.value);
    if (!atual) return null;
    const candidatos = props.anosLetivos.filter((a) => a.anl_ano < atual.anl_ano);
    return candidatos.length ? candidatos.sort((a, b) => b.anl_ano - a.anl_ano)[0] : null;
});

const podeClonar = computed(() =>
    grade.value.length === 0 && anoAnterior.value !== null && fSegId.value && fSerId.value,
);

const clonarAnoAnterior = () => {
    if (!anoAnterior.value || !fSegId.value || !fSerId.value) return;
    if (!confirm(`Clonar todas as disciplinas do ano ${anoAnterior.value.anl_ano}?`)) return;
    router.post('/parametros/grade-disciplinar/clonar', {
        origem_anl_id:  anoAnterior.value.anl_id,
        destino_anl_id: fAnlId.value,
        seg_id:         fSegId.value,
        ser_id:         fSerId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => carregarGrade(),
    });
};

const selectClass = (hasError: boolean) =>
    `flex h-9 w-full rounded-md border bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring ${hasError ? 'border-red-500 ring-1 ring-red-500' : 'border-input'}`;
</script>

<template>
    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1.5">
                <FormLabel :required="true">Ano Letivo</FormLabel>
                <select v-model="fAnlId" :class="selectClass(false)" class="min-w-[140px]">
                    <option :value="''">Selecione...</option>
                    <option v-for="anl in anosLetivos" :key="anl.anl_id" :value="anl.anl_id">{{ anl.anl_ano }}</option>
                </select>
            </div>

            <div class="grid gap-1.5">
                <FormLabel :required="true">Segmento</FormLabel>
                <select v-model="fSegId" :class="selectClass(false)" class="min-w-[280px]">
                    <option :value="''">Selecione...</option>
                    <option v-for="seg in segmentos" :key="seg.seg_id" :value="seg.seg_id">{{ seg.seg_nome_completo }}</option>
                </select>
            </div>

            <div class="grid gap-1.5">
                <FormLabel :required="true">Série</FormLabel>
                <select v-model="fSerId" :disabled="!fSegId" :class="selectClass(false)" class="min-w-[180px]">
                    <option :value="''">{{ !fSegId ? 'Selecione segmento primeiro' : 'Selecione...' }}</option>
                    <option v-for="s in series" :key="s.ser_id" :value="s.ser_id">{{ s.ser_nome }}</option>
                </select>
            </div>

            <div class="ml-auto flex gap-2">
                <Button
                    v-if="fAnlId && fSegId && fSerId"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="gap-1.5"
                    :disabled="loadingGrade"
                    title="Atualizar grade"
                    @click="carregarGrade"
                >
                    <RefreshCw :class="['size-4', loadingGrade && 'animate-spin']" />
                    Atualizar
                </Button>
                <Button
                    v-if="podeClonar"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="clonarAnoAnterior"
                >
                    <Copy class="mr-2 size-4" />
                    Clonar do ano {{ anoAnterior?.anl_ano }}
                </Button>
                <Button
                    v-if="fAnlId && fSegId && fSerId && !showForm"
                    type="button"
                    size="sm"
                    class="bg-indigo-600 hover:bg-indigo-700"
                    @click="abrirCriar"
                >
                    <Plus class="mr-2 size-4" /> Adicionar Disciplina
                </Button>
            </div>
        </div>

        <!-- Formulário inline -->
        <div v-if="showForm" class="rounded-lg border bg-background p-4">
            <h4 class="mb-4 text-sm font-semibold">
                {{ editing ? 'Editar Disciplina' : 'Nova Disciplina na Grade' }}
            </h4>

            <div class="grid gap-4 sm:grid-cols-12">
                <div class="grid gap-1.5 sm:col-span-5">
                    <FormLabel :required="true">Disciplina</FormLabel>
                    <DisciplinaLookup
                        v-model="formData.grd_dis_id"
                        :items="disciplinas"
                        :initial="editing?.disciplina ?? null"
                        :loading="loadingDisciplinas"
                        :disabled-ids="disabledIds"
                        :invalid="!!errors.grd_dis_id"
                    />
                    <p v-if="!loadingDisciplinas && disciplinas.length === 0" class="text-xs text-muted-foreground">
                        Nenhuma disciplina compatível com este segmento.
                    </p>
                    <InputError :message="errors.grd_dis_id" />
                </div>

                <div class="grid gap-1.5 sm:col-span-5">
                    <FormLabel>Nome alternativo <span class="text-muted-foreground">(opcional)</span></FormLabel>
                    <Input
                        v-model="formData.grd_nome_alternativo"
                        maxlength="100"
                        placeholder="Ex.: Matemática Aplicada"
                    />
                    <InputError :message="errors.grd_nome_alternativo" />
                </div>

                <div class="flex items-end gap-2 sm:col-span-2">
                    <Switch id="grd_fl_ativo" v-model="formData.grd_fl_ativo" />
                    <label for="grd_fl_ativo" class="text-sm">Ativa</label>
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="cancelar">
                    <X class="mr-2 size-4" /> Cancelar
                </Button>
                <Button
                    type="button"
                    size="sm"
                    class="bg-indigo-600 hover:bg-indigo-700"
                    :disabled="processing || !formData.grd_dis_id"
                    @click="salvar"
                >
                    <LoaderCircle v-if="processing" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    Salvar
                </Button>
            </div>
        </div>

        <!-- Tabela -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-3 py-2 text-center font-semibold w-20">Ordem</th>
                        <th class="px-3 py-2 font-semibold">Disciplina</th>
                        <th class="px-3 py-2 font-semibold">Nome alternativo</th>
                        <th class="px-3 py-2 text-center font-semibold w-24">Ativa</th>
                        <th class="px-3 py-2 text-right font-semibold w-44">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!fAnlId || !fSegId || !fSerId">
                        <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">
                            Selecione ano letivo, segmento e série para visualizar a grade.
                        </td>
                    </tr>
                    <tr v-else-if="loadingGrade">
                        <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">Carregando...</td>
                    </tr>
                    <tr v-else-if="grade.length === 0">
                        <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">
                            Nenhuma disciplina cadastrada para esta combinação.
                        </td>
                    </tr>
                    <tr
                        v-for="(g, idx) in grade"
                        :key="g.grd_id"
                        :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                    >
                        <td class="px-3 py-2 text-center font-semibold tabular-nums">{{ g.grd_ordem }}</td>
                        <td class="px-3 py-2">
                            {{ g.disciplina?.dis_nome ?? '—' }}
                            <span v-if="g.disciplina?.dis_sigla" class="text-xs text-muted-foreground">({{ g.disciplina.dis_sigla }})</span>
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">{{ g.grd_nome_alternativo ?? '—' }}</td>
                        <td class="px-3 py-2 text-center">
                            <CheckCircle2 v-if="g.grd_fl_ativo" class="mx-auto size-4 text-emerald-600" />
                            <XCircle v-else class="mx-auto size-4 text-rose-400" />
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <Button
                                    type="button" variant="ghost" size="sm"
                                    :disabled="idx === 0"
                                    @click="reordenar(g, 'up')"
                                    aria-label="Subir"
                                >
                                    <ArrowUp class="size-4" />
                                </Button>
                                <Button
                                    type="button" variant="ghost" size="sm"
                                    :disabled="idx === grade.length - 1"
                                    @click="reordenar(g, 'down')"
                                    aria-label="Descer"
                                >
                                    <ArrowDown class="size-4" />
                                </Button>
                                <Button type="button" variant="ghost" size="sm" @click="abrirEditar(g)" aria-label="Editar">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    type="button" variant="ghost" size="sm"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                    @click="remover(g)" aria-label="Remover"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
