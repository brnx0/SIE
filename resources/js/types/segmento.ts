export interface Segmento {
    seg_id: number;
    seg_cd_inep: string | null;
    seg_nome_reduzido: string;
    seg_nome_completo: string;
    seg_qt_anos_escolares: number;
    seg_ordem: number;
    seg_dt_abertura: string;
    seg_dt_encerramento: string | null;
    seg_fl_prereq: boolean;
    seg_ds_prereq: string | null;
    seg_observacoes: string | null;
    seg_fl_ativo: boolean;
    seg_created_at: string | null;
    seg_updated_at: string | null;
}

export interface SegmentoFormData {
    seg_cd_inep: string;
    seg_nome_reduzido: string;
    seg_nome_completo: string;
    seg_qt_anos_escolares: number | null;
    seg_ordem: number | null;
    seg_dt_abertura: string;
    seg_dt_encerramento: string;
    seg_fl_prereq: boolean;
    seg_ds_prereq: string;
    seg_observacoes: string;
    seg_fl_ativo: boolean;
    _method?: 'put' | 'post';
    [key: string]: unknown;
}
