<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AnoLetivo, DiaNaoLetivo, DiaNaoLetivoFormData } from '@/types/parametro';
import { router } from '@inertiajs/vue3';
import { CalendarOff, LoaderCircle, Pencil, Plus, Save, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    diasNaoLetivos: DiaNaoLetivo[];
    anosLetivos: AnoLetivo[];
}>();

const MESES = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

// Anos ordenados: em exercício primeiro, depois ano desc
const anosOrdenados = computed(() =>
    [...props.anosLetivos].sort((a, b) => {
        if (a.anl_fl_em_exercicio !== b.anl_fl_em_exercicio) return a.anl_fl_em_exercicio ? -1 : 1;
        return b.anl_ano - a.anl_ano;
    }),
);

const anoAtual = props.anosLetivos.find((a) => a.anl_fl_em_exercicio);
const selectedAnlId = ref<number | null>(anoAtual?.anl_id ?? props.anosLetivos[0]?.anl_id ?? null);

// Filtro por mês (null = todos)
const mesFiltro = ref<number | null>(null);

const mesDe = (dia: DiaNaoLetivo) => Number(dia.dnl_dt_dia.slice(5, 7)) - 1; // 0..11

const diasDoAno = computed(() =>
    props.diasNaoLetivos
        .filter((d) => d.dnl_anl_id === selectedAnlId.value)
        .sort((a, b) => a.dnl_dt_dia.localeCompare(b.dnl_dt_dia)),
);

// Meses que possuem registros no ano (para o filtro)
const mesesComRegistro = computed(() => {
    const set = new Set<number>();
    diasDoAno.value.forEach((d) => set.add(mesDe(d)));
    return [...set].sort((a, b) => a - b);
});

const diasFiltrados = computed(() =>
    mesFiltro.value === null ? diasDoAno.value : diasDoAno.value.filter((d) => mesDe(d) === mesFiltro.value),
);

// Agrupamento por mês
const diasPorMes = computed(() => {
    const grupos: { mes: number; dias: DiaNaoLetivo[] }[] = [];
    for (const dia of diasFiltrados.value) {
        const m = mesDe(dia);
        let g = grupos.find((x) => x.mes === m);
        if (!g) {
            g = { mes: m, dias: [] };
            grupos.push(g);
        }
        g.dias.push(dia);
    }
    return grupos.sort((a, b) => a.mes - b.mes);
});

// ===== Formulário inline =====
const showForm = ref(false);
const editing = ref<DiaNaoLetivo | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const emptyForm = (): DiaNaoLetivoFormData => ({
    dnl_anl_id:    selectedAnlId.value,
    dnl_dt_dia:    '',
    dnl_dt_fim:    '',
    dnl_descricao: '',
});

const form = reactive<DiaNaoLetivoFormData>(emptyForm());

const openCreate = () => {
    editing.value = null;
    Object.assign(form, emptyForm());
    errors.value = {};
    showForm.value = true;
};

const openEdit = (d: DiaNaoLetivo) => {
    editing.value = d;
    form.dnl_anl_id    = d.dnl_anl_id;
    form.dnl_dt_dia    = d.dnl_dt_dia.slice(0, 10);
    form.dnl_dt_fim    = d.dnl_dt_fim ? d.dnl_dt_fim.slice(0, 10) : '';
    form.dnl_descricao = d.dnl_descricao;
    errors.value = {};
    showForm.value = true;
};

const cancel = () => {
    showForm.value = false;
    editing.value = null;
    errors.value = {};
};

const save = () => {
    processing.value = true;
    errors.value = {};

    const data: Record<string, any> = {
        dnl_anl_id:    form.dnl_anl_id,
        dnl_dt_dia:    form.dnl_dt_dia,
        dnl_dt_fim:    form.dnl_dt_fim || null,
        dnl_descricao: form.dnl_descricao,
    };

    const opts = {
        preserveScroll: true,
        preserveState: true,
        onError: (errs: Record<string, string>) => { errors.value = errs; },
        onFinish: () => { processing.value = false; },
    };

    if (editing.value) {
        data._method = 'put';
        router.post(`/parametros/dias-nao-letivos/${editing.value.dnl_id}`, data, {
            ...opts,
            onSuccess: () => { showForm.value = false; editing.value = null; },
        });
    } else {
        router.post('/parametros/dias-nao-letivos', data, {
            ...opts,
            onSuccess: () => { showForm.value = false; },
        });
    }
};

const remove = (d: DiaNaoLetivo) => {
    if (!confirm(`Remover o dia não letivo ${fmtDate(d.dnl_dt_dia)} (${d.dnl_descricao})?`)) return;
    router.delete(`/parametros/dias-nao-letivos/${d.dnl_id}`, { preserveScroll: true, preserveState: true });
};

const fmtDate = (s?: string | null) => {
    if (!s) return '—';
    const [y, m, d] = s.slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
};

const onAnoChange = () => {
    showForm.value = false;
    mesFiltro.value = null;
    form.dnl_anl_id = selectedAnlId.value;
};
</script>

<template>
    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
        <!-- Cabeçalho: ano + filtro de mês + adicionar -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <CalendarOff class="size-4 text-indigo-600" />
                <h3 class="text-sm font-semibold">Dias Não Letivos por Ano Letivo</h3>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <RefreshButton />
                <select
                    v-model="selectedAnlId"
                    class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
                    @change="onAnoChange"
                >
                    <option v-for="anl in anosOrdenados" :key="anl.anl_id" :value="anl.anl_id">
                        {{ anl.anl_ano }}{{ anl.anl_fl_em_exercicio ? ' (em exercício)' : '' }}
                    </option>
                </select>
                <select
                    v-model="mesFiltro"
                    class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
                >
                    <option :value="null">Todos os meses</option>
                    <option v-for="m in mesesComRegistro" :key="m" :value="m">{{ MESES[m] }}</option>
                </select>
                <Button
                    v-if="!showForm && selectedAnlId"
                    type="button"
                    size="sm"
                    class="bg-indigo-600 hover:bg-indigo-700"
                    @click="openCreate"
                >
                    <Plus class="mr-2 size-4" /> Adicionar Dia
                </Button>
            </div>
        </div>

        <!-- Formulário inline -->
        <div v-if="showForm" class="rounded-lg border bg-background p-4">
            <h4 class="mb-4 text-sm font-semibold">
                {{ editing ? 'Editar dia não letivo' : 'Cadastrar dia não letivo' }}
            </h4>
            <div class="grid gap-4 sm:grid-cols-12">
                <div class="grid gap-1.5 sm:col-span-3">
                    <FormLabel for="dnl_dt_dia" :required="true">Data{{ form.dnl_dt_fim ? ' início' : '' }}</FormLabel>
                    <Input
                        id="dnl_dt_dia"
                        v-model="form.dnl_dt_dia"
                        type="date"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.dnl_dt_dia }"
                    />
                    <InputError :message="errors.dnl_dt_dia" />
                </div>
                <div class="grid gap-1.5 sm:col-span-3">
                    <FormLabel for="dnl_dt_fim">Data fim <span class="text-xs font-normal text-muted-foreground">(opcional)</span></FormLabel>
                    <Input
                        id="dnl_dt_fim"
                        v-model="form.dnl_dt_fim"
                        type="date"
                        :min="form.dnl_dt_dia || undefined"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.dnl_dt_fim }"
                    />
                    <InputError :message="errors.dnl_dt_fim" />
                </div>
                <div class="grid gap-1.5 sm:col-span-6">
                    <FormLabel for="dnl_descricao" :required="true">Descrição</FormLabel>
                    <Input
                        id="dnl_descricao"
                        v-model="form.dnl_descricao"
                        maxlength="255"
                        placeholder="Ex.: Feriado — Independência do Brasil"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.dnl_descricao }"
                    />
                    <InputError :message="errors.dnl_descricao" />
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

        <!-- Vazio -->
        <div v-if="diasFiltrados.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
            {{ diasDoAno.length === 0 ? 'Nenhum dia não letivo cadastrado para este ano letivo.' : 'Nenhum registro no mês selecionado.' }}
        </div>

        <!-- Agrupado por mês -->
        <div v-for="grupo in diasPorMes" :key="grupo.mes" class="overflow-hidden rounded-lg border">
            <div class="flex items-center justify-between bg-slate-100 px-3 py-2 dark:bg-slate-800">
                <span class="text-sm font-semibold">{{ MESES[grupo.mes] }}</span>
                <span class="text-xs text-muted-foreground">{{ grupo.dias.length }} dia(s)</span>
            </div>
            <table class="w-full text-left text-sm">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Data</th>
                        <th class="px-3 py-2 font-semibold">Descrição</th>
                        <th class="px-3 py-2 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(d, idx) in grupo.dias"
                        :key="d.dnl_id"
                        :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                    >
                        <td class="px-3 py-2 font-medium">
                            {{ fmtDate(d.dnl_dt_dia) }}<template v-if="d.dnl_dt_fim"> a {{ fmtDate(d.dnl_dt_fim) }}</template>
                        </td>
                        <td class="px-3 py-2">{{ d.dnl_descricao }}</td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <Button type="button" variant="ghost" size="sm" @click="openEdit(d)" aria-label="Editar">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                    @click="remove(d)"
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
    </div>
</template>
