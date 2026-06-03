<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import MunicipioCombobox from '@/components/aluno/MunicipioCombobox.vue';
import HomonimoDialog, { type HomonimoMatch } from '@/components/aluno/HomonimoDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Municipio } from '@/types/aluno';
import type { AlunoResumo, TurmaMatricula } from '@/types/matricula';
import { COR_RACA } from '@/lib/corRaca';
import { DEFICIENCIAS, TRANSTORNOS_GLOBAIS, TRANSTORNOS_APRENDIZAGEM, PATOLOGIAS, PATOLOGIAS_INFANCIA, CLINICAS, RECURSOS_INEP } from '@/lib/alunoSaudeEnums';
import { TIPOS_SANGUINEOS } from '@/lib/tiposSanguineos';
import Switch from '@/components/common/Switch.vue';
import { Label } from '@/components/ui/label';
import { useToast } from '@/composables/useToast';
import { computed, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { LoaderCircle, Save, X } from 'lucide-vue-next';

const page = usePage();

const { push: pushToast } = useToast();

const props = defineProps<{
    open: boolean;
    turma: TurmaMatricula | null;
    aluno: AlunoResumo | null;
    alunoNaoCadastrado: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'saved'): void;
}>();

// ── Abas ─────────────────────────────────────────────────────────────────────
const activeTab = ref<'dados' | 'saude'>('dados');

// ── Form ──────────────────────────────────────────────────────────────────────
const errors     = ref<Record<string, string>>({});
const processing = ref(false);

// ── Duplicata aluno ───────────────────────────────────────────────────────────
const duplicataOpen    = ref(false);
const duplicataMatches = ref<HomonimoMatch[]>([]);
let   confirmDuplicata = false;

const possuiDeficiencia = ref(false);

const formMatricula = reactive({
    tma_dt_matricula:             new Date().toISOString().slice(0, 10),
    tma_obs:                      '',
});

const formAluno = reactive({
    aln_nome:          '',
    aln_cpf:           '',
    aln_nis:           '',
    aln_filiacao_1:      '',
    aln_filiacao_1_tipo: '' as 'PAI' | 'MAE' | '',
    aln_filiacao_2:      '',
    aln_filiacao_2_tipo: '' as 'PAI' | 'MAE' | '',
    aln_sexo:          '' as 'M' | 'F' | '',
    aln_dt_nascimento: '',
    aln_cor_raca:      null as number | null,
    aln_pais_origem:   'Brasil',
    aln_mun_id_nasc:   null as number | null,
    aln_nr_certidao:   '',
    aln_cep:           '',
    aln_logradouro:    '',
    aln_numero:        '',
    aln_complemento:  '',
    aln_bairro:        '',
    aln_cidade:        '',
    aln_uf:            '',
    aln_telefone:      '',
    aln_email:         '',
    aln_fl_trouxe_transferencia:  false,
    aln_fl_trouxe_rg:             false,
    aln_fl_trouxe_reg_nascimento: false,
    aln_fl_bolsa_familia:         false,
    aln_fl_recebe_merenda:        false,
    aln_fl_usa_transporte:        false,
    aln_fl_usa_biblioteca:        false,
});

const formSaude = reactive({
    als_tipo_sanguineo:           '',
    als_ds_alergias:              '',
    als_contato_emergencia:       '',
    als_telefone_emergencia:      '',
    als_plano_saude:              '',
    als_cartao_sus:               '',
    als_alergia_a:                '',
    als_remedio_febre:            '',
    als_remedio_cefaleia:         '',
    als_patologias:               [] as string[],
    als_outra_doenca:             '',
    als_patologias_infancia:      [] as string[],
    als_outra_doenca_infancia:    '',
    als_deficiencias:             [] as string[],
    als_transtornos_globais:      [] as string[],
    als_transtornos_aprendizagem: [] as string[],
    als_deficiencia_outro:        '',
    als_fl_altas_habilidades:     false,
    als_cid:                      '',
    als_observacao:               '',
    als_clinicas:                 [] as string[],
    als_recursos_inep:            [] as string[],
});

// ── Computed ──────────────────────────────────────────────────────────────────
const params             = computed(() => (page.props as any).params ?? {});
const cpfObrigatorio     = computed(() => !!params.value.cpf_obrigatorio);
const alertarAcentos     = computed(() => !!params.value.alertar_acentos_nomes);
const tabSaudeDisponivel = computed(() => possuiDeficiencia.value);

const stripAccents = (str: string) => str.normalize('NFD').replace(/[̀-ͯ]/g, '');
const municipioNascimentoSelecionado = ref<Municipio | null>(null);

const municipioNascimentoInicial = computed(() =>
    formAluno.aln_mun_id_nasc ? municipioNascimentoSelecionado.value : null
);

const limparFormAluno = () => {
    formAluno.aln_nome = '';
    formAluno.aln_cpf = '';
    formAluno.aln_nis = '';
    formAluno.aln_filiacao_1      = '';
    formAluno.aln_filiacao_1_tipo = '';
    formAluno.aln_filiacao_2      = '';
    formAluno.aln_filiacao_2_tipo = '';
    formAluno.aln_sexo = '';
    formAluno.aln_dt_nascimento = '';
    formAluno.aln_cor_raca = null;
    formAluno.aln_pais_origem = 'Brasil';
    formAluno.aln_mun_id_nasc = null;
    municipioNascimentoSelecionado.value = null;
    formAluno.aln_nr_certidao = '';
    formAluno.aln_cep = '';
    formAluno.aln_logradouro = '';
    formAluno.aln_numero = '';
    formAluno.aln_complemento = '';
    formAluno.aln_bairro = '';
    formAluno.aln_cidade = '';
    formAluno.aln_uf = '';
    formAluno.aln_telefone = '';
    formAluno.aln_email = '';
    formAluno.aln_fl_trouxe_transferencia  = false;
    formAluno.aln_fl_trouxe_rg             = false;
    formAluno.aln_fl_trouxe_reg_nascimento = false;
    formAluno.aln_fl_bolsa_familia         = false;
    formAluno.aln_fl_recebe_merenda        = false;
    formAluno.aln_fl_usa_transporte        = false;
    formAluno.aln_fl_usa_biblioteca        = false;
};

const preencherFormAluno = (aluno: AlunoResumo) => {
    formAluno.aln_nome = aluno.aln_nome ?? '';
    formAluno.aln_cpf = aluno.aln_cpf ?? '';
    formAluno.aln_nis = aluno.aln_nis ?? '';
    formAluno.aln_filiacao_1 = aluno.aln_filiacao_1 ?? '';
    formAluno.aln_filiacao_1_tipo = aluno.aln_filiacao_1_tipo ?? '';
    formAluno.aln_filiacao_2 = aluno.aln_filiacao_2 ?? '';
    formAluno.aln_filiacao_2_tipo = aluno.aln_filiacao_2_tipo ?? '';
    formAluno.aln_sexo = aluno.aln_sexo ?? '';
    formAluno.aln_dt_nascimento = aluno.aln_dt_nascimento ?? '';
    formAluno.aln_cor_raca = aluno.aln_cor_raca;
    formAluno.aln_pais_origem = aluno.aln_pais_origem ?? 'Brasil';
    formAluno.aln_mun_id_nasc = aluno.aln_mun_id_nasc;
    municipioNascimentoSelecionado.value = aluno.municipio_nascimento ?? null;
    formAluno.aln_nr_certidao = aluno.aln_nr_certidao ?? '';
    formAluno.aln_cep = aluno.aln_cep ?? '';
    formAluno.aln_logradouro = aluno.aln_logradouro ?? '';
    formAluno.aln_numero = aluno.aln_numero ?? '';
    formAluno.aln_complemento = aluno.aln_complemento ?? '';
    formAluno.aln_bairro = aluno.aln_bairro ?? '';
    formAluno.aln_cidade = aluno.aln_cidade ?? '';
    formAluno.aln_uf = aluno.aln_uf ?? '';
    formAluno.aln_telefone = aluno.aln_telefone ?? '';
    formAluno.aln_email = aluno.aln_email ?? '';
    formAluno.aln_fl_trouxe_transferencia  = (aluno as any).aln_fl_trouxe_transferencia  ?? false;
    formAluno.aln_fl_trouxe_rg             = (aluno as any).aln_fl_trouxe_rg             ?? false;
    formAluno.aln_fl_trouxe_reg_nascimento = (aluno as any).aln_fl_trouxe_reg_nascimento ?? false;
    formAluno.aln_fl_bolsa_familia         = (aluno as any).aln_fl_bolsa_familia         ?? false;
    formAluno.aln_fl_recebe_merenda        = (aluno as any).aln_fl_recebe_merenda        ?? false;
    formAluno.aln_fl_usa_transporte        = (aluno as any).aln_fl_usa_transporte        ?? false;
    formAluno.aln_fl_usa_biblioteca        = (aluno as any).aln_fl_usa_biblioteca        ?? false;

    // Quadro de saúde
    const s = aluno.saude ?? null;
    possuiDeficiencia.value = s?.als_fl_pcd ?? false;
    formSaude.als_tipo_sanguineo           = s?.als_tipo_sanguineo ?? '';
    formSaude.als_ds_alergias              = s?.als_ds_alergias ?? '';
    formSaude.als_contato_emergencia       = s?.als_contato_emergencia ?? '';
    formSaude.als_telefone_emergencia      = s?.als_telefone_emergencia ?? '';
    formSaude.als_plano_saude              = s?.als_plano_saude ?? '';
    formSaude.als_cartao_sus               = s?.als_cartao_sus ?? '';
    formSaude.als_alergia_a                = s?.als_alergia_a ?? '';
    formSaude.als_remedio_febre            = s?.als_remedio_febre ?? '';
    formSaude.als_remedio_cefaleia         = s?.als_remedio_cefaleia ?? '';
    formSaude.als_patologias               = s?.als_patologias ?? [];
    formSaude.als_outra_doenca             = s?.als_outra_doenca ?? '';
    formSaude.als_patologias_infancia      = s?.als_patologias_infancia ?? [];
    formSaude.als_outra_doenca_infancia    = s?.als_outra_doenca_infancia ?? '';
    formSaude.als_deficiencias             = s?.als_deficiencias ?? [];
    formSaude.als_transtornos_globais      = s?.als_transtornos_globais ?? [];
    formSaude.als_transtornos_aprendizagem = s?.als_transtornos_aprendizagem ?? [];
    formSaude.als_deficiencia_outro        = s?.als_deficiencia_outro ?? '';
    formSaude.als_fl_altas_habilidades     = s?.als_fl_altas_habilidades ?? false;
    formSaude.als_cid                      = s?.als_cid ?? '';
    formSaude.als_observacao               = s?.als_observacao ?? '';
    formSaude.als_clinicas                 = s?.als_clinicas ?? [];
    formSaude.als_recursos_inep            = s?.als_recursos_inep ?? [];
};

// ── Reset ao abrir ────────────────────────────────────────────────────────────
const reset = () => {
    activeTab.value = 'dados';
    errors.value    = {};
    possuiDeficiencia.value = false;

    // Matricula defaults
    formMatricula.tma_dt_matricula = new Date().toISOString().slice(0, 10);
    formMatricula.tma_obs          = '';

    // Saúde — limpa antes de popular para não sobrescrever dados do aluno
    formSaude.als_tipo_sanguineo           = '';
    formSaude.als_ds_alergias              = '';
    formSaude.als_contato_emergencia       = '';
    formSaude.als_telefone_emergencia      = '';
    formSaude.als_plano_saude              = '';
    formSaude.als_cartao_sus               = '';
    formSaude.als_alergia_a                = '';
    formSaude.als_remedio_febre            = '';
    formSaude.als_remedio_cefaleia         = '';
    formSaude.als_patologias               = [];
    formSaude.als_outra_doenca             = '';
    formSaude.als_patologias_infancia      = [];
    formSaude.als_outra_doenca_infancia    = '';
    formSaude.als_deficiencias             = [];
    formSaude.als_transtornos_globais      = [];
    formSaude.als_transtornos_aprendizagem = [];
    formSaude.als_deficiencia_outro        = '';
    formSaude.als_fl_altas_habilidades     = false;
    formSaude.als_cid                      = '';
    formSaude.als_observacao               = '';
    formSaude.als_clinicas                 = [];
    formSaude.als_recursos_inep            = [];

    // Aluno/saúde — preenche depois do reset de saúde
    if (props.alunoNaoCadastrado || !props.aluno) limparFormAluno();
    else preencherFormAluno(props.aluno);
};

watch(() => props.open, (v) => { if (v) reset(); });

// strip accents always — alertarAcentos only controls the banner
const ALUNO_TEXT_FIELDS = [
    'aln_nome', 'aln_filiacao_1', 'aln_filiacao_2',
    'aln_logradouro', 'aln_bairro', 'aln_cidade', 'aln_complemento',
] as const;
ALUNO_TEXT_FIELDS.forEach((field) => {
    watch(() => formAluno[field], (v) => {
        if (typeof v !== 'string') return;
        const next = stripAccents(v);
        if (next !== v) (formAluno as any)[field] = next;
    });
});

const SAUDE_TEXT_FIELDS = [
    'als_ds_alergias', 'als_contato_emergencia', 'als_plano_saude',
    'als_alergia_a', 'als_remedio_febre', 'als_remedio_cefaleia',
    'als_outra_doenca', 'als_outra_doenca_infancia',
    'als_deficiencia_outro', 'als_observacao',
] as const;
SAUDE_TEXT_FIELDS.forEach((field) => {
    watch(() => formSaude[field], (v) => {
        if (typeof v !== 'string') return;
        const next = stripAccents(v);
        if (next !== v) (formSaude as any)[field] = next;
    });
});

watch(possuiDeficiencia, (v) => {
    if (!v) {
        formSaude.als_deficiencias = [];
        activeTab.value = 'dados';
    }
});

// ── Helpers ───────────────────────────────────────────────────────────────────
const toggleArrayItem = (arr: string[], item: string) => {
    const idx = arr.indexOf(item);
    if (idx === -1) arr.push(item);
    else arr.splice(idx, 1);
};

const err = (field: string): string | undefined =>
    errors.value[field] || errors.value[field.replace('.', '_')];

const labelSemestre = (s: number) => `${s}º Semestre`;

// ── Submit ────────────────────────────────────────────────────────────────────
const submit = async () => {
    if (!props.turma) return;

    if (!props.aluno && !props.alunoNaoCadastrado) {
        activeTab.value = 'dados';
        errors.value = {
            _geral: 'Selecione um aluno ou marque "Aluno não cadastrado na base" antes de finalizar a matrícula.',
        };
        return;
    }

    // Valida idade — sempre que série tiver requisito de idade
    {
        const dtNasc   = formAluno.aln_dt_nascimento;
        const dtCorte  = props.turma.ano_letivo?.anl_dt_corte;
        const serIdade = props.turma.serie?.ser_idade;

        if (dtNasc && dtCorte && serIdade != null) {
            const nasc  = new Date(dtNasc  + 'T00:00:00');
            const corte = new Date(dtCorte + 'T00:00:00');
            let idade = corte.getFullYear() - nasc.getFullYear();
            const aniv = new Date(corte.getFullYear(), nasc.getMonth(), nasc.getDate());
            if (corte < aniv) idade--;

            if (idade < serIdade) {
                activeTab.value = 'dados';
                errors.value['aluno.aln_dt_nascimento'] =
                    `Idade insuficiente: ${idade} ano${idade !== 1 ? 's' : ''} na data de corte. Série exige ${serIdade} ano${serIdade !== 1 ? 's' : ''}.`;
                return;
            }
        }
    }

    // Valida aba de saúde se necessário
    const temItemPcd = formSaude.als_deficiencias.length > 0
        || formSaude.als_transtornos_globais.length > 0
        || formSaude.als_transtornos_aprendizagem.length > 0;
    if (possuiDeficiencia.value && !temItemPcd) {
        activeTab.value = 'saude';
        errors.value['saude.als_deficiencias'] = 'Informe ao menos uma deficiência ou transtorno.';
        return;
    }

    processing.value = true;
    errors.value     = {};

    // XSRF-TOKEN cookie é atualizado pelo Laravel em cada resposta (ao contrário da meta tag)
    const xsrfToken = decodeURIComponent(
        document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
    );

    const payload: Record<string, any> = {
        tma_tur_id:         props.turma.tur_id,
        tma_dt_matricula:   formMatricula.tma_dt_matricula,
        tma_obs:            formMatricula.tma_obs,
        possui_deficiencia: possuiDeficiencia.value,
        confirm_duplicata:  confirmDuplicata,
    };

    payload.aluno = { ...formAluno };
    if (props.aluno) {
        payload.tma_aln_id = props.aluno.aln_id;
    }

    if (possuiDeficiencia.value) {
        payload.saude = { ...formSaude };
    }

    try {
        const res = await fetch('/matriculas', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (res.status === 419) {
            errors.value['_geral'] = 'Sua sessão expirou. Recarregue a página e tente novamente.';
            return;
        }

        const json = await res.json().catch(() => ({
            message: 'Não foi possível concluir a matrícula. Recarregue a página e tente novamente.',
        }));

        if (!res.ok) {
            if (res.status === 422) {
                const errs = json.errors ?? {};

                // Duplicata de aluno sem CPF → mostrar dialog de confirmação
                if (errs.duplicata_aluno) {
                    try {
                        duplicataMatches.value = JSON.parse(errs.duplicata_aluno);
                        duplicataOpen.value = true;
                    } catch {
                        errors.value['_geral'] = 'Aluno duplicado detectado.';
                    }
                    return;
                }

                errors.value = errs;
                if (json.message && Object.keys(errs).length === 0) {
                    errors.value['_geral'] = json.message;
                }
            } else {
                errors.value['_geral'] = json.message ?? 'Erro ao realizar matrícula.';
            }
            return;
        }

        pushToast('success', 'Matrícula realizada com sucesso. Gerando comprovante...');
        emit('saved');
        emit('update:open', false);
        window.open(`/matriculas/${json.tma_id}/comprovante`, '_blank');
    } finally {
        processing.value = false;
    }
};

const close = () => emit('update:open', false);

const confirmarDuplicata = () => {
    duplicataOpen.value = false;
    confirmDuplicata = true;
    submit();
};

const cancelarDuplicata = () => {
    duplicataOpen.value = false;
    confirmDuplicata = false;
};

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-indigo-500';
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="flex max-h-[90vh] w-full max-w-3xl flex-col gap-0 overflow-hidden p-0">
            <DialogHeader class="shrink-0 border-b px-6 py-4">
                <DialogTitle>
                    Matrícula
                    <span v-if="turma" class="ml-2 text-sm font-normal text-muted-foreground">
                        {{ turma.escola?.esc_nome }} · {{ turma.serie?.ser_nome }} · Turma {{ turma.tur_nome }} · {{ labelSemestre(turma.tur_semestre) }}
                    </span>
                </DialogTitle>
            </DialogHeader>

            <!-- Aluno selecionado (existente) -->
            <div v-if="aluno && !alunoNaoCadastrado" class="shrink-0 border-b bg-indigo-50 px-6 py-3 text-sm dark:bg-indigo-900/20">
                <span class="font-medium text-indigo-800 dark:text-indigo-200">{{ aluno.aln_nome }}</span>
                <span v-if="aluno.aln_nr_matricula" class="ml-2 text-indigo-600 dark:text-indigo-400">Mat. {{ aluno.aln_nr_matricula }}</span>
                <span v-if="aluno.aln_dt_nascimento" class="ml-2 text-muted-foreground">
                    Nasc. {{ new Date(aluno.aln_dt_nascimento + 'T00:00:00').toLocaleDateString('pt-BR') }}
                </span>
            </div>

            <!-- Abas -->
            <div class="shrink-0 flex gap-0 border-b">
                <button
                    type="button"
                    class="px-5 py-2.5 text-sm font-medium transition-colors"
                    :class="activeTab === 'dados'
                        ? 'border-b-2 border-indigo-600 text-indigo-700 dark:text-indigo-400'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="activeTab = 'dados'"
                >
                    Dados
                </button>
                <button
                    type="button"
                    class="px-5 py-2.5 text-sm font-medium transition-colors"
                    :class="[
                        !tabSaudeDisponivel ? 'cursor-not-allowed opacity-40' : '',
                        activeTab === 'saude'
                            ? 'border-b-2 border-indigo-600 text-indigo-700 dark:text-indigo-400'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    :disabled="!tabSaudeDisponivel"
                    @click="tabSaudeDisponivel && (activeTab = 'saude')"
                >
                    Quadro de Saúde
                    <span v-if="possuiDeficiencia && !formSaude.als_deficiencias.length && !formSaude.als_transtornos_globais.length && !formSaude.als_transtornos_aprendizagem.length" class="ml-1 text-rose-500">*</span>
                </button>
            </div>

            <!-- Conteúdo scrollável -->
            <div class="flex-1 overflow-y-auto px-6 py-5">

                <!-- ── ABA DADOS ─────────────────────────────────────────── -->
                <div v-show="activeTab === 'dados'" class="grid gap-5">

                    <!-- Erro geral -->
                    <div v-if="err('_geral')" class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ err('_geral') }}
                    </div>

                    <!-- Possui Deficiência -->
                    <div class="flex items-center gap-3 rounded-lg border bg-muted/30 px-4 py-3">
                        <input id="possui_deficiencia" type="checkbox" v-model="possuiDeficiencia" class="size-4 accent-indigo-600 cursor-pointer" />
                        <label for="possui_deficiencia" class="cursor-pointer text-sm font-medium">Possui alguma deficiência</label>
                        <span v-if="possuiDeficiencia" class="ml-auto text-xs text-indigo-600">Preencha a aba "Quadro de Saúde"</span>
                    </div>

                    <!-- Aviso acentuação -->
                    <p v-if="alertarAcentos" class="flex items-center gap-1.5 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-400">
                        <span class="font-semibold">Atenção:</span> sistema configurado para nomes sem acentuação — acentos são removidos automaticamente.
                    </p>

                    <!-- Dados do aluno -->
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Dados do Aluno</p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5 sm:col-span-2">
                                <FormLabel for="aln_nome" :required="true">Nome</FormLabel>
                                <Input id="aln_nome" v-model="formAluno.aln_nome" maxlength="100" class="uppercase" />
                                <InputError :message="err('aluno.aln_nome')" />
                            </div>

                            <div class="grid gap-1.5">
                                <FormLabel for="aln_cpf" :required="cpfObrigatorio">CPF</FormLabel>
                                <Input id="aln_cpf" v-model="formAluno.aln_cpf" maxlength="14" placeholder="000.000.000-00" />
                                <InputError :message="err('aluno.aln_cpf')" />
                            </div>

                            <div class="grid gap-1.5">
                                <FormLabel for="aln_nis">NIS</FormLabel>
                                <Input id="aln_nis" v-model="formAluno.aln_nis" maxlength="11" placeholder="00000000000" />
                                <InputError :message="err('aluno.aln_nis')" />
                            </div>

                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between">
                                    <FormLabel for="aln_filiacao_1" :required="true">Filiação 1</FormLabel>
                                    <div class="flex items-center gap-4 text-sm">
                                        <label class="flex cursor-pointer items-center gap-1.5">
                                            <input type="radio" v-model="formAluno.aln_filiacao_1_tipo" value="MAE" class="accent-indigo-600" />
                                            Mãe
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-1.5">
                                            <input type="radio" v-model="formAluno.aln_filiacao_1_tipo" value="PAI" class="accent-indigo-600" />
                                            Pai
                                        </label>
                                    </div>
                                </div>
                                <Input id="aln_filiacao_1" v-model="formAluno.aln_filiacao_1" maxlength="100" class="uppercase" />
                                <InputError :message="err('aluno.aln_filiacao_1')" />
                                <InputError :message="err('aluno.aln_filiacao_1_tipo')" />
                            </div>

                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between">
                                    <FormLabel for="aln_filiacao_2">Filiação 2</FormLabel>
                                    <div class="flex items-center gap-4 text-sm">
                                        <label class="flex cursor-pointer items-center gap-1.5">
                                            <input type="radio" v-model="formAluno.aln_filiacao_2_tipo" value="MAE" class="accent-indigo-600" />
                                            Mãe
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-1.5">
                                            <input type="radio" v-model="formAluno.aln_filiacao_2_tipo" value="PAI" class="accent-indigo-600" />
                                            Pai
                                        </label>
                                    </div>
                                </div>
                                <Input id="aln_filiacao_2" v-model="formAluno.aln_filiacao_2" maxlength="100" class="uppercase" />
                                <InputError :message="err('aluno.aln_filiacao_2')" />
                                <InputError :message="err('aluno.aln_filiacao_2_tipo')" />
                            </div>

                            <div class="grid gap-1.5">
                                <FormLabel for="aln_sexo" :required="true">Sexo</FormLabel>
                                <select id="aln_sexo" v-model="formAluno.aln_sexo" :class="selectClass">
                                    <option value="">Selecione...</option>
                                    <option value="F">Feminino</option>
                                    <option value="M">Masculino</option>
                                </select>
                                <InputError :message="err('aluno.aln_sexo')" />
                            </div>

                            <div class="grid gap-1.5">
                                <FormLabel for="aln_dt_nascimento" :required="true">Data de Nascimento</FormLabel>
                                <Input
                                    id="aln_dt_nascimento"
                                    type="date"
                                    v-model="formAluno.aln_dt_nascimento"
                                    :max="new Date().toISOString().slice(0, 10)"
                                    @input="(e: Event) => {
                                        const v = (e.target as HTMLInputElement).value;
                                        const year = v?.split('-')[0] ?? '';
                                        if (year.length > 4) (e.target as HTMLInputElement).value = formAluno.aln_dt_nascimento;
                                    }"
                                />
                                <InputError :message="err('aluno.aln_dt_nascimento')" />
                            </div>

                            <div class="grid gap-1.5">
                                <FormLabel for="aln_cor_raca" :required="true">Cor / Raça</FormLabel>
                                <select id="aln_cor_raca" v-model.number="formAluno.aln_cor_raca" :class="selectClass">
                                    <option :value="null">Selecione...</option>
                                    <option v-for="opt in COR_RACA" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                                <InputError :message="err('aluno.aln_cor_raca')" />
                            </div>

                            <div class="grid gap-1.5">
                                <FormLabel for="aln_mun_id_nasc" :required="true">Município de Nascimento</FormLabel>
                                <MunicipioCombobox
                                    :model-value="formAluno.aln_mun_id_nasc"
                                    :initial="municipioNascimentoInicial"
                                    :invalid="!!err('aluno.aln_mun_id_nasc')"
                                    @update:model-value="(v) => (formAluno.aln_mun_id_nasc = v)"
                                />
                                <InputError :message="err('aluno.aln_mun_id_nasc')" />
                            </div>

                            <div class="grid gap-1.5">
                                <FormLabel for="aln_nr_certidao">Nº Certidão</FormLabel>
                                <Input id="aln_nr_certidao" v-model="formAluno.aln_nr_certidao" maxlength="32" />
                                <InputError :message="err('aluno.aln_nr_certidao')" />
                            </div>
                        </div>

                        <!-- Endereço -->
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Endereço</p>
                        <div class="grid gap-4 sm:grid-cols-4">
                            <div class="grid gap-1.5 sm:col-span-1">
                                <FormLabel for="aln_cep" :required="true">CEP</FormLabel>
                                <Input id="aln_cep" v-model="formAluno.aln_cep" maxlength="9" placeholder="00000-000" />
                                <InputError :message="err('aluno.aln_cep')" />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-3">
                                <FormLabel for="aln_logradouro">Logradouro</FormLabel>
                                <Input id="aln_logradouro" v-model="formAluno.aln_logradouro" maxlength="150" />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-2">
                                <FormLabel for="aln_bairro">Bairro</FormLabel>
                                <Input id="aln_bairro" v-model="formAluno.aln_bairro" maxlength="100" />
                            </div>
                            <div class="grid gap-1.5">
                                <FormLabel for="aln_numero">Número</FormLabel>
                                <Input id="aln_numero" v-model="formAluno.aln_numero" maxlength="10" />
                            </div>
                            <div class="grid gap-1.5">
                                <FormLabel for="aln_uf">UF</FormLabel>
                                <Input id="aln_uf" v-model="formAluno.aln_uf" maxlength="2" class="uppercase" />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-3">
                                <FormLabel for="aln_cidade">Cidade</FormLabel>
                                <Input id="aln_cidade" v-model="formAluno.aln_cidade" maxlength="100" />
                            </div>
                            <div class="grid gap-1.5 sm:col-span-1">
                                <FormLabel for="aln_complemento">Complemento</FormLabel>
                                <Input id="aln_complemento" v-model="formAluno.aln_complemento" maxlength="100" />
                            </div>
                        </div>

                        <!-- Contato -->
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Contato</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <FormLabel for="aln_telefone">Telefone</FormLabel>
                                <Input id="aln_telefone" v-model="formAluno.aln_telefone" maxlength="11" />
                            </div>
                            <div class="grid gap-1.5">
                                <FormLabel for="aln_email">E-mail</FormLabel>
                                <Input id="aln_email" type="email" v-model="formAluno.aln_email" maxlength="150" />
                            </div>
                        </div>
                    <!-- Checkboxes de matrícula -->
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Documentos / Programas</p>

                    <div class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" v-model="formAluno.aln_fl_trouxe_transferencia" class="size-4 accent-indigo-600" />
                            Trouxe Transferência
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" v-model="formAluno.aln_fl_bolsa_familia" class="size-4 accent-indigo-600" />
                            Bolsa Família
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" v-model="formAluno.aln_fl_trouxe_rg" class="size-4 accent-indigo-600" />
                            Trouxe R.G.
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" v-model="formAluno.aln_fl_recebe_merenda" class="size-4 accent-indigo-600" />
                            Recebe Merenda
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" v-model="formAluno.aln_fl_trouxe_reg_nascimento" class="size-4 accent-indigo-600" />
                            Trouxe Reg. Nascimento
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" v-model="formAluno.aln_fl_usa_transporte" class="size-4 accent-indigo-600" />
                            Usa Transporte
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" v-model="formAluno.aln_fl_usa_biblioteca" class="size-4 accent-indigo-600" />
                            Usa Biblioteca
                        </label>
                    </div>

                    <!-- Observação -->
                    <div class="grid gap-1.5">
                        <FormLabel for="tma_obs">Observação</FormLabel>
                        <textarea
                            id="tma_obs"
                            v-model="formMatricula.tma_obs"
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                            placeholder="Observações sobre a matrícula..."
                        />
                    </div>
                </div>

                <!-- ── ABA SAÚDE ────────────────────────────────────────── -->
                <div v-show="activeTab === 'saude'" class="space-y-6">

                    <!-- Emergência / Contato -->
                    <div class="rounded-xl border bg-card p-5 shadow-sm">
                        <h3 class="mb-4 text-sm font-semibold">Contato de Emergência</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="als_contato_emergencia">Contato (Emergência)</Label>
                                <Input id="als_contato_emergencia" v-model="formSaude.als_contato_emergencia" maxlength="150" />
                                <InputError :message="err('saude.als_contato_emergencia')" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="als_telefone_emergencia">Telefone (Emergência)</Label>
                                <Input id="als_telefone_emergencia" v-model="formSaude.als_telefone_emergencia" maxlength="20" placeholder="(00) 00000-0000" />
                                <InputError :message="err('saude.als_telefone_emergencia')" />
                            </div>
                        </div>
                    </div>

                    <!-- Saúde Básica -->
                    <div class="rounded-xl border bg-card p-5 shadow-sm">
                        <h3 class="mb-4 text-sm font-semibold">Saúde</h3>
                        <div class="grid gap-4 sm:grid-cols-4">
                            <div class="grid gap-2">
                                <Label for="als_tipo_sanguineo">Tipo de Sangue</Label>
                                <select id="als_tipo_sanguineo" v-model="formSaude.als_tipo_sanguineo" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                                    <option value="">Selecione...</option>
                                    <option v-for="t in TIPOS_SANGUINEOS" :key="t" :value="t">{{ t }}</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="als_plano_saude">Plano de Saúde</Label>
                                <Input id="als_plano_saude" v-model="formSaude.als_plano_saude" maxlength="100" />
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="als_cartao_sus">Nº Cartão do SUS</Label>
                                <Input id="als_cartao_sus" v-model="formSaude.als_cartao_sus" maxlength="20" />
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="als_alergia_a">Alergia a</Label>
                                <Input id="als_alergia_a" v-model="formSaude.als_alergia_a" maxlength="500" />
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="als_ds_alergias">Alergias / Restrições Alimentares</Label>
                                <Input id="als_ds_alergias" v-model="formSaude.als_ds_alergias" maxlength="500" />
                            </div>
                        </div>
                        <h4 class="mb-2 mt-4 text-xs font-semibold text-muted-foreground">Remédios Indicados</h4>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="als_remedio_febre">Febre</Label>
                                <Input id="als_remedio_febre" v-model="formSaude.als_remedio_febre" maxlength="200" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="als_remedio_cefaleia">Cefaléia</Label>
                                <Input id="als_remedio_cefaleia" v-model="formSaude.als_remedio_cefaleia" maxlength="200" />
                            </div>
                        </div>
                    </div>

                    <!-- Patologias -->
                    <fieldset class="rounded-xl border bg-card p-5 shadow-sm">
                        <legend class="px-2 text-sm font-semibold">Patologia</legend>
                        <div class="grid gap-x-6 gap-y-2 sm:grid-cols-3">
                            <label v-for="p in PATOLOGIAS" :key="p" class="flex items-center gap-2 text-sm">
                                <input type="checkbox" :checked="formSaude.als_patologias.includes(p)" @change="toggleArrayItem(formSaude.als_patologias, p)" class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                                {{ p }}
                            </label>
                        </div>
                        <div class="mt-3 grid gap-2">
                            <Label for="als_outra_doenca">Outra Doença</Label>
                            <Input id="als_outra_doenca" v-model="formSaude.als_outra_doenca" maxlength="500" />
                        </div>
                    </fieldset>

                    <!-- Patologias Infância -->
                    <fieldset class="rounded-xl border bg-card p-5 shadow-sm">
                        <legend class="px-2 text-sm font-semibold">Patologias Contraídas na Infância</legend>
                        <div class="grid gap-x-6 gap-y-2 sm:grid-cols-3">
                            <label v-for="p in PATOLOGIAS_INFANCIA" :key="p" class="flex items-center gap-2 text-sm">
                                <input type="checkbox" :checked="formSaude.als_patologias_infancia.includes(p)" @change="toggleArrayItem(formSaude.als_patologias_infancia, p)" class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                                {{ p }}
                            </label>
                        </div>
                        <div class="mt-3 grid gap-2">
                            <Label for="als_outra_doenca_infancia">Outra Doença</Label>
                            <Input id="als_outra_doenca_infancia" v-model="formSaude.als_outra_doenca_infancia" maxlength="500" />
                        </div>
                    </fieldset>

                    <!-- Deficiência / Transtornos / Altas Habilidades -->
                    <fieldset class="rounded-xl border bg-card p-5 shadow-sm">
                        <legend class="px-2 text-sm font-semibold">Tipo de Deficiência, Transtorno Global do Desenvolvimento ou Altas Habilidades/Superdotação</legend>

                        <div class="mb-5 flex items-center gap-3">
                            <Switch id="als_fl_pcd" :model-value="possuiDeficiencia" @update:model-value="possuiDeficiencia = $event" />
                            <Label for="als_fl_pcd" class="text-sm font-medium">Aluno com Deficiência (PCD), TGD ou Altas Habilidades</Label>
                        </div>

                        <div :class="{ 'pointer-events-none opacity-40': !possuiDeficiencia }">
                            <InputError :message="err('saude.als_deficiencias')" />
                            <div class="grid gap-6 sm:grid-cols-3">
                                <div>
                                    <h4 class="mb-2 text-xs font-semibold text-muted-foreground">Deficiência <span class="text-rose-500">*</span></h4>
                                    <div class="space-y-2">
                                        <label v-for="d in DEFICIENCIAS" :key="d" class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" :checked="formSaude.als_deficiencias.includes(d)" @change="toggleArrayItem(formSaude.als_deficiencias, d)" class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                                            {{ d }}
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="mb-2 text-xs font-semibold text-muted-foreground">Transtorno Global de Desenvolvimento</h4>
                                    <div class="space-y-2">
                                        <label v-for="t in TRANSTORNOS_GLOBAIS" :key="t" class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" :checked="formSaude.als_transtornos_globais.includes(t)" @change="toggleArrayItem(formSaude.als_transtornos_globais, t)" class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                                            {{ t }}
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="mb-2 text-xs font-semibold text-muted-foreground">Transtornos que Impactam a Aprendizagem</h4>
                                    <div class="space-y-2">
                                        <label v-for="t in TRANSTORNOS_APRENDIZAGEM" :key="t" class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" :checked="formSaude.als_transtornos_aprendizagem.includes(t)" @change="toggleArrayItem(formSaude.als_transtornos_aprendizagem, t)" class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                                            {{ t }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="als_deficiencia_outro">Outro(a)</Label>
                                    <Input id="als_deficiencia_outro" v-model="formSaude.als_deficiencia_outro" maxlength="500" />
                                    <InputError :message="err('saude.als_deficiencia_outro')" />
                                </div>
                            </div>
                            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                                <div class="flex items-center gap-3">
                                    <Switch id="als_fl_altas_habilidades" v-model="formSaude.als_fl_altas_habilidades" />
                                    <Label for="als_fl_altas_habilidades" class="text-sm font-normal">Altas Habilidades/Superdotação</Label>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="als_cid">CID</Label>
                                    <Input id="als_cid" v-model="formSaude.als_cid" maxlength="20" />
                                    <InputError :message="err('saude.als_cid')" />
                                </div>
                            </div>
                            <div class="mt-4 grid gap-2">
                                <Label for="als_observacao">Observação</Label>
                                <textarea id="als_observacao" v-model="formSaude.als_observacao" rows="3" maxlength="2000" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500" />
                            </div>
                        </div>
                    </fieldset>

                    <!-- Clínica -->
                    <fieldset class="rounded-xl border bg-card p-5 shadow-sm">
                        <legend class="px-2 text-sm font-semibold">Clínica</legend>
                        <div class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                            <label v-for="c in CLINICAS" :key="c" class="flex items-center gap-2 text-sm">
                                <input type="checkbox" :checked="formSaude.als_clinicas.includes(c)" @change="toggleArrayItem(formSaude.als_clinicas, c)" class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                                {{ c }}
                            </label>
                        </div>
                    </fieldset>

                    <!-- Recursos INEP -->
                    <fieldset class="rounded-xl border bg-card p-5 shadow-sm">
                        <legend class="px-2 text-sm font-semibold">Recursos necessários para a participação do aluno em avaliações do INEP</legend>
                        <div class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                            <label v-for="r in RECURSOS_INEP" :key="r" class="flex items-center gap-2 text-sm">
                                <input type="checkbox" :checked="formSaude.als_recursos_inep.includes(r)" @change="toggleArrayItem(formSaude.als_recursos_inep, r)" class="size-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                                {{ r }}
                            </label>
                        </div>
                    </fieldset>

                </div>
            </div>

            <!-- Footer -->
            <div class="shrink-0 flex items-center justify-between border-t bg-muted/20 px-6 py-4">
                <Button type="button" variant="outline" @click="close">
                    <X class="mr-2 size-4" /> Cancelar
                </Button>
                <Button
                    type="button"
                    :disabled="processing"
                    class="bg-indigo-600 hover:bg-indigo-700"
                    @click="submit"
                >
                    <LoaderCircle v-if="processing" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    Finalizar Matrícula
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <HomonimoDialog
        v-model:open="duplicataOpen"
        :matches="duplicataMatches"
        :processing="processing"
        @confirm="confirmarDuplicata"
        @cancel="cancelarDuplicata"
    />
</template>
