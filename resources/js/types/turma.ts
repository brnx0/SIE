export type TurnoTipo = 'INTEGRAL' | 'MATUTINO' | 'NOTURNO' | 'VESPERTINO';

export const TURNOS: Record<TurnoTipo, string> = {
    INTEGRAL:   'Integral',
    MATUTINO:   'Matutino',
    NOTURNO:    'Noturno',
    VESPERTINO: 'Vespertino',
};

export const TIPOS_ATENDIMENTO = [
    'NÃO SE APLICA',
    'CLASSE HOSPITALAR',
    'UNIDADE DE INTERNAÇÃO',
    'UNIDADE PRISIONAL',
    'ATIVIDADE COMPLEMENTAR',
    'ATENDIMENTO EDUCACIONAL ESPECIALIZADO - AEE',
] as const;

export const TIPOS_MEDIACAO = [
    'PRESENCIAL',
    'SEMIPRESENCIAL',
    'EDUCAÇÃO A DISTÂNCIA',
] as const;

export const LOCAIS_DIFERENCIADOS = [
    'NAO ESTA EM LOCAL DIFERENCIADO',
    'SALA ANEXA',
    'UNIDADE DE ATENDIMENTO SOCIOEDUCATIVA',
    'UNIDADE PRISIONAL',
] as const;

export const SITUACOES_TURMA = ['ABERTA', 'ENCERRADA'] as const;

export const DIAS_SEMANA = [
    { value: 'dom', label: 'Domingo' },
    { value: 'seg', label: 'Segunda' },
    { value: 'ter', label: 'Terça' },
    { value: 'qua', label: 'Quarta' },
    { value: 'qui', label: 'Quinta' },
    { value: 'sex', label: 'Sexta' },
    { value: 'sab', label: 'Sábado' },
] as const;

export type DiaSemana = 'dom' | 'seg' | 'ter' | 'qua' | 'qui' | 'sex' | 'sab';

export interface TurmaHorario {
    trh_id: number;
    trh_tur_id: number;
    trh_grh_id: number;
    trh_dia: string;
    trh_fun_id: number;
    trh_dis_id: number;
    trh_fl_tc: boolean;
    gradeHorario?: { grh_id: number; grh_hora: string; grh_ordem: number } | null;
    funcionario?: { fun_id: number; fun_nome: string } | null;
    disciplina?: { dis_id: number; dis_nome: string } | null;
}

export interface GradeHorarioResumo {
    grh_id: number;
    grh_turno?: 'm' | 't' | 'n';
    grh_hora: string;
    grh_ordem: number;
}

export interface TurmaProfessor {
    tup_id: number;
    tup_tur_id: number;
    tup_fun_id: number;
    tup_dis_id: number;
    funcionario?: { fun_id: number; fun_nome: string } | null;
    disciplina?: { dis_id: number; dis_nome: string } | null;
}

export interface ProfessorResumo {
    fun_id: number;
    fun_nome: string;
}

export interface DisciplinaResumo {
    dis_id: number;
    dis_nome: string;
}

export interface Turma {
    tur_id: number;
    tur_esc_id: number;
    tur_anl_id: number;
    tur_seg_id: number;
    tur_ser_id: number;
    tur_cd_inep: string | null;
    tur_nome: string;
    tur_turno: TurnoTipo;
    tur_capacidade: number | null;
    tur_tipo_atendimento: string;
    tur_situacao: string;
    tur_hora_inicio: string | null;
    tur_hora_fim: string | null;
    tur_mediacao: string | null;
    tur_local_diferenciado: string | null;
    tur_fl_especial: boolean;
    tur_dias_funcionamento: DiaSemana[] | null;
    tur_obs: string | null;
    tur_created_at: string | null;
    tur_updated_at: string | null;
    escola?: { esc_id: number; esc_nome: string } | null;
    anoLetivo?: { anl_id: number; anl_ano: number } | null;
    segmento?: { seg_id: number; seg_nome_reduzido: string } | null;
    serie?: { ser_id: number; ser_nome: string } | null;
    professores?: TurmaProfessor[];
    horarios?: TurmaHorario[];
}

export interface TurmaFormData {
    tur_esc_id: number | null;
    tur_anl_id: number | null;
    tur_seg_id: number | null;
    tur_ser_id: number | null;
    tur_cd_inep: string;
    tur_nome: string;
    tur_turno: TurnoTipo | '';
    tur_capacidade: number | '';
    tur_tipo_atendimento: string;
    tur_situacao: string;
    tur_hora_inicio: string;
    tur_hora_fim: string;
    tur_mediacao: string;
    tur_local_diferenciado: string;
    tur_fl_especial: boolean;
    tur_dias_funcionamento: DiaSemana[];
    tur_obs: string;
    _method?: 'put';
    [key: string]: any;
}

export interface EscolaResumo {
    esc_id: number;
    esc_nome: string;
}

export interface SegmentoResumo {
    esg_id: number;
    seg_id: number;
    seg_nome: string;
    ser_id_inicio: number;
    ser_id_fim: number | null;
}

export interface SerieResumoTurma {
    ser_id: number;
    ser_nome: string;
}
