import type { Escola } from '@/types/funcionario';
import { ref } from 'vue';
import { apiFetch } from '@/lib/apiFetch';

export function useEscolas() {
    const loading = ref(false);
    const items = ref<Escola[]>([]);
    let controller: AbortController | null = null;

    async function search(q: string): Promise<void> {
        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams();
            if (q) params.set('q', q);

            const res = await apiFetch(`/api/escolas?${params.toString()}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                items.value = [];
                return;
            }
            items.value = await res.json();
        } catch (e: unknown) {
            if ((e as Error).name !== 'AbortError') {
                items.value = [];
            }
        } finally {
            loading.value = false;
        }
    }

    return { loading, items, search };
}
