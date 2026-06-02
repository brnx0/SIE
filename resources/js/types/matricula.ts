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
    serie: { ser_id: number; ser_nome: string; ser_idade: number } | null;
    segmento: { seg_id: number; seg_nome_reduzido: string } | null;
    ano_letivo: { anl_id: number; anl_ano: number; anl_dt_corte: string } | null;
}

export interface AlunoResumo {
    aln_id: number;
    aln_nome: string;
    aln_dt_nascimento: string | null;
    aln_nr_matricula: number | null;
    aln_cpf: string | null;
}

export type TipoAdmissao =
    | 'MATRICULA_NOVA'
    | 'REMATRICULA'
    | 'TRANSFERENCIA_INTERNA'
    | 'TRANSFERENCIA_EXTERNA';

export const TIPOS_ADMISSAO: { value: TipoAdmissao; label: string }[] = [
    { value: 'MATRICULA_NOVA',        label: 'Matrícula Nova' },
    { value: 'REMATRICULA',           label: 'Rematrícula' },
    { value: 'TRANSFERENCIA_INTERNA', label: 'Transferência Interna' },
    { value: 'TRANSFERENCIA_EXTERNA', label: 'Transferência Externa' },
];
