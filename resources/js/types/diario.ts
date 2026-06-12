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
    dpa_fun_id: number;
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
