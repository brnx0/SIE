import type { Municipio } from '@/types/aluno';

export type Zona = 'U' | 'R';
export type LocalizacaoDif = 0 | 1 | 2 | 3 | 4 | 5 | 6 | 7;
export type DepAdministrativa = 1 | 2 | 3 | 4;
export type ProprietarioImovel = 1 | 2 | 3 | 4;
export type FormaOcupacao = 1 | 2 | 3 | 4;
export type SituacaoFunc = 1 | 2 | 3;

export interface Bairro {
    bai_id: number;
    bai_nome: string;
    bai_mun_id?: number;
}

export interface GerenciaRegional {
    ger_id: number;
    ger_nome: string;
    ger_sigla?: string | null;
    ger_uf?: string | null;
}

export interface Escola {
    esc_id: number;
    esc_cd_inep: string;
    esc_cnpj: string;
    esc_nome: string;
    esc_apelido: string;
    esc_cd_escola: string | null;
    esc_logo: string | null;
    esc_logo_url: string | null;

    esc_cep: string;
    esc_logradouro: string;
    esc_numero: string;
    esc_complemento: string | null;
    esc_bai_id: number;
    esc_mun_id: number;
    esc_zona: Zona;
    esc_localizacao_dif: LocalizacaoDif;
    esc_latitude: string | number | null;
    esc_longitude: string | number | null;
    esc_caixa_postal: string | null;

    esc_ddd: string | null;
    esc_telefone_fixo: string | null;
    esc_fax: string | null;
    esc_telefone_2: string | null;
    esc_telefone_3: string | null;
    esc_email: string;
    esc_site: string | null;

    esc_dep_administrativa: DepAdministrativa;
    esc_proprietario_imovel: ProprietarioImovel | null;
    esc_forma_ocupacao: FormaOcupacao | null;
    esc_situacao_func: SituacaoFunc;
    esc_regulamentada_conselho: boolean | null;
    esc_turno_escolar: string | null;
    esc_ger_id: number;
    esc_orgao_regional_ensino: string | null;
    esc_fl_creche: boolean;
    esc_fl_predio_compartilhado: boolean;
    esc_fl_sorteio_vagas: boolean;
    esc_fl_ativo: boolean;

    municipio?: Municipio | null;
    bairro?: Bairro | null;
    gerencia?: GerenciaRegional | null;
}

export interface EscolaFormData {
    esc_cd_inep: string;
    esc_cnpj: string;
    esc_nome: string;
    esc_apelido: string;
    esc_cd_escola: string;

    esc_cep: string;
    esc_logradouro: string;
    esc_numero: string;
    esc_complemento: string;
    esc_bai_id: number | null;
    esc_mun_id: number | null;
    esc_zona: Zona | '';
    esc_localizacao_dif: LocalizacaoDif | null;
    esc_latitude: string;
    esc_longitude: string;
    esc_caixa_postal: string;

    esc_ddd: string;
    esc_telefone_fixo: string;
    esc_fax: string;
    esc_telefone_2: string;
    esc_telefone_3: string;
    esc_email: string;
    esc_site: string;

    esc_dep_administrativa: DepAdministrativa | '';
    esc_proprietario_imovel: ProprietarioImovel | '';
    esc_forma_ocupacao: FormaOcupacao | '';
    esc_situacao_func: SituacaoFunc | '';
    esc_regulamentada_conselho: boolean | null;
    esc_turno_escolar: string;
    esc_ger_id: number | null;
    esc_orgao_regional_ensino: string;
    esc_fl_creche: boolean;
    esc_fl_predio_compartilhado: boolean;
    esc_fl_sorteio_vagas: boolean;
    esc_fl_ativo: boolean;

    esc_logo: File | null;
    _method?: 'put' | 'post';
    [key: string]: unknown;
}
