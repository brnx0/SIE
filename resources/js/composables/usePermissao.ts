import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import { resolveNivel, nivelDaRota, NIVEL_VISUALIZAR } from '@/lib/permissoes';

/**
 * Permissão por tela no frontend.
 *
 * - `nivelAtual` / `isReadOnly`: nível da TELA da página atual (read-only = 2).
 * - `nivelDe` / `podeVer`: lookup por rota exata — usado pelo menu (sidebar).
 */
export function usePermissao() {
    const page = usePage<SharedData>();
    const mapa = computed(() => page.props.permissoes ?? {});
    const roles = computed(() => page.props.auth?.user?.roles ?? []);

    const nivelAtual = computed(() => resolveNivel(page.url, mapa.value));
    const isReadOnly = computed(() => nivelAtual.value === NIVEL_VISUALIZAR);

    const nivelDe = (rota: string) => nivelDaRota(rota, mapa.value);
    const podeVer = (rota: string) => nivelDe(rota) > 0;
    const hasRole = (role: string) => roles.value.includes(role);

    return { mapa, roles, nivelAtual, isReadOnly, nivelDe, podeVer, hasRole };
}
