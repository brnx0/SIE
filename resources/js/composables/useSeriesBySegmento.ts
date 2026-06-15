import { apiFetch } from '@/lib/apiFetch';
import { ref } from 'vue';

export interface SerieSegmentoItem {
    ser_id: number;
    ser_nome: string;
    ser_idade: number;
}

export function useSeriesBySegmento() {
    const loading = ref(false);
    const items = ref<SerieSegmentoItem[]>([]);
    let controller: AbortController | null = null;

    async function search(segId: number): Promise<void> {
        if (!segId) {
            items.value = [];
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams({
                seg_id: String(segId),
                excluir_multi: '1',
            });
            const res = await apiFetch(`/api/series?${params.toString()}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) { items.value = []; return; }
            items.value = await res.json();
        } catch (e: unknown) {
            if ((e as Error).name !== 'AbortError') items.value = [];
        } finally {
            loading.value = false;
        }
    }

    function clear(): void {
        items.value = [];
    }

    return { loading, items, search, clear };
}
