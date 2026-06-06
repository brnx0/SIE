<script setup lang="ts">
import CargoCombobox from '@/components/funcionario/CargoCombobox.vue';
import EscolaCombobox from '@/components/funcionario/EscolaCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    ESCOLARIDADES,
    VINCULOS,
    SITUACOES_FUNCIONAIS,
    CRITERIOS_ACESSO,
    FUNCOES_SALA_AULA,
} from '@/lib/funcionarioEnums';
import type {
    Funcionario,
    FuncionarioAdmissao,
    FuncionarioLotacao,
    AdmissaoFormData,
    LotacaoFormData,
    Cargo,
    Escola,
} from '@/types/funcionario';
import { router } from '@inertiajs/vue3';
import { Edit3, Loader2, MapPin, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    funcionario: Funcionario;
}>();

const admissoes = computed<FuncionarioAdmissao[]>(() => props.funcionario.admissoes ?? []);

const formatDateBR = (iso: string | null | undefined): string => {
    if (!iso) return '';
    const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return iso;
    return `${m[3]}/${m[2]}/${m[1]}`;
};

const parseDateBR = (br: string): string => {
    const m = br.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    return m ? `${m[3]}-${m[2]}-${m[1]}` : br;
};

// ─── Admissão ──────────────────────────────────────────────

const showAdmForm = ref(false);
const editingAdmId = ref<number | null>(null);
const admProcessing = ref(false);
const admErrors = ref<Record<string, string>>({});

const admForm = reactive<AdmissaoFormData>({
    adm_matricula: '',
    adm_dt_admissao: '',
    adm_crg_id: null,
    adm_escolaridade_admissao: '',
});
const admDtBR = ref('');
const admCargoInitial = ref<Cargo | null>(null);

watch(admDtBR, (v) => {
    admForm.adm_dt_admissao = v && v.length === 10 ? parseDateBR(v) : '';
});

const resetAdmForm = () => {
    admForm.adm_matricula = '';
    admForm.adm_dt_admissao = '';
    admForm.adm_crg_id = null;
    admForm.adm_escolaridade_admissao = '';
    admDtBR.value = '';
    admCargoInitial.value = null;
    admErrors.value = {};
    editingAdmId.value = null;
    showAdmForm.value = false;
};

const openNewAdm = () => {
    resetAdmForm();
    showAdmForm.value = true;
};

const openEditAdm = (adm: FuncionarioAdmissao) => {
    admForm.adm_matricula = adm.adm_matricula;
    admForm.adm_dt_admissao = adm.adm_dt_admissao;
    admForm.adm_crg_id = adm.adm_crg_id;
    admForm.adm_escolaridade_admissao = adm.adm_escolaridade_admissao ?? '';
    admDtBR.value = formatDateBR(adm.adm_dt_admissao);
    admCargoInitial.value = adm.cargo ?? null;
    admErrors.value = {};
    editingAdmId.value = adm.adm_id;
    showAdmForm.value = true;
};

const submitAdm = () => {
    admProcessing.value = true;
    admErrors.value = {};

    const data: Record<string, any> = { ...admForm };
    if (data.adm_escolaridade_admissao === '') data.adm_escolaridade_admissao = null;

    const url = editingAdmId.value
        ? `/funcionarios/${props.funcionario.fun_id}/admissoes/${editingAdmId.value}`
        : `/funcionarios/${props.funcionario.fun_id}/admissoes`;

    const method = editingAdmId.value ? 'put' : 'post';

    router[method](url, data, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            resetAdmForm();
        },
        onError: (errors) => {
            admErrors.value = errors;
        },
        onFinish: () => {
            admProcessing.value = false;
        },
    });
};

const deleteAdm = (adm: FuncionarioAdmissao) => {
    if (!confirm(`Remover admissão matrícula "${adm.adm_matricula}"? As lotações vinculadas serão removidas.`)) return;

    router.delete(`/funcionarios/${props.funcionario.fun_id}/admissoes/${adm.adm_id}`, {
        preserveScroll: true,
        preserveState: true,
    });
};

// ─── Lotação ───────────────────────────────────────────────

const showLotForm = ref(false);
const editingLotId = ref<number | null>(null);
const lotAdmId = ref<number | null>(null);
const lotProcessing = ref(false);
const lotErrors = ref<Record<string, string>>({});

const lotForm = reactive<LotacaoFormData>({
    lot_adm_id: null,
    lot_esc_id: null,
    lot_crg_id: null,
    lot_vinculo: '',
    lot_situacao_funcional: '',
    lot_criterio_acesso: '',
    lot_dt_inicio: '',
    lot_dt_fim: '',
    lot_fl_ativo: true,
    lot_funcoes_sala_aula: [],
});
const lotDtInicioBR = ref('');
const lotDtFimBR = ref('');
const lotCargoInitial = ref<Cargo | null>(null);
const lotEscolaInitial = ref<Escola | null>(null);

watch(lotDtInicioBR, (v) => {
    lotForm.lot_dt_inicio = v && v.length === 10 ? parseDateBR(v) : '';
});

watch(lotDtFimBR, (v) => {
    lotForm.lot_dt_fim = v && v.length === 10 ? parseDateBR(v) : '';
    if (!v || v.length < 10) {
        lotForm.lot_fl_ativo = true;
    } else {
        lotForm.lot_fl_ativo = false;
    }
});

const CARGOS_SALA_AULA_EXATOS = [
    'Auxiliar de Desenvolvimento Infantil',
    'Auxiliar de Desenvolvimento Infantil – PNE',
    'Cuidador(a) de Educando com Necessidades Especiais',
    'Tradutor(a) Intérprete de LIBRAS',
    'Mediador(a)',
    'Estimulador(a)',
    'Educação Especial – Trabalho Diferenciado',
    'Educadora de Desenvolvimento Infantil em Creche',
    'Monitor(a) de Creche',
    'Auxiliar de Creche',
    'Assistente de Alfabetização',
    'Auxiliar de Ensino',
    'Auxiliar de Classe',
    'Monitor(a) Docente de Atividades',
    'Monitor(a) de Laboratório',
    'Instrutor(a)',
    'Instrutor(a) de Dança',
    'Instrutor(a) de Fanfarra',
    'Instrutor(a) de Música',
    'Instrutor(a) Profissionalizante',
    'Reforço Escolar',
    'Estagiário(a)',
    'Monitor(a)',
];

const isCargoDocente = computed(() => {
    if (!lotCargoInitial.value) return false;
    const nome = lotCargoInitial.value.crg_nome;
    if (['Professor', 'Docente', 'Regente', 'Prof'].some((p) => nome.startsWith(p))) return true;
    return CARGOS_SALA_AULA_EXATOS.includes(nome);
});

const resetLotForm = () => {
    lotForm.lot_adm_id = null;
    lotForm.lot_esc_id = null;
    lotForm.lot_crg_id = null;
    lotForm.lot_vinculo = '';
    lotForm.lot_situacao_funcional = '';
    lotForm.lot_criterio_acesso = '';
    lotForm.lot_dt_inicio = '';
    lotForm.lot_dt_fim = '';
    lotForm.lot_fl_ativo = true;
    lotForm.lot_funcoes_sala_aula = [];
    lotDtInicioBR.value = '';
    lotDtFimBR.value = '';
    lotCargoInitial.value = null;
    lotEscolaInitial.value = null;
    lotErrors.value = {};
    editingLotId.value = null;
    showLotForm.value = false;
    lotAdmId.value = null;
};

const openNewLot = (admId: number) => {
    resetLotForm();
    lotAdmId.value = admId;
    showLotForm.value = true;
};

const openEditLot = (admId: number, lot: FuncionarioLotacao) => {
    lotForm.lot_adm_id = lot.lot_adm_id;
    lotForm.lot_esc_id = lot.lot_esc_id;
    lotForm.lot_crg_id = lot.lot_crg_id;
    lotForm.lot_vinculo = lot.lot_vinculo;
    lotForm.lot_situacao_funcional = lot.lot_situacao_funcional ?? '';
    lotForm.lot_criterio_acesso = lot.lot_criterio_acesso ?? '';
    lotForm.lot_dt_inicio = lot.lot_dt_inicio;
    lotForm.lot_dt_fim = lot.lot_dt_fim ?? '';
    lotForm.lot_fl_ativo = lot.lot_fl_ativo;
    lotForm.lot_funcoes_sala_aula = lot.lot_funcoes_sala_aula ?? [];
    lotDtInicioBR.value = formatDateBR(lot.lot_dt_inicio);
    lotDtFimBR.value = formatDateBR(lot.lot_dt_fim);
    lotCargoInitial.value = lot.cargo ?? null;
    lotEscolaInitial.value = lot.escola ?? null;
    lotErrors.value = {};
    editingLotId.value = lot.lot_id;
    lotAdmId.value = admId;
    showLotForm.value = true;
};

const onLotCargoSelect = (cargo: Cargo | null) => {
    lotForm.lot_crg_id = cargo?.crg_id ?? null;
    lotCargoInitial.value = cargo;
};

const submitLot = () => {
    if (!lotAdmId.value) return;
    lotProcessing.value = true;
    lotErrors.value = {};

    const data: Record<string, any> = { ...lotForm };
    if (!data.lot_situacao_funcional) data.lot_situacao_funcional = null;
    if (!data.lot_criterio_acesso) data.lot_criterio_acesso = null;
    if (!data.lot_dt_fim) data.lot_dt_fim = null;
    if (!data.lot_funcoes_sala_aula?.length) data.lot_funcoes_sala_aula = null;

    const base = `/funcionarios/${props.funcionario.fun_id}/admissoes/${lotAdmId.value}/lotacoes`;
    const url = editingLotId.value ? `${base}/${editingLotId.value}` : base;
    const method = editingLotId.value ? 'put' : 'post';

    router[method](url, data, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            resetLotForm();
        },
        onError: (errors) => {
            lotErrors.value = errors;
        },
        onFinish: () => {
            lotProcessing.value = false;
        },
    });
};

const deleteLot = (admId: number, lot: FuncionarioLotacao) => {
    if (!confirm('Remover esta lotação?')) return;

    router.delete(
        `/funcionarios/${props.funcionario.fun_id}/admissoes/${admId}/lotacoes/${lot.lot_id}`,
        { preserveScroll: true, preserveState: true },
    );
};

const toggleFuncaoSala = (funcao: string) => {
    const idx = lotForm.lot_funcoes_sala_aula.indexOf(funcao);
    if (idx >= 0) {
        lotForm.lot_funcoes_sala_aula.splice(idx, 1);
    } else {
        lotForm.lot_funcoes_sala_aula.push(funcao);
    }
};
</script>

<template>
    <div class="grid gap-6">
        <!-- Header + Nova Admissão -->
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Admissões na Rede</h3>
            <div class="flex items-center gap-2">
                <RefreshButton />
                <Button type="button" size="sm" variant="outline" @click="openNewAdm">
                    <Plus class="mr-1 size-4" /> Nova Admissão
                </Button>
            </div>
        </div>

        <!-- Form Admissão -->
        <div v-if="showAdmForm" class="rounded-xl border border-sky-200 bg-sky-50/50 p-5 shadow-sm dark:border-sky-800 dark:bg-sky-900/20">
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-sm font-medium">{{ editingAdmId ? 'Editar Admissão' : 'Nova Admissão' }}</h4>
                <button type="button" class="rounded p-1 hover:bg-muted" @click="resetAdmForm">
                    <X class="size-4" />
                </button>
            </div>
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="grid gap-2">
                    <FormLabel for="adm_matricula" :required="true">Matrícula</FormLabel>
                    <Input id="adm_matricula" v-model="admForm.adm_matricula" maxlength="30" />
                    <InputError :message="admErrors.adm_matricula" />
                </div>
                <div class="grid gap-2">
                    <FormLabel for="adm_dt_admissao" :required="true">Data de Admissão</FormLabel>
                    <Input
                        id="adm_dt_admissao"
                        v-model="admDtBR"
                        v-maska="'##/##/####'"
                        inputmode="numeric"
                        placeholder="DD/MM/AAAA"
                        maxlength="10"
                    />
                    <InputError :message="admErrors.adm_dt_admissao" />
                </div>
                <div class="grid gap-2">
                    <FormLabel :required="true">Cargo de Admissão</FormLabel>
                    <CargoCombobox
                        :model-value="admForm.adm_crg_id"
                        :initial="admCargoInitial"
                        :invalid="!!admErrors.adm_crg_id"
                        @update:model-value="(v) => (admForm.adm_crg_id = v)"
                    />
                    <InputError :message="admErrors.adm_crg_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="adm_escolaridade">Escolaridade na Admissão</Label>
                    <select
                        id="adm_escolaridade"
                        :value="admForm.adm_escolaridade_admissao === '' ? '' : admForm.adm_escolaridade_admissao"
                        @change="(e: Event) => (admForm.adm_escolaridade_admissao = (e.target as HTMLSelectElement).value === '' ? '' : Number((e.target as HTMLSelectElement).value) as any)"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                    >
                        <option value="">Selecione...</option>
                        <option v-for="opt in ESCOLARIDADES" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <InputError :message="admErrors.adm_escolaridade_admissao" />
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="resetAdmForm">Cancelar</Button>
                <Button
                    type="button"
                    size="sm"
                    :disabled="admProcessing"
                    class="bg-sky-600 text-white hover:bg-sky-700"
                    @click="submitAdm"
                >
                    <Loader2 v-if="admProcessing" class="mr-1 size-4 animate-spin" />
                    {{ editingAdmId ? 'Salvar' : 'Adicionar' }}
                </Button>
            </div>
        </div>

        <!-- Nenhuma admissão -->
        <div v-if="admissoes.length === 0 && !showAdmForm" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
            Nenhuma admissão cadastrada. Clique em "Nova Admissão" para começar.
        </div>

        <!-- Cards de admissão (cada um com suas lotações) -->
        <div v-for="adm in admissoes" :key="adm.adm_id" class="rounded-xl border bg-card shadow-sm">
            <!-- Admissão header -->
            <div class="flex items-center gap-3 border-b bg-muted/30 px-5 py-3">
                <span class="rounded bg-sky-100 px-2.5 py-1 font-mono text-xs font-semibold text-sky-800 dark:bg-sky-900/40 dark:text-sky-300">
                    {{ adm.adm_matricula }}
                </span>
                <div class="flex-1">
                    <span class="text-sm font-medium">{{ adm.cargo?.crg_nome ?? '—' }}</span>
                    <span class="ml-2 text-xs text-muted-foreground">{{ formatDateBR(adm.adm_dt_admissao) }}</span>
                </div>
                <div class="flex gap-1">
                    <button type="button" class="rounded p-1.5 hover:bg-muted" title="Editar admissão" @click="openEditAdm(adm)">
                        <Edit3 class="size-4 text-sky-600" />
                    </button>
                    <button type="button" class="rounded p-1.5 hover:bg-muted" title="Remover admissão" @click="deleteAdm(adm)">
                        <Trash2 class="size-4 text-rose-500" />
                    </button>
                </div>
            </div>

            <!-- Lotações (sempre visíveis) -->
            <div class="p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <MapPin class="mr-1 inline size-3.5" />Lotações
                    </h4>
                    <Button type="button" size="sm" variant="outline" class="h-7 text-xs" @click="openNewLot(adm.adm_id)">
                        <Plus class="mr-1 size-3" /> Nova Lotação
                    </Button>
                </div>

                <!-- Lotação form -->
                <div v-if="showLotForm && lotAdmId === adm.adm_id" class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-800 dark:bg-indigo-900/20">
                    <div class="mb-3 flex items-center justify-between">
                        <h4 class="text-sm font-medium">{{ editingLotId ? 'Editar Lotação' : 'Nova Lotação' }}</h4>
                        <button type="button" class="rounded p-1 hover:bg-muted" @click="resetLotForm">
                            <X class="size-4" />
                        </button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <FormLabel :required="true">Escola</FormLabel>
                            <EscolaCombobox
                                :model-value="lotForm.lot_esc_id"
                                :initial="lotEscolaInitial"
                                :invalid="!!lotErrors.lot_esc_id"
                                @update:model-value="(v) => (lotForm.lot_esc_id = v)"
                            />
                            <InputError :message="lotErrors.lot_esc_id" />
                        </div>
                        <div class="grid gap-2">
                            <FormLabel :required="true">Vínculo</FormLabel>
                            <select
                                v-model="lotForm.lot_vinculo"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="">Selecione...</option>
                                <option v-for="v in VINCULOS" :key="v" :value="v">{{ v }}</option>
                            </select>
                            <InputError :message="lotErrors.lot_vinculo" />
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <FormLabel :required="true">Função Atual</FormLabel>
                            <CargoCombobox
                                :model-value="lotForm.lot_crg_id"
                                :initial="lotCargoInitial"
                                :invalid="!!lotErrors.lot_crg_id"
                                @update:model-value="(v) => (lotForm.lot_crg_id = v)"
                                @select="onLotCargoSelect"
                            />
                            <InputError :message="lotErrors.lot_crg_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Critério de acesso ao cargo/função</Label>
                            <select
                                v-model="lotForm.lot_criterio_acesso"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="">Selecione...</option>
                                <option v-for="c in CRITERIOS_ACESSO" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <InputError :message="lotErrors.lot_criterio_acesso" />
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-4">
                        <div class="grid gap-2">
                            <Label>Situação Funcional</Label>
                            <select
                                v-model="lotForm.lot_situacao_funcional"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
                            >
                                <option value="">Selecione...</option>
                                <option v-for="s in SITUACOES_FUNCIONAIS" :key="s" :value="s">{{ s }}</option>
                            </select>
                            <InputError :message="lotErrors.lot_situacao_funcional" />
                        </div>
                        <div class="grid gap-2">
                            <FormLabel :required="true">Data Inicial</FormLabel>
                            <Input
                                v-model="lotDtInicioBR"
                                v-maska="'##/##/####'"
                                inputmode="numeric"
                                placeholder="DD/MM/AAAA"
                                maxlength="10"
                            />
                            <InputError :message="lotErrors.lot_dt_inicio" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Data Final</Label>
                            <Input
                                v-model="lotDtFimBR"
                                v-maska="'##/##/####'"
                                inputmode="numeric"
                                placeholder="DD/MM/AAAA"
                                maxlength="10"
                            />
                            <InputError :message="lotErrors.lot_dt_fim" />
                        </div>
                        <div class="flex items-end gap-2 pb-1">
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    :checked="lotForm.lot_fl_ativo"
                                    @change="lotForm.lot_fl_ativo = ($event.target as HTMLInputElement).checked"
                                    class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                />
                                Funcionário Ativo no Cargo
                            </label>
                        </div>
                    </div>

                    <div class="mt-4 rounded-lg border p-4">
                        <div class="mb-2 flex items-center gap-2">
                            <h5 class="text-sm font-medium">Função exercida em sala de aula</h5>
                            <span v-if="isCargoDocente" class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Obrigatório</span>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="f in FUNCOES_SALA_AULA"
                                :key="f"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    :checked="lotForm.lot_funcoes_sala_aula.includes(f)"
                                    @change="toggleFuncaoSala(f)"
                                    class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                />
                                {{ f }}
                            </label>
                        </div>
                        <InputError :message="lotErrors.lot_funcoes_sala_aula" class="mt-2" />
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" @click="resetLotForm">Cancelar</Button>
                        <Button
                            type="button"
                            size="sm"
                            :disabled="lotProcessing"
                            class="bg-indigo-600 text-white hover:bg-indigo-700"
                            @click="submitLot"
                        >
                            <Loader2 v-if="lotProcessing" class="mr-1 size-4 animate-spin" />
                            {{ editingLotId ? 'Salvar' : 'Adicionar' }}
                        </Button>
                    </div>
                </div>

                <!-- Lotação list -->
                <div v-if="!adm.lotacoes?.length && !(showLotForm && lotAdmId === adm.adm_id)" class="rounded-lg border-2 border-dashed py-6 text-center text-xs text-muted-foreground">
                    Nenhuma lotação cadastrada para esta admissão.
                </div>

                <div v-else class="grid gap-2">
                    <div v-for="lot in adm.lotacoes" :key="lot.lot_id" class="flex items-center gap-3 rounded-lg border px-4 py-3 text-sm">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ lot.escola?.esc_nome ?? '—' }}</span>
                                <span class="text-muted-foreground">·</span>
                                <span>{{ lot.cargo?.crg_nome ?? '—' }}</span>
                                <span class="text-muted-foreground">·</span>
                                <span class="text-xs text-muted-foreground">{{ lot.lot_vinculo }}</span>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span>{{ formatDateBR(lot.lot_dt_inicio) }} — {{ lot.lot_dt_fim ? formatDateBR(lot.lot_dt_fim) : 'atual' }}</span>
                                <span
                                    :class="lot.lot_fl_ativo
                                        ? 'rounded bg-emerald-100 px-1.5 py-0.5 font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                        : 'rounded bg-rose-100 px-1.5 py-0.5 font-medium text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'"
                                >
                                    {{ lot.lot_fl_ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                                <span v-if="lot.lot_situacao_funcional" class="rounded bg-muted px-1.5 py-0.5">{{ lot.lot_situacao_funcional }}</span>
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <button type="button" class="rounded p-1.5 hover:bg-muted" title="Editar lotação" @click="openEditLot(adm.adm_id, lot)">
                                <Edit3 class="size-4 text-indigo-600" />
                            </button>
                            <button type="button" class="rounded p-1.5 hover:bg-muted" title="Remover lotação" @click="deleteLot(adm.adm_id, lot)">
                                <Trash2 class="size-4 text-rose-500" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
