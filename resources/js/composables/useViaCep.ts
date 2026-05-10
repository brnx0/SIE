import { ref } from 'vue';

export interface ViaCepResult {
    logradouro: string;
    bairro: string;
    cidade: string;
    uf: string;
    ibge: string;
    ddd: string;
}

export function useViaCep() {
    const loading = ref(false);
    const error = ref<string | null>(null);

    async function lookup(cep: string): Promise<ViaCepResult | null> {
        const digits = cep.replace(/\D/g, '');
        if (digits.length !== 8) {
            return null;
        }

        loading.value = true;
        error.value = null;

        try {
            const res = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
            if (!res.ok) {
                error.value = 'Falha ao consultar CEP';
                return null;
            }
            const data = await res.json();
            if (data.erro) {
                error.value = 'CEP não encontrado';
                return null;
            }
            return {
                logradouro: data.logradouro ?? '',
                bairro: data.bairro ?? '',
                cidade: data.localidade ?? '',
                uf: data.uf ?? '',
                ibge: data.ibge ?? '',
                ddd: data.ddd ?? '',
            };
        } catch (e) {
            error.value = 'Erro de rede ao consultar CEP';
            return null;
        } finally {
            loading.value = false;
        }
    }

    return { loading, error, lookup };
}
