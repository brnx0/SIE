export interface AnoLetivoOption {
    anl_id: number;
    anl_ano: number;
    anl_fl_em_exercicio: boolean;
}

export interface SerieOption {
    ser_id: number;
    ser_nome: string;
}

export interface EscolaSegmento {
    esg_id: number;
    esc_id: number;
    seg_id: number;
    anl_id_inicio: number;
    anl_id_fim: number | null;
    ser_id_inicio: number;
    ser_id_fim: number;
    esg_fl_ativo: boolean;
    esg_motivo: string | null;
    esg_created_at: string | null;
    esg_updated_at: string | null;
    segmento?: { seg_id: number; seg_nome_reduzido: string; seg_nome_completo: string };
    ano_letivo_inicio?: AnoLetivoOption;
    ano_letivo_fim?: AnoLetivoOption | null;
    serie_inicio?: SerieOption;
    serie_fim?: SerieOption;
}

export interface EscolaSegmentoFormData {
    seg_id: number | null;
    anl_id_inicio: number | null;
    anl_id_fim: number | null;
    ser_id_inicio: number | null;
    ser_id_fim: number | null;
    esg_fl_ativo: boolean;
    esg_motivo: string;
    [key: string]: unknown;
}
