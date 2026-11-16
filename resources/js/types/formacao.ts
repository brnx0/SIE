export interface NivelGraduacao {
    ngr_id: number;
    ngr_nome: string;
    ngr_fl_ativo: boolean;
    cursos_count?: number;
}

export interface CursoFormacaoSuperiorCadastro {
    cfs_id: number;
    cfs_cd_area: string | null;
    cfs_area_detalhada: string | null;
    cfs_area_geral: string | null;
    cfs_codigo: string;
    cfs_nome: string;
    cfs_grau: string | null;
    cfs_ngr_id: number | null;
    cfs_fl_ativo: boolean;
    nivel?: Pick<NivelGraduacao, 'ngr_id' | 'ngr_nome'> | null;
}

export interface AreaInep {
    codigo: string;
    nome: string;
}
