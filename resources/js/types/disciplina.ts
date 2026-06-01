export interface AreaConhecimento {
    arc_id: number;
    arc_nome: string;
}

export interface Disciplina {
    dis_id: number;
    arc_id: number;
    dis_cod_ref: number | null;
    dis_nome_mec: string;
    dis_nome: string;
    dis_sigla: string | null;
    dis_fl_fundamental: boolean;
    dis_fl_medio: boolean;
    dis_fl_pedagogica: boolean;
    dis_fl_ativo: boolean;
    dis_created_at: string | null;
    dis_updated_at: string | null;
    areaConhecimento?: Pick<AreaConhecimento, 'arc_id' | 'arc_nome'>;
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
