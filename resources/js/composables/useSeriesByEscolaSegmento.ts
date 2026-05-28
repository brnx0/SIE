import { apiFetch } from '@/lib/apiFetch';
import type { SerieResumoTurma } from '@/types/turma';
import { ref } from 'vue';

export function useSeriesByEscolaSegmento() {
    const loading = ref(false);
    const items = ref<SerieResumoTurma[]>([]);
    let controller: AbortController | null = null;

    async function search(escId: number, anlId: number, segId: number): Promise<void> {
        if (!escId || !anlId || !segId) {
            items.value = [];
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams({
                esc_id: String(escId),
                anl_id: String(anlId),
                seg_id: String(segId),
            });
            const res = await apiFetch(`/api/series/by-escola-segmento?${params.toString()}`, {
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
