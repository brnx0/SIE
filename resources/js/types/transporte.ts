import type { Municipio } from '@/types/aluno';
import type { Bairro } from '@/types/escola';

export interface Veiculo {
    vei_id: number;
    vei_placa: string;
    vei_renavam: string | null;
    vei_tipo: string;
    vei_capacidade: number;
    vei_marca: string | null;
    vei_modelo: string | null;
    vei_ano_fabricacao: number | null;
    vei_fl_acessivel: boolean;
    vei_fl_terceirizado: boolean;
    vei_dt_licenciamento: string | null;
    vei_dt_seguro: string | null;
    vei_dt_vistoria: string | null;
    vei_obs: string | null;
    vei_fl_ativo: boolean;
}

export interface FuncionarioVinculo {
    fun_id: number;
    fun_nome: string;
    fun_cpf: string | null;
}

export interface Motorista {
    mot_id: number;
    mot_nome: string;
    mot_cpf: string | null;
    mot_dt_nascimento: string | null;
    mot_telefone: string | null;
    mot_fun_id: number | null;
    mot_cnh_numero: string;
    mot_cnh_categoria: string;
    mot_cnh_dt_validade: string;
    mot_fl_ativo: boolean;
    funcionario?: FuncionarioVinculo | null;
}

export interface MonitorTransporte {
    tmo_id: number;
    tmo_nome: string;
    tmo_cpf: string | null;
    tmo_dt_nascimento: string | null;
    tmo_telefone: string | null;
    tmo_fun_id: number | null;
    tmo_fl_ativo: boolean;
    funcionario?: FuncionarioVinculo | null;
}

export interface InstituicaoTransporte {
    tin_id: number;
    tin_nome: string;
    tin_esc_id: number | null;
    tin_esfera: string;
    tin_endereco: string | null;
    tin_bai_id: number | null;
    tin_mun_id: number | null;
    tin_telefone: string | null;
    tin_latitude: number | null;
    tin_longitude: number | null;
    tin_fl_ativo: boolean;
    escola?: { esc_id: number; esc_nome: string } | null;
    bairro?: Bairro | null;
    municipio?: Municipio | null;
}

export interface InstituicaoResumo {
    tin_id: number;
    tin_nome: string;
    tin_esfera: string;
}

export interface UsuarioTransporte {
    tue_id: number;
    tue_nome: string;
    tue_cpf: string | null;
    tue_dt_nascimento: string | null;
    tue_telefone: string | null;
    tue_tin_id: number | null;
    tue_justificativa: string;
    tue_fl_ativo: boolean;
    instituicao?: { tin_id: number; tin_nome: string } | null;
}

// ── Rotas ──────────────────────────────────────────────────────────────────
export const ROTA_TURNOS: Record<string, string> = {
    MATUTINO:   'Matutino',
    VESPERTINO: 'Vespertino',
    NOTURNO:    'Noturno',
    INTEGRAL:   'Integral',
};

export const ROTA_ZONAS: Record<string, string> = {
    urbana: 'Urbana',
    rural:  'Rural',
};

export interface VeiculoResumo {
    vei_id: number;
    vei_placa: string;
    vei_tipo: string;
    vei_capacidade: number;
    vei_fl_ativo?: boolean;
}

export interface MotoristaResumo {
    mot_id: number;
    mot_nome: string;
    mot_cnh_categoria: string;
    mot_cnh_dt_validade: string;
    mot_fl_ativo?: boolean;
}

export interface MonitorResumo {
    tmo_id: number;
    tmo_nome: string;
    tmo_fl_ativo?: boolean;
}

export interface Rota {
    rot_id: number;
    rot_anl_id: number;
    rot_nome: string;
    rot_turno: string;
    rot_zona: string | null;
    rot_vei_id: number;
    rot_mot_id: number;
    rot_tmo_id: number | null;
    rot_km: number | null;
    rot_tempo_min: number | null;
    rot_obs: string | null;
    rot_fl_ativo: boolean;
    anoLetivo?: { anl_id: number; anl_ano: number } | null;
    veiculo?: VeiculoResumo | null;
    motorista?: MotoristaResumo | null;
    monitor?: MonitorResumo | null;
    passageiros_count?: number;
}

export interface PontoRota {
    pto_id: number;
    pto_rot_id: number;
    pto_ordem: number;
    pto_nome: string;
    pto_endereco: string | null;
    pto_bai_id: number | null;
    pto_latitude: number | null;
    pto_longitude: number | null;
    pto_hr_prevista: string | null;
    pto_tin_id: number | null;
    bairro?: { bai_id: number; bai_nome: string; bai_mun_id: number } | null;
    instituicao?: { tin_id: number; tin_nome: string } | null;
    total_passageiros?: number;
}

export interface PassageiroRota {
    pas_id: number;
    pas_rot_id: number;
    pas_pto_id: number;
    pas_aln_id: number | null;
    pas_tue_id: number | null;
    pas_dt_inicio: string | null;
    pas_dt_fim: string | null;
    pas_obs: string | null;
    ponto?: { pto_id: number; pto_nome: string; pto_ordem: number } | null;
    aluno?: {
        aln_id: number;
        aln_nome: string;
        aln_nr_matricula: number | null;
        turma: string | null;
        escola: string | null;
    } | null;
    usuario_externo?: { tue_id: number; tue_nome: string } | null;
}

export interface RotaResumo {
    rot_id: number;
    rot_nome: string;
    rot_turno: string;
    rot_anl_id?: number;
    rot_fl_ativo?: boolean;
}

// ── Diário de Bordo ─────────────────────────────────────────────────────────
export const OCORRENCIA_TIPOS: Record<string, string> = {
    atraso:          'Atraso',
    pane:            'Pane/Avaria',
    acidente:        'Acidente',
    indisciplina:    'Indisciplina',
    via_interditada: 'Via interditada',
    outro:           'Outro',
};

export interface DiarioBordoLista {
    tdb_id: number;
    tdb_rot_id: number;
    tdb_dt_viagem: string;
    tdb_km_inicial: number | null;
    tdb_km_final: number | null;
    passageiros_count: number;
    embarcados_count: number;
    ocorrencias_count: number;
    rota?: { rot_id: number; rot_nome: string; rot_turno: string } | null;
    veiculo?: { vei_id: number; vei_placa: string; vei_capacidade: number } | null;
    motorista?: { mot_id: number; mot_nome: string } | null;
}

export interface DiarioBordo {
    tdb_id: number;
    tdb_rot_id: number;
    tdb_dt_viagem: string;
    tdb_vei_id: number;
    tdb_mot_id: number;
    tdb_tmo_id: number | null;
    tdb_hr_saida: string | null;
    tdb_hr_chegada: string | null;
    tdb_km_inicial: number | null;
    tdb_km_final: number | null;
    tdb_obs: string | null;
    rota?: { rot_id: number; rot_nome: string; rot_turno: string; anl_ano: number | null } | null;
    veiculo?: { vei_id: number; vei_placa: string; vei_capacidade: number } | null;
    motorista?: { mot_id: number; mot_nome: string } | null;
    monitor?: { tmo_id: number; tmo_nome: string } | null;
}

export interface ChamadaItem {
    tdp_id: number;
    tdp_pas_id: number;
    tdp_fl_embarcou: boolean;
    tdp_fl_desembarcou: boolean;
    tdp_hr_embarque: string | null;
    tdp_hr_desembarque: string | null;
    tdp_obs: string | null;
    nome: string;
    tipo: 'aluno' | 'externo';
    ponto?: { pto_id: number; pto_nome: string; pto_ordem: number } | null;
}

export interface OcorrenciaItem {
    toc_id: number;
    toc_tipo: string;
    toc_hr: string | null;
    toc_descricao: string;
}
