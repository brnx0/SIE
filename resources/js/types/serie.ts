import type { Segmento } from '@/types/segmento';

export interface Serie {
    ser_id: number;
    seg_id: number;
    ser_cd_referencia: string | null;
    ser_nome: string;
    ser_carga_horaria: number | null;
    ser_qt_aulas_semestrais: number | null;
    ser_qt_aulas_anuais: number | null;
    ser_idade: number;
    ser_serie_equivalente: string | null;
    ser_nr_ordenacao: number;
    ser_ordem_no_segmento: number;
    ser_fl_ativo: boolean;
    ser_tipo_avaliacao: string[] | null;
    ser_tipo_avaliacao_descritiva: 'por_aluno' | 'por_unidade' | null;
    ser_promo_ser_id_1: number | null;
    ser_promo_ser_id_2: number | null;
    ser_cons_ser_id_1: number | null;
    ser_cons_ser_id_2: number | null;
    ser_created_at: string | null;
    ser_updated_at: string | null;
    segmento?: Pick<Segmento, 'seg_id' | 'seg_nome_reduzido'>;
    promoSerie1?: SerieResumo | null;
    promoSerie2?: SerieResumo | null;
    consSerie1?: SerieResumo | null;
    consSerie2?: SerieResumo | null;
}

export interface SerieResumo {
    ser_id: number;
    ser_nome: string;
}

export interface SerieIndicador {
    ind_id: number;
    ind_ser_id: number | null;
    ind_descricao: string;
    ind_fl_ativo: boolean;
}

export interface SerieParaReplicar {
    ser_id: number;
    ser_nome: string;
    seg_nome: string | null;
}

export interface SerieFormData {
    seg_id: number | null;
    ser_cd_referencia: string;
    ser_nome: string;
    ser_carga_horaria: number | null;
    ser_qt_aulas_semestrais: number | null;
    ser_qt_aulas_anuais: number | null;
    ser_idade: number | null;
    ser_serie_equivalente: string;
    ser_nr_ordenacao: number | null;
    ser_ordem_no_segmento: number | null;
    ser_fl_ativo: boolean;
    ser_tipo_avaliacao: string[];
    ser_tipo_avaliacao_descritiva: string;
    ser_promo_ser_id_1: number | null;
    ser_promo_ser_id_2: number | null;
    ser_cons_ser_id_1: number | null;
    ser_cons_ser_id_2: number | null;
    _method?: 'put' | 'post';
    [key: string]: unknown;
}
