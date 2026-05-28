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

export type TipoUnidadeTipo = 'unidade_didatica' | 'bimestral' | 'fase' | 'semestral' | 'trimestral';

export const TIPO_UNIDADE_LABELS: Record<TipoUnidadeTipo, string> = {
    unidade_didatica: 'Unidade Didática',
    bimestral:        'Bimestral',
    fase:             'Fase',
    semestral:        'Semestral',
    trimestral:       'Trimestral',
};

export interface TipoUnidade {
    tun_id: number;
    tun_tipo: TipoUnidadeTipo;
    tun_anl_id_inicio: number;
    tun_anl_id_fim: number | null;
    ano_letivo_inicio?: { anl_id: number; anl_ano: number } | null;
    ano_letivo_fim?: { anl_id: number; anl_ano: number } | null;
    tun_created_at?: string | null;
    tun_updated_at?: string | null;
}

export interface TipoUnidadeFormData {
    tun_tipo: TipoUnidadeTipo | '';
    tun_anl_id_inicio: number | null;
    tun_anl_id_fim: number | null;
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
