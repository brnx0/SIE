import { router } from '@inertiajs/vue3';
import { useTabStore } from '@/stores/tabs';
import { pathOf } from '@/lib/tabRegistry';

/**
 * Roteamento ciente de abas.
 *
 * - Aba diferente da ativa, já aberta → só ativa (preserva estado da aba).
 * - Aba não aberta → router.visit (cria nova aba).
 * - Mesma aba ativa, mas URL diferente → router.visit (sub-navega dentro
 *   da aba: ex. breadcrumb voltando de /escolas/1/edit p/ /escolas).
 * - Mesma aba ativa e mesma URL → no-op.
 */
export function useTabNav() {
    const store = useTabStore();

    const open = (href?: string) => {
        if (!href) return;
        const path = pathOf(href);
        const active = store.activeTab;

        if (active && active.path === path) {
            if (active.url !== href) router.visit(href);
            return;
        }

        const existing = store.findByPath(path);
        if (existing) store.setActive(existing.id);
        else router.visit(href);
    };

    return { open };
}
