import { ref } from 'vue';
import { apiFetch } from '@/lib/apiFetch';

export interface FuncionarioResumo {
    fun_id: number;
    fun_nome: string;
    fun_cpf: string | null;
}

export function useFuncionarios() {
    const loading = ref(false);
    const items = ref<FuncionarioResumo[]>([]);
    let controller: AbortController | null = null;

    async function search(q: string): Promise<void> {
        if (controller) controller.abort();
        controller = new AbortController();

        loading.value = true;
        try {
            const params = new URLSearchParams();
            if (q) params.set('q', q);

            const res = await apiFetch(`/api/funcionarios?${params.toString()}`, {
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
