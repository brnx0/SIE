import type { SerieResumo } from '@/types/serie';
import { apiFetch } from '@/lib/apiFetch';
import { ref } from 'vue';

export function useSeries() {
    const loading = ref(false);
    const items = ref<SerieResumo[]>([]);
    let controller: AbortController | null = null;

    async function search(q: string, exclude?: number | null, segId?: number | null): Promise<void> {
        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (exclude != null) params.set('exclude', String(exclude));
            if (segId != null) params.set('seg_id', String(segId));

            const res = await apiFetch(`/api/series/search?${params.toString()}`, {
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
