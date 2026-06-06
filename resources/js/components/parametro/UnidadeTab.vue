<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AnoLetivo, Unidade, UnidadeFormData, UnidadeTipo } from '@/types/parametro';
import { UNIDADE_LIMITES, UNIDADE_ORDINAL, UNIDADE_TIPO_LABELS } from '@/types/parametro';
import { router } from '@inertiajs/vue3';
import { Calendar, LoaderCircle, Pencil, Plus, Save, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    unidades: Unidade[];
    anosLetivos: AnoLetivo[];
}>();

const anoAtual = props.anosLetivos.find((a) => a.anl_fl_em_exercicio);
const selectedAnlId = ref<number | null>(anoAtual?.anl_id ?? props.anosLetivos[0]?.anl_id ?? null);

const unidadesDoAno = computed(() =>
    props.unidades.filter((u) => u.uni_anl_id === selectedAnlId.value).sort((a, b) => a.uni_numero - b.uni_numero),
);

const tipoDoAno = computed<UnidadeTipo | null>(() => unidadesDoAno.value[0]?.uni_tipo ?? null);

const proximoNumero = computed(() => unidadesDoAno.value.length + 1);

const limiteAtingido = computed(() => {
    if (!tipoDoAno.value) return false;
    return unidadesDoAno.value.length >= UNIDADE_LIMITES[tipoDoAno.value];
});

const showForm = ref(false);
const editingUnidade = ref<Unidade | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const emptyForm = (): UnidadeFormData => ({
    uni_anl_id:        selectedAnlId.value,
    uni_tipo:          tipoDoAno.value ?? '',
    uni_dt_inicio:     '',
    uni_dt_fim:        '',
    uni_dias_extensao: '',
});

const form = reactive<UnidadeFormData>(emptyForm());

const openCreate = () => {
    editingUnidade.value = null;
    Object.assign(form, emptyForm());
    errors.value = {};
    showForm.value = true;
};

const openEdit = (u: Unidade) => {
    editingUnidade.value = u;
    form.uni_anl_id        = u.uni_anl_id;
    form.uni_tipo          = u.uni_tipo;
    form.uni_dt_inicio     = u.uni_dt_inicio.slice(0, 10);
    form.uni_dt_fim        = u.uni_dt_fim.slice(0, 10);
    form.uni_dias_extensao = u.uni_dias_extensao ?? '';
    errors.value = {};
    showForm.value = true;
};

const cancel = () => {
    showForm.value = false;
    editingUnidade.value = null;
    errors.value = {};
};

const save = () => {
    processing.value = true;
    errors.value = {};

    const data: Record<string, any> = {
        uni_anl_id:        form.uni_anl_id,
        uni_tipo:          form.uni_tipo,
        uni_dt_inicio:     form.uni_dt_inicio,
        uni_dt_fim:        form.uni_dt_fim,
        uni_dias_extensao: form.uni_dias_extensao !== '' ? form.uni_dias_extensao : null,
    };

    if (editingUnidade.value) {
        data._method = 'put';
        router.post(`/parametros/unidades/${editingUnidade.value.uni_id}`, data, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { showForm.value = false; editingUnidade.value = null; },
            onError:   (errs) => { errors.value = errs; },
            onFinish:  () => { processing.value = false; },
        });
    } else {
        router.post('/parametros/unidades', data, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { showForm.value = false; },
            onError:   (errs) => { errors.value = errs; },
            onFinish:  () => { processing.value = false; },
        });
    }
};

const remove = (u: Unidade) => {
    const ord = UNIDADE_ORDINAL[u.uni_numero] ?? `${u.uni_numero}º`;
    const tipo = UNIDADE_TIPO_LABELS[u.uni_tipo];
    if (!confirm(`Remover o ${ord} período (${tipo})?`)) return;
    router.delete(`/parametros/unidades/${u.uni_id}`, { preserveScroll: true, preserveState: true });
};

const fmtDate = (s?: string | null) => {
    if (!s) return '—';
    const [y, m, d] = s.slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
};

const periodoLabel = (u: Unidade) => {
    const ord = UNIDADE_ORDINAL[u.uni_numero] ?? `${u.uni_numero}º`;
    return `${ord} ${UNIDADE_TIPO_LABELS[u.uni_tipo]}`;
};

const TIPO_OPTIONS = Object.entries(UNIDADE_TIPO_LABELS) as [UnidadeTipo, string][];
</script>

<template>
    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
        <!-- Cabeçalho: seletor de ano -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Calendar class="size-4 text-indigo-600" />
                <h3 class="text-sm font-semibold">Períodos por Ano Letivo</h3>
            </div>
            <div class="flex items-center gap-3">
                <RefreshButton />
                <select
                    v-model="selectedAnlId"
                    class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
                    @change="showForm = false"
                >
                    <option v-for="anl in anosLetivos" :key="anl.anl_id" :value="anl.anl_id">
                        {{ anl.anl_ano }}{{ anl.anl_fl_em_exercicio ? ' (em exercício)' : '' }}
                    </option>
                </select>
                <Button
                    v-if="!showForm && !limiteAtingido && selectedAnlId"
                    type="button"
                    size="sm"
                    class="bg-indigo-600 hover:bg-indigo-700"
                    @click="openCreate"
                >
                    <Plus class="mr-2 size-4" />
                    {{ proximoNumero === 1 ? 'Adicionar 1º Período' : `Adicionar ${UNIDADE_ORDINAL[proximoNumero] ?? proximoNumero + 'º'} Período` }}
                </Button>
                <span v-else-if="limiteAtingido" class="text-xs text-emerald-600 font-medium">
                    Todos os períodos cadastrados
                </span>
            </div>
        </div>

        <!-- Tipo vigente no ano -->
        <div v-if="tipoDoAno" class="flex items-center gap-2 text-xs text-muted-foreground">
            <span class="rounded bg-indigo-100 px-2 py-0.5 font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                {{ UNIDADE_TIPO_LABELS[tipoDoAno] }}
            </span>
            <span>— {{ UNIDADE_LIMITES[tipoDoAno] }} períodos no total</span>
        </div>

        <!-- Tabela de períodos -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Período</th>
                        <th class="px-3 py-2 font-semibold">Início</th>
                        <th class="px-3 py-2 font-semibold">Fim</th>
                        <th class="px-3 py-2 font-semibold">Extensão</th>
                        <th class="px-3 py-2 font-semibold">Fim efetivo</th>
                        <th class="px-3 py-2 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="unidadesDoAno.length === 0">
                        <td colspan="6" class="px-3 py-6 text-center text-muted-foreground">
                            Nenhum período cadastrado para este ano letivo.
                        </td>
                    </tr>
                    <tr
                        v-for="(u, idx) in unidadesDoAno"
                        :key="u.uni_id"
                        :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                    >
                        <td class="px-3 py-2 font-medium">{{ periodoLabel(u) }}</td>
                        <td class="px-3 py-2">{{ fmtDate(u.uni_dt_inicio) }}</td>
                        <td class="px-3 py-2">{{ fmtDate(u.uni_dt_fim) }}</td>
                        <td class="px-3 py-2">
                            <span v-if="u.uni_dias_extensao" class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                +{{ u.uni_dias_extensao }} dia{{ u.uni_dias_extensao !== 1 ? 's' : '' }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-3 py-2">
                            <span :class="u.uni_dias_extensao ? 'font-medium text-amber-700 dark:text-amber-300' : ''">
                                {{ fmtDate(u.uni_dt_fim_efetivo) }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <Button type="button" variant="ghost" size="sm" @click="openEdit(u)" aria-label="Editar">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                    @click="remove(u)"
                                    aria-label="Remover"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Formulário inline -->
        <div v-if="showForm" class="rounded-lg border bg-background p-4">
            <h4 class="mb-4 text-sm font-semibold">
                {{ editingUnidade ? `Editar ${periodoLabel(editingUnidade)}` : `Cadastrar ${UNIDADE_ORDINAL[proximoNumero] ?? proximoNumero + 'º'} Período` }}
            </h4>

            <div class="grid gap-4 sm:grid-cols-4">
                <!-- Tipo (apenas no create e sem períodos anteriores) -->
                <div class="grid gap-1.5">
                    <FormLabel for="uni_tipo" :required="true">Tipo</FormLabel>
                    <select
                        id="uni_tipo"
                        v-model="form.uni_tipo"
                        :disabled="!!editingUnidade || !!tipoDoAno"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.uni_tipo }"
                    >
                        <option value="">Selecione...</option>
                        <option v-for="[val, label] in TIPO_OPTIONS" :key="val" :value="val">{{ label }}</option>
                    </select>
                    <InputError :message="errors.uni_tipo" />
                </div>

                <!-- Data Início -->
                <div class="grid gap-1.5">
                    <FormLabel for="uni_dt_inicio" :required="true">Início</FormLabel>
                    <Input
                        id="uni_dt_inicio"
                        v-model="form.uni_dt_inicio"
                        type="date"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.uni_dt_inicio }"
                    />
                    <InputError :message="errors.uni_dt_inicio" />
                </div>

                <!-- Data Fim -->
                <div class="grid gap-1.5">
                    <FormLabel for="uni_dt_fim" :required="true">Fim</FormLabel>
                    <Input
                        id="uni_dt_fim"
                        v-model="form.uni_dt_fim"
                        type="date"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.uni_dt_fim }"
                    />
                    <InputError :message="errors.uni_dt_fim" />
                </div>

                <!-- Dias de extensão -->
                <div class="grid gap-1.5">
                    <FormLabel for="uni_dias_extensao">Extensão <span class="text-muted-foreground">(dias)</span></FormLabel>
                    <Input
                        id="uni_dias_extensao"
                        v-model.number="form.uni_dias_extensao"
                        type="number"
                        min="0"
                        placeholder="0"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.uni_dias_extensao }"
                    />
                    <InputError :message="errors.uni_dias_extensao" />
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="cancel">
                    <X class="mr-2 size-4" /> Cancelar
                </Button>
                <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" :disabled="processing" @click="save">
                    <LoaderCircle v-if="processing" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    Salvar
                </Button>
            </div>
        </div>
    </div>
</template>
