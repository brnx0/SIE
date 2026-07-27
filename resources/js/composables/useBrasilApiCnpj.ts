import { ref } from 'vue';

/**
 * Consulta cadastral de CNPJ na BrasilAPI — mesma pegada do useViaCep:
 * pública, sem chave, CORS liberado, chamada direto do browser (o limite de
 * requisição fica no IP do usuário, não no do servidor).
 *
 * A BrasilAPI é uma casca sobre a Minha Receita (mesmo payload), que por sua
 * vez lê os Dados Abertos da Receita Federal. Duas consequências:
 *  - o dado tem o atraso da carga da Receita (mensal), então não é fonte de
 *    verdade para nada crítico — serve para poupar digitação;
 *  - se a BrasilAPI sair do ar, a Minha Receita é open source e self-hostável.
 *
 * Só é devolvido o que o cadastro de fornecedor usa. A resposta traz também o
 * quadro societário (nome e CPF parcial dos sócios) — dado pessoal que este
 * cadastro não precisa e, por isso, não é lido nem gravado.
 */
export interface CnpjResult {
    razaoSocial: string;
    nomeFantasia: string;
    logradouro: string;
    numero: string;
    complemento: string;
    bairro: string;
    cep: string;
    ibge: string;
    telefone: string;
    email: string;
    /** 'ATIVA', 'BAIXADA', 'SUSPENSA', 'INAPTA'... */
    situacao: string;
}

export function useBrasilApiCnpj() {
    const loading = ref(false);
    const error = ref<string | null>(null);

    async function lookup(cnpj: string): Promise<CnpjResult | null> {
        // O CNPJ pode ser alfanumérico (jul/2026): preserva [0-9A-Z].
        const limpo = (cnpj ?? '').toUpperCase().replace(/[^0-9A-Z]/g, '');
        if (limpo.length !== 14) {
            return null;
        }

        loading.value = true;
        error.value = null;

        try {
            const res = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${limpo}`);

            if (res.status === 404) {
                error.value = 'CNPJ não encontrado na Receita Federal';
                return null;
            }

            if (!res.ok) {
                error.value = 'Falha ao consultar CNPJ';
                return null;
            }

            const d = await res.json();

            return {
                razaoSocial: d.razao_social ?? '',
                nomeFantasia: d.nome_fantasia ?? '',
                logradouro: d.logradouro ?? '',
                numero: d.numero ?? '',
                complemento: d.complemento ?? '',
                bairro: d.bairro ?? '',
                cep: (d.cep ?? '').replace(/\D/g, ''),
                ibge: d.codigo_municipio_ibge ? String(d.codigo_municipio_ibge) : '',
                telefone: (d.ddd_telefone_1 ?? '').replace(/\D/g, ''),
                email: d.email ?? '',
                situacao: d.descricao_situacao_cadastral ?? '',
            };
        } catch {
            error.value = 'Erro de rede ao consultar CNPJ';
            return null;
        } finally {
            loading.value = false;
        }
    }

    return { loading, error, lookup };
}
