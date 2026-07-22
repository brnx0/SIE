import type { RotaResumo } from '@/types/transporte';
import { ref } from 'vue';
import { apiFetch } from '@/lib/apiFetch';

export function useRotasTransporte() {
    const loading = ref(false);
    const items = ref<RotaResumo[]>([]);
    let controller: AbortController | null = null;

    async function search(q: string, incluirIds: number[] = [], extra: { anl_id?: number | null; turno?: string } = {}): Promise<void> {
        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (incluirIds.length) params.set('incluir_ids', incluirIds.join(','));
            if (extra.anl_id) params.set('anl_id', String(extra.anl_id));
            if (extra.turno) params.set('turno', extra.turno);

            const res = await apiFetch(`/api/transporte/rotas/search?${params.toString()}`, {
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
