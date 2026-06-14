export interface InstrumentoAvaliativo {
    iav_id: number;
    iav_nome: string;
    iav_fl_ativo: boolean;
    iav_created_at: string | null;
    iav_updated_at: string | null;
}

export interface InstrumentoAvaliativoFormData {
    iav_nome: string;
    iav_fl_ativo: boolean;
    _method?: 'put';
    [key: string]: unknown;
}
