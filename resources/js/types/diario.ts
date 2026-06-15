export type PlanoStatus = 'pendente' | 'aprovado' | 'reprovado' | 'correcao';

export interface IndicadorResumo {
    ind_id: number;
    ind_descricao: string;
    ind_dis_id: number | null;
    ind_fl_ativo: boolean;
}

export interface PlanoTurmaResumo {
    tur_id: number;
    tur_nome: string;
    tur_esc_id: number;
    tur_anl_id: number;
    tur_ser_id: number;
    esc_nome?: string;
    ser_nome?: string;
}

export interface PlanoDisciplinaResumo {
    dis_id: number;
    dis_nome: string;
}

export interface PlanoUnidade {
    uni_id: number;
    uni_tipo: string;
    uni_numero: number;
    uni_dt_inicio: string;
    uni_dt_fim: string;
    uni_anl_id?: number;
}

export interface PlanoIndicadorPivot {
    dpi_id: number;
    dpi_dpa_id: number;
    dpi_ind_id: number;
}

export interface PlanoAula {
    dpa_id: number;
    dpa_user_id: number;
    dpa_esc_id: number;
    dpa_anl_id: number;
    dpa_tur_id: number;
    dpa_dis_id: number;
    dpa_uni_id: number;
    dpa_tema: string;
    dpa_dt_inicio: string;
    dpa_dt_fim: string;
    dpa_objeto_conhecimento: string;
    dpa_estrategias: string | null;
    dpa_recursos: string;
    dpa_competencias: string | null;
    dpa_avaliacao: string | null;
    dpa_objetivos_complementares: string | null;
    dpa_obs_coordenador: string | null;
    dpa_status: PlanoStatus;
    turma?: PlanoTurmaResumo & { serie?: { ser_id: number; ser_nome: string } };
    disciplina?: PlanoDisciplinaResumo;
    unidade?: PlanoUnidade;
    escola?: { esc_id: number; esc_nome: string };
    indicadores?: PlanoIndicadorPivot[];
}

export interface ProfessorResumoDiario {
    fun_id: number;
    fun_nome: string;
}

export interface AnoLetivoResumo {
    anl_id: number;
    anl_ano: number;
    anl_fl_em_exercicio: boolean;
}

export interface PlanoAeeTurmaResumo {
    tur_id: number;
    tur_nome: string;
    tur_esc_id: number;
    tur_anl_id: number;
    esc_nome?: string;
}

export interface PlanoAee {
    dae_id: number;
    dae_user_id: number;
    dae_esc_id: number;
    dae_anl_id: number;
    dae_tur_id: number;
    dae_tema: string;
    dae_dt_inicio: string;
    dae_dt_fim: string;
    dae_objetivo: string;
    dae_diagnostico: string | null;
    dae_area_desenv: string | null;
    dae_metas: string;
    dae_estrategias: string;
    dae_recursos: string;
    dae_avaliacao: string | null;
    dae_obs_coordenador: string | null;
    dae_status: PlanoStatus;
    turma?: PlanoAeeTurmaResumo;
    escola?: { esc_id: number; esc_nome: string };
    anoLetivo?: { anl_id: number; anl_ano: number };
}
