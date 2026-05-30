import { apiFetch } from '@/lib/apiFetch';
import type { SegmentoResumo } from '@/types/turma';
import { ref } from 'vue';

export function useSegmentosByEscola() {
    const loading = ref(false);
    const items = ref<SegmentoResumo[]>([]);
    let controller: AbortController | null = null;

    async function search(escId: number, anlId: number, incluirIds: number[] = []): Promise<void> {
        if (!escId || !anlId) {
            items.value = [];
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams({ esc_id: String(escId), anl_id: String(anlId) });
            if (incluirIds.length) params.set('incluir_ids', incluirIds.join(','));
            const res = await apiFetch(`/api/segmentos/by-escola?${params.toString()}`, {
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
