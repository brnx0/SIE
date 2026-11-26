<script setup lang="ts">
/**
 * Botões Anterior / Próximo entre registros de uma listagem, dentro do form.
 *
 * A sequência é a da listagem com os filtros que o usuário deixou aplicados:
 * o form passa esses filtros em `params` (lidos do snapshot de
 * `useFiltrosListagem`) e o servidor responde em `{recurso}/{id}/vizinhos`
 * usando a mesma consulta e ordenação da listagem.
 *
 * - Mantém o modo consulta (`?view=1`) ao trocar de registro.
 * - `dirty`: com alterações não salvas os botões ficam bloqueados, até o
 *   usuário salvar ou cancelar a edição.
 * - Registro fora do filtro atual → o componente não aparece.
 *
 * Uso (ver components/turma/TurmaForm.vue):
 *   <RegistroNavegacao recurso="/turmas" :id="initial.tur_id" :params="filtros" :dirty="form.isDirty" />
 */
import { Button } from '@/components/ui/button';
import { useViewMode } from '@/composables/useViewMode';
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    /** Caminho base do recurso, ex.: "/turmas". */
    recurso: string;
    /** Id do registro aberto. */
    id: number;
    /** Filtros da listagem, no formato que o index do recurso recebe. */
    params?: Record<string, string | number>;
    /** Form com alterações não salvas: bloqueia a navegação. */
    dirty?: boolean;
}>();

type Vizinhos = { anterior: number | null; proximo: number | null; posicao: number | null; total: number };

const vizinhos = ref<Vizinhos | null>(null);
const { viewMode } = useViewMode();
const bloqueio = 'Salve ou cancele a edição para ir a outro registro';

const carregar = async () => {
    vizinhos.value = null;
    try {
        const qs = new URLSearchParams(
            Object.entries(props.params ?? {}).map(([k, v]) => [k, String(v)]),
        ).toString();
        const r = await fetch(`${props.recurso}/${props.id}/vizinhos${qs ? `?${qs}` : ''}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (r.ok) vizinhos.value = await r.json();
    } catch {
        /* sem navegação: o form segue funcionando normalmente */
    }
};

onMounted(carregar);
watch(() => props.id, carregar);

const ir = (destino: number | null) => {
    if (!destino || props.dirty) return;
    router.visit(`${props.recurso}/${destino}/edit${viewMode.value ? '?view=1' : ''}`);
};
</script>

<template>
    <div v-if="vizinhos?.posicao" class="flex items-center gap-1" :title="dirty ? bloqueio : undefined">
        <Button
            :title="dirty ? bloqueio : 'Registro anterior'" type="button" variant="outline" size="icon" aria-label="Registro anterior"
            :disabled="dirty || !vizinhos.anterior" @click="ir(vizinhos.anterior)"
        >
            <ChevronLeft class="size-4" />
        </Button>
        <span class="min-w-16 px-1 text-center text-xs tabular-nums text-muted-foreground">
            {{ vizinhos.posicao }} de {{ vizinhos.total }}
        </span>
        <Button
            :title="dirty ? bloqueio : 'Próximo registro'" type="button" variant="outline" size="icon" aria-label="Próximo registro"
            :disabled="dirty || !vizinhos.proximo" @click="ir(vizinhos.proximo)"
        >
            <ChevronRight class="size-4" />
        </Button>
    </div>
</template>
