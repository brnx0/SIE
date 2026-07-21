/** Registro de um catálogo simples (nome + situação). Colunas variam por prefixo. */
export interface Anexo {
    anx_id: number;
    anx_nome: string;
    anx_mime: string;
    anx_tamanho: number;
    eh_imagem: boolean;
    usuario: string | null;
    criado_em: string | null;
}

export interface CatalogoRegistro {
    [coluna: string]: string | number | boolean | null;
}

/** Config que o backend manda para a tela genérica de catálogo saber o que renderizar. */
export interface CatalogoConfig {
    rota: string;
    singular: string;
    plural: string;
    artigo: string;
    descricao: string;
    col_id: string;
    col_nome: string;
    col_ativo: string;
    col_ordem?: string | null;
    sem_acento?: boolean;
    tem_ordem?: boolean;
}

export interface UnidadeMedida {
    umd_id: number;
    umd_sigla: string;
    umd_nome: string;
    umd_fl_fracionavel: boolean;
    umd_fl_ativo: boolean;
}

export interface TipoItem {
    tpi_id: number;
    tpi_nome: string;
    tpi_fl_ativo?: boolean;
}

export interface CategoriaAlimento {
    cat_id: number;
    cat_nome: string;
    cat_fl_ativo?: boolean;
}

export interface Item {
    itm_id: number;
    itm_tpi_id: number;
    itm_cat_id: number;
    itm_umd_id: number;
    itm_cd_item: string | null;
    itm_ncm: string | null;
    itm_nome: string;
    itm_especificacao: string | null;
    itm_fl_perecivel: boolean;
    itm_fl_agricultura_familiar: boolean;
    itm_validade_dias: number | null;
    itm_estoque_minimo: string | number | null;
    itm_fl_ativo: boolean;
    tipoItem?: TipoItem;
    categoria?: CategoriaAlimento;
    unidadeMedida?: UnidadeMedida;
}

export interface PerguntaOpcaoLinha {
    pro_id?: number;
    pro_texto: string;
    pro_peso: number | null;
}

export interface PerguntaLinha {
    prg_id: number;
    prg_tpq_id: number;
    prg_ordem: number;
    prg_texto: string;
    prg_tipo_resposta: string;
    tipo_label: string;
    prg_fl_obrigatoria: boolean;
    prg_fl_ativo: boolean;
    questionario_nome: string | null;
    opcoes: PerguntaOpcaoLinha[];
    /** Já respondida em alguma visita: não pode ser excluída nem trocar de tipo. */
    usada: boolean;
}

export interface VisitaLinha {
    vis_id: number;
    vis_dt_programada: string | null;
    vis_dt_realizada: string | null;
    vis_dt_ateste: string | null;
    vis_situacao: string;
    escola_nome: string | null;
    tipo_visita: string | null;
    tecnico_nome: string | null;
    atestador_nome: string | null;
    respostas_count: number;
    respondidas: number;
}

export interface VisitaCabecalho {
    vis_id: number;
    vis_dt_programada: string | null;
    vis_dt_realizada: string | null;
    vis_dt_ateste: string | null;
    vis_situacao: string;
    vis_parecer: string | null;
    vis_obs: string | null;
    vis_motivo_cancelamento: string | null;
    vis_fun_id: number | null;
    escola_nome: string | null;
    tipo_visita: string | null;
    questionario_nome: string | null;
    tecnico_nome: string | null;
    atestador_nome: string | null;
}

/** Resposta com pergunta congelada no momento da aplicação. */
export interface RespostaLinha {
    vre_id: number;
    pergunta: string;
    tipo: string;
    obrigatoria: boolean;
    resposta: string | null;
    pro_id: number | null;
    obs: string | null;
    respondida: boolean;
    texto_resposta: string;
    opcoes: { pro_id: number; pro_texto: string }[];
}

export interface PreparacaoLinha {
    prd_id: number;
    prd_dt: string | null;
    prd_turno: string;
    prd_refeicao: string;
    prd_qtd_servida: number;
    prd_situacao: string;
    prato_nome: string | null;
    escola_nome: string | null;
    itens_count: number;
}

export interface PreparacaoCabecalho {
    prd_id: number;
    prd_dt: string | null;
    prd_turno: string;
    prd_refeicao: string;
    prd_qtd_servida: number;
    prd_situacao: string;
    prd_obs: string | null;
    prato_nome: string | null;
    escola_nome: string | null;
}

export interface PreparacaoItemLinha {
    pdi_id: number;
    item_nome: string | null;
    umd_sigla: string | null;
    qtd_prevista: number;
    qtd_utilizada: number;
    /** Utilizado − previsto. Positivo = gastou além da ficha técnica. */
    desvio: number;
}

/** O que o cardápio manda servir na data escolhida. */
export interface SugestaoCardapio {
    prt_id: number;
    prato: string | null;
    turno: string;
    refeicao: string;
}

export interface DemandaLinha {
    dmd_id: number;
    dmd_mes: number;
    dmd_ano: number;
    mes_nome: string;
    dmd_situacao: string;
    dmd_dias_letivos: number;
    escola_nome: string | null;
    ano_letivo: number | null;
    itens_count: number;
    ajustados: number;
}

export interface DemandaCabecalho {
    dmd_id: number;
    dmd_mes: number;
    dmd_ano: number;
    mes_nome: string;
    dmd_situacao: string;
    dmd_dias_letivos: number;
    dmd_obs: string | null;
    escola_nome: string | null;
    ano_letivo: number | null;
    cardapio_nome: string | null;
    cardapio_da_rede: boolean | null;
}

export interface DemandaItemLinha {
    dmi_id: number;
    item_nome: string | null;
    item_codigo: string | null;
    umd_sigla: string | null;
    qtd_calculada: number;
    qtd_ajustada: number | null;
    /** O número que vale: ajustado quando existe, senão o calculado. */
    qtd_final: number;
    justificativa: string | null;
    ajustada: boolean;
}

/** Linha da consulta de estoque: saldo por item e lote. */
export interface SaldoLinha {
    sld_id: number;
    item_nome: string | null;
    item_codigo: string | null;
    categoria: string | null;
    umd_sigla: string | null;
    lote_codigo: string | null;
    lote_validade: string | null;
    marca: string | null;
    escola_nome: string | null;
    qtd: number;
    custo_medio: number;
    valor_total: number;
    dias_vencer: number | null;
    vencido: boolean;
    estoque_minimo: number | null;
}

/** Linha do razão de movimentação. */
export interface MovimentoLinha {
    mov_id: number;
    mov_dt: string | null;
    mov_tipo: string;
    escola_nome: string | null;
    item_nome: string | null;
    lote_codigo: string | null;
    lote_validade: string | null;
    qtd: number;
    umd_sigla: string | null;
    qtd_base: number;
    umd_base: string | null;
    vl_unitario: number | null;
    vl_total: number | null;
    fornecedor_nome: string | null;
    tipo_retirada: string | null;
    usuario_nome: string | null;
    mov_obs: string | null;
    /** +1 entra no saldo, -1 sai. */
    sinal: number;
    transferencia: boolean;
    /** Agrupador da transferência; quando presente, há guia de remessa em PDF. */
    transferencia_id: string | null;
    estorno_de: number | null;
}

/** Item com saldo, oferecido na transferência e na retirada. */
export interface ItemComSaldo {
    itm_id: number;
    itm_nome: string | null;
    itm_umd_id: number;
    umd_sigla: string | null;
    saldo: number;
}

export interface RecebimentoLinha {
    rcb_id: number;
    rcb_dt: string | null;
    rcb_tipo: string;
    rcb_situacao: string;
    rcb_nr_nota: string | null;
    ordem_numero: string | null;
    fornecedor_nome: string | null;
    itens_count: number;
    conferidos: number;
}

export interface RecebimentoItemLinha {
    rci_id: number;
    item_nome: string | null;
    item_perecivel: boolean;
    validade_dias: number | null;
    umd_sigla: string | null;
    qtd_prevista: number;
    qtd_recebida: number;
    qtd_rejeitada: number;
    motivo_rejeicao: string | null;
    lot_id: number | null;
    lote_codigo: string | null;
    lote_validade: string | null;
    lote_mrc_id: number | null;
    vl_unitario: number | null;
}

export interface Contrato {
    ctr_id: number;
    ctr_frn_id: number;
    ctr_nr_contrato: string;
    ctr_ano: number;
    ctr_objeto: string;
    ctr_modalidade: string | null;
    ctr_dt_assinatura: string | null;
    ctr_dt_inicio: string;
    ctr_dt_fim: string;
    ctr_vl_total: string | number;
    ctr_situacao: string;
    fornecedor?: { frn_id: number; frn_razao_social: string; frn_cnpj: string | null; frn_cpf: string | null } | null;
    itens_count?: number;
    aditivos_count?: number;
    ordens_count?: number;
}

/** Item contratado, com o saldo calculado (não é coluna). */
export interface ContratoItemLinha {
    cti_id: number;
    cti_itm_id: number;
    cti_umd_id: number;
    cti_qtd: number;
    cti_vl_unitario: number;
    item_nome: string | null;
    umd_sigla: string | null;
    qtd_pedida: number;
    qtd_saldo: number;
    vl_total: number;
}

/** Log de alteração do contrato: guarda o de/para. */
export interface AditivoLinha {
    adt_id: number;
    adt_nr_aditivo: string;
    adt_tipo: 'VALOR' | 'PRAZO' | 'OBJETO' | 'QTD';
    adt_dt_assinatura: string | null;
    adt_vl_anterior: number | null;
    adt_vl_novo: number | null;
    adt_dt_fim_anterior: string | null;
    adt_dt_fim_nova: string | null;
    adt_objeto_anterior: string | null;
    adt_objeto_novo: string | null;
    adt_qtd_anterior: number | null;
    adt_qtd_nova: number | null;
    adt_justificativa: string | null;
    item_nome: string | null;
    usuario_nome: string | null;
    /** Cancelado: o contrato voltou às condições anteriores a este aditivo. */
    adt_fl_cancelado: boolean;
    adt_dt_cancelamento: string | null;
    adt_justificativa_cancelamento: string | null;
    usuario_cancelamento_nome: string | null;
    /** Só o último válido de cada tipo pode ser cancelado. */
    pode_cancelar: boolean;
}

export interface EmpenhoLinha {
    emp_id: number;
    emp_nr_empenho: string;
    emp_dt: string | null;
    emp_vl_empenhado: number;
    emp_situacao: string;
    contrato_numero: string | null;
    contrato_ano: number | null;
    fornecedor_nome: string | null;
    vl_consumido: number;
    vl_saldo: number;
}

export interface Empenho {
    emp_id: number;
    emp_ctr_id: number;
    emp_nr_empenho: string;
    emp_dt: string;
    emp_vl_empenhado: string | number;
    emp_situacao: string;
    emp_obs: string | null;
    contrato?: Contrato | null;
}

export interface OrdemLinha {
    ofn_id: number;
    ofn_nr_ordem: string;
    ofn_dt_emissao: string | null;
    ofn_dt_entrega_prevista: string | null;
    ofn_situacao: string;
    contrato_numero: string | null;
    contrato_ano: number | null;
    fornecedor_nome: string | null;
    empenho_numero: string | null;
    itens_count: number;
    vl_total: number;
}

export interface OrdemFornecimento {
    ofn_id: number;
    ofn_ctr_id: number;
    ofn_emp_id: number | null;
    ofn_nr_ordem: string;
    ofn_dt_emissao: string;
    ofn_dt_entrega_prevista: string | null;
    /** Derivada do recebimento: EMITIDA → PARCIAL → ENTREGUE. CANCELADA é ato de gente. */
    ofn_situacao: string;
    ofn_obs: string | null;
    ofn_motivo_cancelamento: string | null;
    contrato?: Contrato | null;
    empenho?: Empenho | null;
}

export interface OrdemItemLinha {
    ofi_id: number;
    ofi_itm_id: number;
    ofi_qtd: number;
    ofi_vl_unitario: number;
    ofi_qtd_entregue: number;
    item_nome: string | null;
    umd_sigla: string | null;
    vl_total: number;
}

/** Item do contrato oferecido na ordem, já com o saldo disponível. */
export interface ItemContratoDisponivel {
    cti_id: number;
    cti_itm_id: number;
    item_nome: string | null;
    umd_sigla: string | null;
    cti_qtd: number;
    cti_vl_unitario: number;
    qtd_saldo: number;
}

export interface Cardapio {
    crd_id: number;
    /** Nulo = cardápio padrão da rede (vale para escola sem cardápio próprio). */
    crd_esc_id: number | null;
    crd_anl_id: number;
    crd_nome: string;
    crd_dt_inicio: string;
    crd_dt_fim: string;
    crd_fl_ativo: boolean;
    escola?: { esc_id: number; esc_nome: string } | null;
    anoLetivo?: { anl_id: number; anl_ano: number } | null;
    dias_count?: number;
}

/** Slot da grade: semana × dia × turno × refeição → prato. */
export interface CardapioDiaLinha {
    cdi_id: number;
    cdi_prt_id: number;
    cdi_semana: number;
    cdi_dia_semana: number;
    cdi_turno: string;
    cdi_refeicao: string;
    prato_nome: string | null;
}

export interface Prato {
    prt_id: number;
    prt_nome: string;
    prt_descricao: string | null;
    prt_modo_preparo: string | null;
    prt_fl_ativo: boolean;
    itens_count?: number;
    /** Nomes dos itens que compõem o prato (listagem). */
    composicao?: string[];
    tem_nutricional?: boolean;
    /** Tabela nutricional da porção — toda opcional. */
    prt_nut_porcao?: string | number | null;
    prt_nut_porcao_umd_id?: number | null;
    prt_nut_energia_kcal?: string | number | null;
    prt_nut_carboidrato_g?: string | number | null;
    prt_nut_acucares_g?: string | number | null;
    prt_nut_proteina_g?: string | number | null;
    prt_nut_gordura_total_g?: string | number | null;
    prt_nut_gordura_saturada_g?: string | number | null;
    prt_nut_gordura_trans_g?: string | number | null;
    prt_nut_fibra_g?: string | number | null;
    prt_nut_sodio_mg?: string | number | null;
    prt_nut_observacao?: string | null;
    porcaoUnidadeMedida?: { umd_id: number; umd_sigla: string } | null;
}

/** Linha da ficha técnica: item + quantidade por aluno. */
export interface PratoItemLinha {
    pri_id: number;
    pri_itm_id: number;
    pri_umd_id: number;
    pri_qtd_per_capita: number;
    item_nome: string | null;
    umd_sigla: string | null;
    item_umd_sigla: string | null;
}

export interface Fornecedor {
    frn_id: number;
    frn_mun_id: number | null;
    frn_bai_id: number | null;
    frn_cnpj: string | null;
    frn_cpf: string | null;
    frn_razao_social: string;
    frn_nome_fantasia: string | null;
    frn_fl_agricultura_familiar: boolean;
    frn_email: string | null;
    frn_fone: string | null;
    frn_cep: string | null;
    frn_logradouro: string | null;
    frn_numero: string | null;
    frn_complemento: string | null;
    frn_fl_ativo: boolean;
    municipio?: { mun_id: number; mun_nome: string; mun_uf: string } | null;
    bairro?: { bai_id: number; bai_nome: string } | null;
}

/** Conversão de embalagem para a unidade base do item (ex.: 1 CX = 30 KG). */
export interface ItemConversaoLinha {
    cvu_id: number;
    cvu_umd_origem_id: number;
    cvu_fator: number;
    cvu_fl_ativo: boolean;
    origem_sigla: string | null;
    origem_nome: string | null;
}

export interface ConversaoUnidade {
    cvu_id: number;
    cvu_itm_id: number | null;
    cvu_umd_origem_id: number;
    cvu_umd_destino_id: number;
    cvu_fator: string | number;
    cvu_fl_ativo: boolean;
    unidadeOrigem?: UnidadeMedida;
    unidadeDestino?: UnidadeMedida;
}
