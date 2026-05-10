import type { GerenciaRegional } from '@/types/escola';
import { ref } from 'vue';

export function useGerencias() {
    const loading = ref(false);
    const items = ref<GerenciaRegional[]>([]);
    let controller: AbortController | null = null;

    async function search(q: string, uf?: string | null): Promise<void> {
        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (uf) params.set('uf', uf);

            const res = await fetch(`/api/gerencias?${params.toString()}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                items.value = [];
                return;
            }
            items.value = await res.json();
        } catch (e: unknown) {
            if ((e as Error).name !== 'AbortError') items.value = [];
        } finally {
            loading.value = false;
        }
    }

    return { loading, items, search };
}
