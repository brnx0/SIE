import { nextTick } from 'vue';

/**
 * Converte a chave de erro de validação no id do input correspondente.
 * Remove prefixos aninhados: 'saude.als_cid' → 'als_cid', 'aluno.aln_nome' → 'aln_nome'.
 */
export const idFromErrorKey = (key: string): string =>
    key.includes('.') ? key.slice(key.lastIndexOf('.') + 1) : key;

/**
 * Padrão do projeto para feedback de campos obrigatórios: ao dar erro de
 * validação, o formulário leva o usuário ATÉ o campo — troca de aba (feito pelo
 * caller) e foca/scrolla o primeiro input inválido.
 *
 * Uso:
 *   const { focusFirstError } = useErrorFocus();
 *   // dentro do onError, após ativar a aba do primeiro erro:
 *   focusFirstError(Object.keys(form.errors));
 */
export function useErrorFocus() {
    /**
     * Foca o primeiro campo inválido (na ordem do DOM) entre as chaves passadas.
     * Deve ser chamado depois de ativar a aba correta — inputs em abas ocultas
     * não estão no DOM e não podem receber foco.
     */
    const focusFirstError = async (keys: string[]): Promise<void> => {
        await nextTick();

        const els = keys
            .map((k) => document.getElementById(idFromErrorKey(k)))
            .filter((e): e is HTMLElement => !!e);
        if (!els.length) return;

        // primeiro na ordem do documento
        els.sort((a, b) =>
            a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1,
        );

        const first = els[0];
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        first.focus({ preventScroll: true });
    };

    return { focusFirstError, idFromErrorKey };
}
