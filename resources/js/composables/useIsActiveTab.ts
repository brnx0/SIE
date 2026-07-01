import { computed, inject, type ComputedRef } from 'vue';
import { TAB_ID } from '@/lib/tabRegistry';
import { useTabStore } from '@/stores/tabs';

/**
 * Indica se o painel atual pertence à aba ativa do TabShell.
 *
 * Uso principal: gatear `<Teleport to="body">` de relatórios. Sem isso, toda
 * aba de relatório aberta teleporta seu `#print-area` para o <body> ao mesmo
 * tempo — vários com o mesmo id — e `window.print()` imprime todos empilhados
 * (sobreposição / página em branco). Com o teleport desabilitado nas abas
 * inativas, só a aba visível chega ao body.
 *
 * Fora do TabShell (TAB_ID ausente) retorna sempre `true`, preservando o
 * comportamento de páginas sem abas.
 */
export function useIsActiveTab(): ComputedRef<boolean> {
    const tabId = inject(TAB_ID, '');
    const store = useTabStore();
    return computed(() => !tabId || store.activeId === tabId);
}
