import type { Bairro } from '@/types/escola';
import { ref } from 'vue';
import { apiFetch } from '@/lib/apiFetch';

export function useBairros() {
    const loading = ref(false);
    const items = ref<Bairro[]>([]);
    let controller: AbortController | null = null;

    async function search(q: string, munId?: number | null): Promise<void> {
        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (munId) params.set('mun_id', String(munId));

            const res = await apiFetch(`/api/bairros?${params.toString()}`, {
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

    async function create(munId: number, nome: string): Promise<Bairro | null> {
        try {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const res = await apiFetch('/api/bairros', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ bai_mun_id: munId, bai_nome: nome }),
            });
            if (!res.ok) return null;
            return await res.json();
        } catch {
            return null;
        }
    }

    return { loading, items, search, create };
}
