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
    anl_dt_inicio_1sem: string;
    anl_dt_inicio_2sem: string;
    anl_dt_fim: string;
    anl_dt_censo: string | null;
    anl_fl_em_exercicio: boolean;
    anl_fl_progressao_parcial: boolean;
    anl_fl_aprovacao_conselho_freq: boolean;
    anl_created_at: string | null;
    anl_updated_at: string | null;
    created_by?: AnoLetivoUserRef | null;
    updated_by?: AnoLetivoUserRef | null;
}

export interface AnoLetivoFormData {
    anl_ano: number | '';
    anl_dt_inicio_ano: string;
    anl_dt_inicio_1sem: string;
    anl_dt_inicio_2sem: string;
    anl_dt_fim: string;
    anl_dt_censo: string;
    anl_fl_em_exercicio: boolean;
    anl_fl_progressao_parcial: boolean;
    anl_fl_aprovacao_conselho_freq: boolean;
    [key: string]: any;
}

export type UnidadeTipo = 'bimestre' | 'trimestre' | 'semestre';

export const UNIDADE_TIPO_LABELS: Record<UnidadeTipo, string> = {
    bimestre:  'Bimestral',
    trimestre: 'Trimestral',
    semestre:  'Semestral',
};

export const UNIDADE_LIMITES: Record<UnidadeTipo, number> = {
    bimestre:  4,
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
