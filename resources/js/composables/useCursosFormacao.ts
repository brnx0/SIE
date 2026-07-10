import type { CursoFormacaoSuperior } from '@/types/funcionario';
import { ref } from 'vue';
import { apiFetch } from '@/lib/apiFetch';

export function useCursosFormacao() {
    const loading = ref(false);
    const items = ref<CursoFormacaoSuperior[]>([]);
    let controller: AbortController | null = null;

    async function search(q: string): Promise<void> {
        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams();
            if (q) params.set('q', q);

            const res = await apiFetch(`/api/cursos-formacao-superior?${params.toString()}`, {
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
