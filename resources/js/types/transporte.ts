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

/**
 * Instituição de ensino no transporte = escola já cadastrada (edu_escola).
 * Não há cadastro próprio; a seleção usa o EscolaCombobox (api/escolas).
 */
export interface EscolaResumo {
    esc_id: number;
    esc_nome: string;
}

export interface UsuarioTransporte {
    tue_id: number;
    tue_nome: string;
    tue_cpf: string | null;
    tue_dt_nascimento: string | null;
    tue_telefone: string | null;
    tue_esc_id: number | null;
    tue_justificativa: string;
    tue_fl_ativo: boolean;
    escola?: EscolaResumo | null;
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

/**
 * Motorista e monitor são funcionários da rede (edu_funcionario) lotados nos
 * cargos de CargoTransporte — não há cadastro próprio no módulo.
 */
export interface FuncionarioTransporte {
    fun_id: number;
    fun_nome: string;
    fun_cpf?: string | null;
    fun_cnh_categoria?: string | null;
    fun_cnh_dt_validade?: string | null;
    fun_fl_ativo?: boolean;
}

export interface Rota {
    rot_id: number;
    rot_anl_id: number;
    rot_nome: string;
    rot_turno: string;
    rot_zona: string | null;
    rot_vei_id: number;
    rot_fun_id_motorista: number;
    rot_fun_id_monitor: number | null;
    rot_km: number | null;
    rot_tempo_min: number | null;
    rot_trajeto: GeoJsonLineString | null;
    rot_obs: string | null;
    rot_fl_ativo: boolean;
    anoLetivo?: { anl_id: number; anl_ano: number } | null;
    veiculo?: VeiculoResumo | null;
    motorista?: FuncionarioTransporte | null;
    monitor?: FuncionarioTransporte | null;
    passageiros_count?: number;
}

export interface PontoRota {
    pto_id: number;
    pto_rot_id: number;
    pto_ordem: number;
    pto_nome: string;
    pto_cep: string | null;
    pto_endereco: string | null;
    pto_bai_id: number | null;
    pto_latitude: number | null;
    pto_longitude: number | null;
    pto_hr_prevista: string | null;
    pto_esc_id: number | null;
    bairro?: { bai_id: number; bai_nome: string; bai_mun_id: number } | null;
    escola?: EscolaResumo | null;
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
    tdb_fl_fechado: boolean;
    rota?: { rot_id: number; rot_nome: string; rot_turno: string } | null;
    veiculo?: { vei_id: number; vei_placa: string; vei_capacidade: number } | null;
    motorista?: { fun_id: number; fun_nome: string } | null;
}

export interface DiarioBordo {
    tdb_id: number;
    tdb_rot_id: number;
    tdb_dt_viagem: string;
    tdb_vei_id: number;
    tdb_fun_id_motorista: number;
    tdb_fun_id_monitor: number | null;
    tdb_hr_saida: string | null;
    tdb_hr_chegada: string | null;
    tdb_km_inicial: number | null;
    tdb_km_final: number | null;
    tdb_obs: string | null;
    tdb_fl_fechado: boolean;
    rota?: { rot_id: number; rot_nome: string; rot_turno: string; anl_ano: number | null } | null;
    veiculo?: { vei_id: number; vei_placa: string; vei_capacidade: number } | null;
    motorista?: { fun_id: number; fun_nome: string } | null;
    monitor?: { fun_id: number; fun_nome: string } | null;
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

// ── Simulador georreferenciado (Fase 5) ─────────────────────────────────────
export interface GeoJsonLineString {
    type: 'LineString';
    coordinates: [number, number][]; // [lon, lat]
}

export interface SimuladorPonto {
    pto_id: number | null; // null = ponto novo criado no mapa
    pto_ordem: number;
    pto_nome: string;
    pto_latitude: number | null;
    pto_longitude: number | null;
    escola?: EscolaResumo | null;
}

export interface EscolaGeo {
    esc_id: number;
    esc_nome: string;
    esc_latitude: number;
    esc_longitude: number;
}

export interface SimuladorRota {
    rot_id: number;
    rot_nome: string;
    rot_km: number | null;
    rot_tempo_min: number | null;
    rot_trajeto: GeoJsonLineString | null;
    capacidade: number | null;
    total_passageiros: number;
    pontos: SimuladorPonto[];
}
