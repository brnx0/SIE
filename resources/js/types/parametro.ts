import type { Municipio } from '@/types/aluno';

export interface ParametroEntidade {
    par_id: number;
    par_nome_entidade: string;
    par_msg_cab_secretaria: string;
    par_msg_cab_estado: string;
    par_endereco: string;
    par_mun_id: number | null;
    par_logomarca: string | null;
    par_logomarca_url: string | null;
    par_brasao: string | null;
    par_brasao_url: string | null;
    par_fl_nome_pessoa_caixa_alta: boolean;
    par_fl_nome_escola_caixa_alta: boolean;
    par_fl_alertar_homonimos: boolean;
    par_fl_alertar_acentos_nomes: boolean;
    par_fl_validar_idade_serie: boolean;
    par_fl_gerar_matricula_auto: boolean;
    par_fl_validar_carga_prof: boolean;
    par_fl_cpf_obrigatorio: boolean;
    par_fl_fardamento_obrigatorio: boolean;
    par_tipo_validacao_carga: 'bloquear' | 'avisar' | null;
    municipio?: Municipio | null;
}

export interface AnoLetivoUserRef {
    id: number;
    name: string;
}

export interface AnoLetivo {
    anl_id: number;
    anl_ano: number;
    anl_dt_inicio_ano: string;
    anl_dt_fim: string;
    anl_dt_corte: string;
    anl_dt_censo: string | null;
    anl_fl_em_exercicio: boolean;
    anl_fl_progressao_parcial: boolean;
    anl_fl_aprovacao_conselho_freq: boolean;
    anl_frequencia_minima: number | string | null;
    anl_media_geral: number | string | null;
    anl_conceito_modo: 'faixa' | 'conceito' | null;
    anl_created_at: string | null;
    anl_updated_at: string | null;
    created_by?: AnoLetivoUserRef | null;
    updated_by?: AnoLetivoUserRef | null;
}

export interface AnoLetivoFormData {
    anl_ano: number | '';
    anl_dt_inicio_ano: string;
    anl_dt_fim: string;
    anl_dt_corte: string;
    anl_dt_censo: string;
    anl_fl_em_exercicio: boolean;
    anl_fl_progressao_parcial: boolean;
    anl_fl_aprovacao_conselho_freq: boolean;
    anl_frequencia_minima: number | '';
    anl_media_geral: number | '';
    anl_conceito_modo: 'faixa' | 'conceito';
    [key: string]: any;
}

export type UnidadeTipo = 'bimestre' | 'unidade' | 'trimestre' | 'semestre';

export const UNIDADE_TIPO_LABELS: Record<UnidadeTipo, string> = {
    bimestre:  'Bimestral',
    unidade:   'Por Unidade',
    trimestre: 'Trimestral',
    semestre:  'Semestral',
};

export const UNIDADE_LIMITES: Record<UnidadeTipo, number> = {
    bimestre:  4,
    unidade:   4,
    trimestre: 3,
    semestre:  2,
};

export const UNIDADE_ORDINAL: Record<number, string> = {
    1: '1º',
    2: '2º',
    3: '3º',
    4: '4º',
};

export interface Unidade {
    uni_id: number;
    uni_anl_id: number;
    uni_tipo: UnidadeTipo;
    uni_numero: number;
    uni_dt_inicio: string;
    uni_dt_fim: string;
    uni_dias_extensao: number | null;
    uni_dt_fim_efetivo: string;
    uni_created_at?: string | null;
    uni_updated_at?: string | null;
    ano_letivo?: { anl_id: number; anl_ano: number } | null;
}

export interface DiaNaoLetivo {
    dnl_id: number;
    dnl_anl_id: number;
    dnl_dt_dia: string;
    dnl_dt_fim?: string | null;
    dnl_descricao: string;
    dnl_created_at?: string | null;
    dnl_updated_at?: string | null;
}

export interface DiaNaoLetivoFormData {
    dnl_anl_id: number | null;
    dnl_dt_dia: string;
    dnl_dt_fim: string;
    dnl_descricao: string;
    _method?: 'put';
}

export interface MediaEscola {
    mde_id: number;
    mde_anl_id: number;
    mde_esc_id: number;
    mde_media: number | string;
    escola?: { esc_id: number; esc_nome: string } | null;
}

export interface MediaEscolaFormData {
    mde_anl_id: number | null;
    mde_esc_id: number | null;
    mde_media: number | '';
    _method?: 'put';
}

export interface Conceito {
    cnc_id: number;
    cnc_sigla: string;
    cnc_descricao: string;
    cnc_limite_inferior: number | string;
    cnc_limite_superior: number | string;
}

export interface ConceitoFormData {
    cnc_sigla: string;
    cnc_descricao: string;
    cnc_limite_inferior: number | '';
    cnc_limite_superior: number | '';
    _method?: 'put';
}

export interface TurmaAlunoSituacaoResumo {
    tas_cod: number;
    tas_descricao: string;
}

export interface SituacaoBloqueio {
    sba_id: number;
    sba_tas_cod: number;
    situacao?: TurmaAlunoSituacaoResumo | null;
}

export interface AtendimentoAee {
    ate_id: number;
    ate_descricao: string;
    ate_fl_ativo: boolean;
}

export interface Atividade {
    atv_id: number;
    atv_descricao: string;
    atv_fl_ativo: boolean;
}

export interface GradeHorario {
    grh_id: number;
    grh_seg_id: number;
    grh_turno: 'm' | 't' | 'n';
    grh_hora: string;
    grh_ordem: number;
    segmento?: { seg_id: number; seg_nome_reduzido: string } | null;
}

export interface SegmentoResumo {
    seg_id: number;
    seg_nome_reduzido: string;
    seg_nome_completo: string;
}

export interface DisciplinaResumo {
    dis_id: number;
    dis_nome: string;
    dis_sigla?: string | null;
}

export interface GradeDisciplinar {
    grd_id: number;
    grd_anl_id: number;
    grd_seg_id: number;
    grd_ser_id: number;
    grd_dis_id: number;
    grd_ordem: number;
    grd_nome_alternativo: string | null;
    grd_fl_ativo: boolean;
    disciplina?: DisciplinaResumo | null;
}

export interface GradeDisciplinarFormData {
    grd_anl_id: number | null;
    grd_seg_id: number | null;
    grd_ser_id: number | null;
    grd_dis_id: number | null;
    grd_nome_alternativo: string;
    grd_fl_ativo: boolean;
    _method?: 'put';
}

export interface UnidadeFormData {
    uni_anl_id: number | null;
    uni_tipo: UnidadeTipo | '';
    uni_dt_inicio: string;
    uni_dt_fim: string;
    uni_dias_extensao: number | '';
    _method?: 'put';
}

export interface ParametroEntidadeFormData {
    par_nome_entidade: string;
    par_msg_cab_secretaria: string;
    par_msg_cab_estado: string;
    par_endereco: string;
    par_mun_id: number | null;
    par_logomarca: File | null;
    par_brasao: File | null;
    par_fl_nome_pessoa_caixa_alta: boolean;
    par_fl_nome_escola_caixa_alta: boolean;
    par_fl_alertar_homonimos: boolean;
    par_fl_alertar_acentos_nomes: boolean;
    par_fl_validar_idade_serie: boolean;
    par_fl_gerar_matricula_auto: boolean;
    par_fl_validar_carga_prof: boolean;
    par_fl_cpf_obrigatorio: boolean;
    par_fl_fardamento_obrigatorio: boolean;
    par_tipo_validacao_carga: 'bloquear' | 'avisar';
    _method?: 'put' | 'post';
    [key: string]: any;
}
