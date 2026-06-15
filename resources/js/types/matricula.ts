import type { Municipio } from './aluno';

export interface TurmaMatricula {
    tur_id: number;
    tur_nome: string;
    tur_turno: string;
    tur_semestre: number;
    tur_situacao: string;
    tur_capacidade: number | null;
    total_matriculados: number;
    vagas_disponiveis: number | null;
    escola: { esc_id: number; esc_nome: string; esc_cd_escola: string } | null;
    serie: { ser_id: number; ser_nome: string; ser_idade: number; ser_fl_multi: boolean } | null;
    segmento: { seg_id: number; seg_nome_reduzido: string } | null;
    ano_letivo: { anl_id: number; anl_ano: number; anl_dt_corte: string } | null;
}

export interface AlunoResumo {
    aln_id: number;
    aln_nome: string;
    aln_dt_nascimento: string | null;
    aln_sexo: 'M' | 'F' | null;
    aln_cor_raca: number | null;
    aln_pais_origem: string | null;
    aln_mun_id_nasc: number | null;
    aln_nr_certidao: string | null;
    aln_nis: string | null;
    aln_filiacao_1: string | null;
    aln_filiacao_1_tipo: 'PAI' | 'MAE' | null;
    aln_filiacao_2: string | null;
    aln_filiacao_2_tipo: 'PAI' | 'MAE' | null;
    aln_cep: string | null;
    aln_logradouro: string | null;
    aln_numero: string | null;
    aln_complemento: string | null;
    aln_bairro: string | null;
    aln_cidade: string | null;
    aln_uf: string | null;
    aln_telefone: string | null;
    aln_email: string | null;
    aln_nr_matricula: number | null;
    aln_cpf: string | null;
    municipio_nascimento: Municipio | null;
    saude?: {
        als_tipo_sanguineo: string | null;
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
    } | null;
}

