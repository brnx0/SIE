import type { Municipio } from '@/types/aluno';
import { ref } from 'vue';

export function useMunicipios() {
    const loading = ref(false);
    const items = ref<Municipio[]>([]);
    let controller: AbortController | null = null;

    async function search(q: string, uf?: string): Promise<void> {
        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (uf) params.set('uf', uf);

            const res = await fetch(`/api/municipios?${params.toString()}`, {
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

    async function byIbge(codigo: string): Promise<Municipio | null> {
        try {
            const res = await fetch(`/api/municipios/by-ibge/${codigo}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return null;
            return await res.json();
        } catch {
            return null;
        }
    }

    return { loading, items, search, byIbge };
}
