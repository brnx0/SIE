import { ref } from 'vue';

/**
 * Estado global de "sessão expirada".
 *
 * Disparado quando o backend responde 419 (token CSRF/sessão expirada) ou 401
 * (não autenticado). O SessionExpiredDialog observa este ref e exibe o aviso.
 * Ao confirmar, o usuário é DESLOGADO de fato (rota GET que invalida a sessão),
 * evitando o bug de voltar logado ao cair em /login ainda autenticado.
 */
export const sessionExpired = ref(false);

let jaDisparou = false;

export function triggerSessionExpired(): void {
    // Evita múltiplos disparos (várias requisições falhando juntas).
    if (jaDisparou) return;
    jaDisparou = true;
    sessionExpired.value = true;
}

/** Desloga de fato: rota GET (isenta de CSRF) que invalida a sessão e vai ao login. */
export function forcarLogout(): void {
    window.location.href = '/sessao-expirada';
}
