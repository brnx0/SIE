/**
 * Mantém os filtros (e a página) de uma listagem ao sair e voltar.
 *
 * Problema: os filtros da listagem vivem na query string. Ao abrir um registro
 * (`/turmas/123/edit`) e voltar pelo "Voltar à lista" — que aponta para a rota
 * nua — a listagem remonta sem query, o servidor devolve o padrão e o usuário
 * perde o filtro e a paginação. (Listagem e formulário dividem a MESMA aba no
 * shell de abas: ao voltar, o painel da listagem é recriado do zero.)
 *
 * Solução: guarda os valores dos filtros num objeto em `sessionStorage` por
 * listagem (chave = slug). A página inicializa os refs a partir do que foi salvo
 * (caindo no default quando não há nada), e reaplica no `onMounted` quando
 * chegou sem query — restaurando controles E dados juntos. Some ao fechar o
 * navegador, então não polui sessões futuras.
 *
 * Genérico: guardamos apenas ESCALARES (ids/strings/booleans), nunca objetos
 * ou listas. Reutilizável em qualquer listagem (ver pages/turmas/Index.vue).
 *
 * Uso:
 *   const { restaurar, salvar } = useFiltrosListagem<Filtros>('turmas');
 *   const salvos = restaurar();
 *   const fEscId = ref(salvos?.esc_id ?? props.filters.esc_id ?? '');
 *   // ...em apply(): salvar({ esc_id: fEscId.value, ... });
 *   onMounted(() => { if (salvos && window.location.search.length <= 1) apply(); });
 */
export function useFiltrosListagem<T extends Record<string, unknown>>(chave: string) {
    const storageKey = `listagem:filtros:${chave}`;

    /** Objeto de filtros salvo, ou null se não houver. */
    const restaurar = (): T | null => {
        try {
            const raw = sessionStorage.getItem(storageKey);
            return raw ? (JSON.parse(raw) as T) : null;
        } catch {
            return null;
        }
    };

    const salvar = (valores: T): void => {
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
