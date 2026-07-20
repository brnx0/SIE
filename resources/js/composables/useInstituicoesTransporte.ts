import type { InstituicaoResumo } from '@/types/transporte';
import { ref } from 'vue';
import { apiFetch } from '@/lib/apiFetch';

export function useInstituicoesTransporte() {
    const loading = ref(false);
    const items = ref<InstituicaoResumo[]>([]);
    let controller: AbortController | null = null;

    async function search(q: string, incluirIds: number[] = []): Promise<void> {
        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (incluirIds.length) params.set('incluir_ids', incluirIds.join(','));

            const res = await apiFetch(`/api/transporte/instituicoes/search?${params.toString()}`, {
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
