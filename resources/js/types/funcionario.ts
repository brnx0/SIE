import type { Municipio, Sexo, CorRaca } from '@/types/aluno';

export interface Cargo {
    crg_id: number;
    crg_nome: string;
    crg_descricao: string | null;
    crg_fl_ativo: boolean;
}

export interface Escola {
    esc_id: number;
    esc_nome: string;
}

export interface FuncionarioLotacao {
    lot_id: number;
    lot_adm_id: number;
    lot_esc_id: number;
    lot_crg_id: number;
    lot_vinculo: string;
    lot_situacao_funcional: string | null;
    lot_criterio_acesso: string | null;
    lot_dt_inicio: string;
    lot_dt_fim: string | null;
    lot_fl_ativo: boolean;
    lot_funcoes_sala_aula: string[] | null;
    escola?: Escola | null;
    cargo?: Cargo | null;
}

export interface FuncionarioAdmissao {
    adm_id: number;
    adm_fun_id: number;
    adm_matricula: string;
    adm_dt_admissao: string;
    adm_crg_id: number;
    adm_escolaridade_admissao: number | null;
    cargo?: Cargo | null;
    lotacoes?: FuncionarioLotacao[];
}

export interface AdmissaoFormData {
    adm_matricula: string;
    adm_dt_admissao: string;
    adm_crg_id: number | null;
    adm_escolaridade_admissao: number | '' | null;
}

export interface LotacaoFormData {
    lot_adm_id: number | null;
    lot_esc_id: number | null;
    lot_crg_id: number | null;
    lot_vinculo: string;
    lot_situacao_funcional: string;
    lot_criterio_acesso: string;
    lot_dt_inicio: string;
    lot_dt_fim: string;
    lot_fl_ativo: boolean;
    lot_funcoes_sala_aula: string[];
}

export interface Funcionario {
    fun_id: number;
    fun_nome: string;
    fun_nome_social: string | null;
    fun_dt_nascimento: string;
    fun_sexo: Sexo;
    fun_cor_raca: CorRaca;
    fun_nacionalidade: string;
    fun_pais_origem: string;
    fun_mun_id_nasc: number;
    fun_cpf: string;
    fun_religiao: string | null;
    fun_escolaridade: number | null;
    fun_estado_civil: number | null;
    fun_povo_indigena: string | null;
    fun_cd_censo: string | null;

    // Documentação
    fun_rg_numero: string | null;
    fun_rg_dt_emissao: string | null;
    fun_rg_uf: string | null;
    fun_rg_orgao_emissor: string | null;
    fun_certidao_modelo: string | null;
    fun_certidao_tipo: string | null;
    fun_certidao_dt_emissao: string | null;
    fun_certidao_numero: string | null;
    fun_certidao_livro: string | null;
    fun_certidao_pagina: string | null;
    fun_certidao_mun_id: number | null;
    fun_certidao_cartorio: string | null;
    fun_ctps_numero: string | null;
    fun_ctps_serie: string | null;
    fun_pis_pasep: string | null;
    fun_titulo_eleitor: string | null;
    fun_titulo_zona: string | null;
    fun_titulo_secao: string | null;
    fun_certificado_reservista: string | null;

    // Endereço
    fun_cep: string | null;
    fun_logradouro: string | null;
    fun_numero: string | null;
    fun_complemento: string | null;
    fun_bairro: string | null;
    fun_cidade: string | null;
    fun_uf: string | null;

    // Contato
    fun_telefone: string | null;
    fun_celular: string | null;
    fun_email: string | null;

    fun_fl_usa_transporte: boolean;
    fun_transporte_tipo: string | null;

    fun_foto: string | null;
    fun_foto_url: string | null;
    fun_fl_ativo: boolean;

    municipio_nascimento?: Municipio | null;
    municipio_certidao?: Municipio | null;
    admissoes?: FuncionarioAdmissao[];
}

export interface FuncionarioFormData {
    fun_nome: string;
    fun_nome_social: string;
    fun_dt_nascimento: string;
    fun_sexo: Sexo | '';
    fun_cor_raca: CorRaca | null;
    fun_nacionalidade: string;
    fun_pais_origem: string;
    fun_mun_id_nasc: number | null;
    fun_cpf: string;
    fun_religiao: string;
    fun_escolaridade: number | '' | null;
    fun_estado_civil: number | '' | null;
    fun_povo_indigena: string;
    fun_cd_censo: string;

    // Documentação
    fun_rg_numero: string;
    fun_rg_dt_emissao: string;
    fun_rg_uf: string;
    fun_rg_orgao_emissor: string;
    fun_certidao_modelo: string;
    fun_certidao_tipo: string;
    fun_certidao_dt_emissao: string;
    fun_certidao_numero: string;
    fun_certidao_livro: string;
    fun_certidao_pagina: string;
    fun_certidao_mun_id: number | null;
    fun_certidao_cartorio: string;
    fun_ctps_numero: string;
    fun_ctps_serie: string;
    fun_pis_pasep: string;
    fun_titulo_eleitor: string;
    fun_titulo_zona: string;
    fun_titulo_secao: string;
    fun_certificado_reservista: string;

    // Endereço
    fun_cep: string;
    fun_logradouro: string;
    fun_numero: string;
    fun_complemento: string;
    fun_bairro: string;
    fun_cidade: string;
    fun_uf: string;

    // Contato
    fun_telefone: string;
    fun_celular: string;
    fun_email: string;

    fun_fl_usa_transporte: boolean;
    fun_transporte_tipo: string;

    fun_foto: File | null;
    fun_fl_ativo: boolean;
    confirm_homonimo: boolean;
    _method?: 'put' | 'post';
    [key: string]: any;
}
