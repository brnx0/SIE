export interface Municipio {
    mun_id: number;
    mun_nome: string;
    mun_uf: string;
    mun_codigo_ibge?: string;
}

export type TipoSanguineo = 'A+' | 'A-' | 'B+' | 'B-' | 'AB+' | 'AB-' | 'O+' | 'O-';

export interface AlunoSaude {
    als_id?: number;
    als_aln_id?: number;
    als_tipo_sanguineo: TipoSanguineo | '' | null;
    als_ds_alergias: string | null;
    als_fl_pcd: boolean;
    als_contato_emergencia: string | null;
    als_telefone_emergencia: string | null;
    als_plano_saude: string | null;
    als_cartao_sus: string | null;
    als_alergia_a: string | null;
    als_remedio_febre: string | null;
    als_remedio_cefaleia: string | null;
    als_patologias: string[] | null;
    als_outra_doenca: string | null;
    als_patologias_infancia: string[] | null;
    als_outra_doenca_infancia: string | null;
    als_deficiencias: string[] | null;
    als_transtornos_globais: string[] | null;
    als_transtornos_aprendizagem: string[] | null;
    als_deficiencia_outro: string | null;
    als_fl_altas_habilidades: boolean;
    als_cid: string | null;
    als_observacao: string | null;
    als_clinicas: string[] | null;
    als_recursos_inep: string[] | null;
}

export type Sexo = 'M' | 'F';
export type CorRaca = 0 | 1 | 2 | 3 | 4 | 5;

export interface Aluno {
    aln_id: number;
    aln_nome: string;
    aln_nome_social: string | null;
    aln_dt_nascimento: string;
    aln_sexo: Sexo;
    aln_cor_raca: CorRaca;
    aln_pais_origem: string;
    aln_mun_id_nasc: number;
    aln_cpf: string | null;
    aln_cd_inep: string | null;
    aln_nr_matricula: number | null;
    aln_nr_certidao: string | null;
    aln_filiacao_1: string | null;
    aln_filiacao_2: string | null;
    aln_cep: string | null;
    aln_logradouro: string | null;
    aln_numero: string | null;
    aln_complemento: string | null;
    aln_bairro: string | null;
    aln_cidade: string | null;
    aln_uf: string | null;
    aln_telefone: string | null;
    aln_email: string | null;
    aln_foto: string | null;
    aln_foto_url: string | null;
    aln_fl_ativo: boolean;
    municipio_nascimento?: Municipio | null;
    saude?: AlunoSaude | null;
}

export interface AlunoFormData {
    aln_nome: string;
    aln_nome_social: string;
    aln_dt_nascimento: string;
    aln_sexo: Sexo | '';
    aln_cor_raca: CorRaca | null;
    aln_pais_origem: string;
    aln_mun_id_nasc: number | null;
    aln_cpf: string;
    aln_cd_inep: string;
    aln_nr_matricula: number | string | null;
    aln_nr_certidao: string;
    aln_filiacao_1: string;
    aln_filiacao_2: string;
    aln_cep: string;
    aln_logradouro: string;
    aln_numero: string;
    aln_complemento: string;
    aln_bairro: string;
    aln_cidade: string;
    aln_uf: string;
    aln_telefone: string;
    aln_email: string;
    aln_foto: File | null;
    aln_fl_ativo: boolean;
    _method?: 'put' | 'post';
    saude: {
        als_tipo_sanguineo: TipoSanguineo | '';
        als_ds_alergias: string;
        als_fl_pcd: boolean;
        als_contato_emergencia: string;
        als_telefone_emergencia: string;
        als_plano_saude: string;
        als_cartao_sus: string;
        als_alergia_a: string;
        als_remedio_febre: string;
        als_remedio_cefaleia: string;
        als_patologias: string[];
        als_outra_doenca: string;
        als_patologias_infancia: string[];
        als_outra_doenca_infancia: string;
        als_deficiencias: string[];
        als_transtornos_globais: string[];
        als_transtornos_aprendizagem: string[];
        als_deficiencia_outro: string;
        als_fl_altas_habilidades: boolean;
        als_cid: string;
        als_observacao: string;
        als_clinicas: string[];
        als_recursos_inep: string[];
    };
    [key: string]: any;
}
