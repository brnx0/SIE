export interface AreaConhecimento {
    arc_id: number;
    arc_nome: string;
}

export interface DisciplinaIndicador {
    ind_id: number;
    ind_dis_id: number;
    ind_descricao: string;
    ind_fl_ativo: boolean;
    ind_created_at: string | null;
    ind_updated_at: string | null;
}

export interface Disciplina {
    dis_id: number;
    arc_id: number;
    dis_cod_ref: number | null;
    dis_nome_mec: string;
    dis_nome: string;
    dis_sigla: string | null;
    dis_fl_ativo: boolean;
    dis_created_at: string | null;
    dis_updated_at: string | null;
    areaConhecimento?: Pick<AreaConhecimento, 'arc_id' | 'arc_nome'>;
    indicadores?: DisciplinaIndicador[];
}

export interface DisciplinaFormData {
    arc_id: number | null;
    dis_cod_ref: number | null;
    dis_nome_mec: string;
    dis_nome: string;
    dis_sigla: string;
    dis_fl_ativo: boolean;
    _method?: 'put';
    [key: string]: unknown;
}
