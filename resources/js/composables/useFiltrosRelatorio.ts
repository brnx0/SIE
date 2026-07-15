/**
 * Mantém os filtros escolhidos num formulário de relatório.
 *
 * Problema: Form → `router.get('/relatorios/x/gerar')` → Resultado. Ao voltar,
 * o Form remonta e os refs voltam ao default — o usuário perde a seleção e tem
 * que refazer tudo para gerar outro relatório.
 *
 * Solução: guarda os filtros em `sessionStorage` por relatório (chave = slug).
 * Restaura no mount e salva ao gerar. Some ao fechar o navegador (não polui).
 *
 * Uso:
 *   const { restaurar, salvar } = useFiltrosRelatorio('boletim', {
 *       anl_id: null as number | null,
 *       esc_id: null as number | null,
 *   });
 *   const f = restaurar();
 *   const anlId = ref<number | null>(f.anl_id ?? anoDefault?.anl_id ?? null);
 *   const escId = ref<number | null>(f.esc_id ?? props.userEscola?.esc_id ?? null);
 *
 *   function gerar() {
 *       salvar({ anl_id: anlId.value, esc_id: escId.value });
 *       router.get('/relatorios/boletim/gerar', { ... });
 *   }
 */
export function useFiltrosRelatorio<T extends Record<string, unknown>>(chave: string, padrao: T) {
    const storageKey = `relatorio:filtros:${chave}`;

    /** Valores salvos mesclados sobre o padrão (padrão vence se a chave sumir). */
    const restaurar = (): T => {
        try {
            const raw = sessionStorage.getItem(storageKey);
            if (!raw) return { ...padrao };
            const salvos = JSON.parse(raw) as Partial<T>;
            return { ...padrao, ...salvos };
        } catch {
            return { ...padrao };
        }
    };

    const salvar = (valores: Partial<T>): void => {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(valores));
        } catch {
            /* storage cheio/indisponível → ignora, filtro só não persiste */
        }
    };

    const limpar = (): void => {
        try {
            sessionStorage.removeItem(storageKey);
        } catch {
            /* noop */
        }
    };

    return { restaurar, salvar, limpar };
}
