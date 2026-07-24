/**
 * Mantém os filtros e a página de uma listagem ao sair e voltar.
 *
 * Problema: os filtros da listagem vivem na query string. Ao abrir um registro
 * (`/turmas/123/edit`) e voltar pelo "Voltar à lista" — que aponta para a rota
 * nua — a listagem remonta sem query e o usuário perde filtro e paginação.
 * Como listagem e formulário dividem a MESMA aba (ver `pathOf` em
 * lib/tabRegistry), o painel é recriado e não há estado a preservar.
 *
 * Solução: guarda a query aplicada em `sessionStorage` por listagem (chave =
 * slug). Some ao fechar o navegador, então não polui sessões futuras.
 *
 * Guardamos a query crua (e não campo a campo) de propósito: serve a qualquer
 * listagem sem enumerar filtros, e a paginação vem junto de graça.
 *
 * Uso (ver pages/turmas/Index.vue):
 *   const { salvar, restaurar } = useFiltrosListagem('turmas');
 *
 *   onMounted(() => {
 *       // URL com query = intenção explícita do usuário; ela manda.
 *       if (window.location.search.length > 1) { salvar(window.location.search); return; }
 *       const salvo = restaurar();
 *       if (salvo) router.get(`/turmas${salvo}`, {}, { replace: true });
 *   });
 */
export function useFiltrosListagem(chave: string) {
    const storageKey = `listagem:filtros:${chave}`;

    /** Query salva, incluindo o "?" (ex.: "?anl_id=3&turno=MATUTINO"). '' se não houver. */
    const restaurar = (): string => {
        try {
            return sessionStorage.getItem(storageKey) ?? '';
        } catch {
            return '';
        }
    };

    const salvar = (query: string): void => {
        try {
            sessionStorage.setItem(storageKey, query);
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
