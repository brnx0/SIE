/**
 * Resolução de nível de permissão por tela no frontend.
 * Espelha App\Support\PermissaoTela do backend (longest-prefix por segmento).
 *
 * Nível: 0 = sem acesso, 1 = ver+editar, 2 = só visualizar.
 */

export const NIVEL_SEM_ACESSO = 0;
export const NIVEL_EDITAR = 1;
export const NIVEL_VISUALIZAR = 2;

/** Extrai só o pathname de uma url (descarta query/host). */
function soPath(url: string): string {
    try {
        return new URL(url, 'http://internal').pathname;
    } catch {
        return url.split('?')[0];
    }
}

/**
 * Resolve a rota de tela "dona" de um path, via longest-prefix por fronteira
 * de segmento. Ex.: /turmas/5/edit → /turmas. Retorna null se nada casar.
 */
export function resolverTela(url: string, mapa: Record<string, number>): string | null {
    const path = '/' + soPath(url).replace(/^\/+|\/+$/g, '');

    let melhor: string | null = null;
    for (const rota of Object.keys(mapa)) {
        if (path === rota || path.startsWith(rota.replace(/\/+$/, '') + '/')) {
            if (melhor === null || rota.length > melhor.length) {
                melhor = rota;
            }
        }
    }
    return melhor;
}

/**
 * Nível efetivo para um path. Tela não mapeada → 1 (liberado), espelhando o
 * backend (rota não registrada não é gateada).
 */
export function resolveNivel(url: string, mapa: Record<string, number> | undefined): number {
    if (!mapa) return NIVEL_EDITAR;
    const rota = resolverTela(url, mapa);
    if (rota === null) return NIVEL_EDITAR;
    return mapa[rota] ?? NIVEL_SEM_ACESSO;
}

/**
 * Nível de uma rota exata (lookup direto no mapa). Usado pelo menu.
 * Ausente no mapa = nível 0 (sem acesso) — o mapa só traz telas com nível ≥ 1.
 */
export function nivelDaRota(rota: string, mapa: Record<string, number> | undefined): number {
    if (!mapa) return NIVEL_SEM_ACESSO;
    return mapa[rota] ?? NIVEL_SEM_ACESSO;
}
