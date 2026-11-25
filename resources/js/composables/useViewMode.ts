import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePermissao } from '@/composables/usePermissao';

/**
 * Modo consulta das telas de cadastro.
 *
 * Padrão: na listagem o ícone de olho abre `/{recurso}/{id}/edit?view=1`; o form
 * detecta o `?view=1` e entra em somente-leitura MESMO para quem tem permissão de
 * edição, oferecendo um botão "Editar" para sair da consulta.
 *
 * - `isReadOnly`: usuário sem permissão de edição (nível VISUALIZAR da tela).
 * - `viewMode`:   aberto com `?view=1` (olho na listagem).
 * - `readonly`:   `isReadOnly || viewMode` — usar para desabilitar campos, esconder
 *                 Salvar e bloquear o submit.
 *
 * O botão "Editar" aparece quando `viewMode && !isReadOnly`.
 */
export function useViewMode() {
    const page = usePage();
    const { isReadOnly } = usePermissao();

    const viewMode = computed(
        () => new URLSearchParams(page.url.split('?')[1] ?? '').get('view') === '1',
    );
    const readonly = computed(() => isReadOnly.value || viewMode.value);

    return { isReadOnly, viewMode, readonly };
}
