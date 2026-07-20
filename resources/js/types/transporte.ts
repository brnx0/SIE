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
