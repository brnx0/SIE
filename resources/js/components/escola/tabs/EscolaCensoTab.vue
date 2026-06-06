<script setup lang="ts">
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import type { CensoEscolarResumo } from '@/types/censo';
import type { AnoLetivo } from '@/types/parametro';
import { router, useForm } from '@inertiajs/vue3';
import { CalendarClock, ClipboardCheck, ClipboardList, Eye, FilePlus, Pencil } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    escId: number;
    anoLetivoAtual: Pick<AnoLetivo, 'anl_id' | 'anl_ano' | 'anl_dt_censo' | 'anl_fl_em_exercicio'> | null;
    censoHistorico: CensoEscolarResumo[];
    censoAtual: Pick<CensoEscolarResumo, 'cen_id' | 'cen_anl_id'> | null;
    censoPreviousExists: boolean;
}>();

const storeForm = useForm<{ replicate: boolean }>({ replicate: false });

// Reload props when user returns focus (ex: changed anl_dt_censo in another tab)
const onVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        router.reload({ only: ['anoLetivoAtual', 'censoAtual', 'censoPreviousExists', 'censoHistorico'] });
    }
};
onMounted(() => document.addEventListener('visibilitychange', onVisibilityChange));
onUnmounted(() => document.removeEventListener('visibilitychange', onVisibilityChange));

const replicateDialogOpen = ref(false);

function parseLocalDate(dt: string | null | undefined): Date | null {
    if (!dt) return null;
    // Always extract YYYY-MM-DD portion and parse as local midnight to avoid UTC-offset shift
    const dateOnly = dt.slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateOnly)) return null;
    const d = new Date(dateOnly + 'T00:00:00');
    return isNaN(d.getTime()) ? null : d;
}

const prazoFormatado = computed(() => {
    const d = parseLocalDate(props.anoLetivoAtual?.anl_dt_censo);
    return d ? d.toLocaleDateString('pt-BR') : null;
});

const prazoEncerrado = computed(() => {
    const d = parseLocalDate(props.anoLetivoAtual?.anl_dt_censo);
    if (!d) return false;
    // Compare against end of deadline day
    d.setHours(23, 59, 59, 999);
    return new Date() > d;
});

const historicoPrevios = computed(() =>
    props.censoHistorico.filter((c) => c.cen_id !== props.censoAtual?.cen_id)
);

function formatDate(dt: string | null) {
    const d = parseLocalDate(dt);
    return d ? d.toLocaleDateString('pt-BR') : '—';
}

function iniciarCenso() {
    // Se tem censo anterior, pergunta se quer replicar
    if (!props.censoAtual && props.censoPreviousExists) {
        replicateDialogOpen.value = true;
    } else {
        submeterIniciar(false);
    }
}

function submeterIniciar(replicate: boolean) {
    replicateDialogOpen.value = false;
    storeForm.replicate = replicate;
    storeForm.post(`/escolas/${props.escId}/censo`);
}

function abrirEdicao() {
    if (props.censoAtual) {
        router.visit(`/escolas/${props.escId}/censo/${props.censoAtual.cen_id}/edit`);
    }
}

function abrirVisualizacao(cenId: number) {
    router.visit(`/escolas/${props.escId}/censo/${cenId}`);
}
</script>

<template>
    <div class="flex flex-col gap-6">

        <!-- Cabeçalho do ano atual -->
        <div class="rounded-xl border bg-card p-5 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-1">
                    <h3 class="flex items-center gap-2 text-base font-semibold text-slate-800 dark:text-slate-100">
                        <ClipboardList class="size-5 text-indigo-500" />
                        Censo Escolar
                        <span v-if="anoLetivoAtual" class="text-indigo-600 dark:text-indigo-400">
                            {{ anoLetivoAtual.anl_ano }}
                        </span>
                    </h3>

                    <p v-if="prazoFormatado" class="flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400">
                        <CalendarClock class="size-4" />
                        Prazo:
                        <span :class="prazoEncerrado ? 'font-semibold text-rose-600 dark:text-rose-400' : 'font-semibold text-slate-700 dark:text-slate-200'">
                            {{ prazoFormatado }}
                            <span v-if="prazoEncerrado"> — encerrado</span>
                        </span>
                    </p>

                    <p v-if="!anoLetivoAtual" class="text-sm text-amber-600 dark:text-amber-400">
                        Nenhum ano letivo em exercício configurado.
                    </p>
                </div>

                <!-- Ações -->
                <div class="flex items-center gap-2">
                  <RefreshButton />
                  <div v-if="anoLetivoAtual">
                    <!-- Censo ainda não iniciado -->
                    <Button
                        v-if="!censoAtual"
                        type="button"
                        :disabled="storeForm.processing || prazoEncerrado"
                        class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        @click="iniciarCenso"
                    >
                        <FilePlus class="mr-2 size-4" />
                        Iniciar preenchimento
                    </Button>

                    <!-- Censo em preenchimento -->
                    <Button
                        v-else-if="!prazoEncerrado"
                        type="button"
                        class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        @click="abrirEdicao"
                    >
                        <Pencil class="mr-2 size-4" />
                        Continuar preenchimento
                    </Button>

                    <!-- Censo finalizado (prazo encerrado) -->
                    <Button
                        v-else
                        type="button"
                        variant="outline"
                        @click="abrirVisualizacao(censoAtual!.cen_id)"
                    >
                        <Eye class="mr-2 size-4" />
                        Visualizar censo
                    </Button>
                  </div>
                </div>
            </div>

            <!-- Status badge -->
            <div v-if="censoAtual" class="mt-3 flex items-center gap-2">
                <span
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-0.5 text-xs font-medium',
                        prazoEncerrado
                            ? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                    ]"
                >
                    <ClipboardCheck v-if="prazoEncerrado" class="size-3.5" />
                    <ClipboardList v-else class="size-3.5" />
                    {{ prazoEncerrado ? 'Finalizado' : 'Em preenchimento' }}
                </span>
            </div>
        </div>

        <!-- Histórico de censos anteriores -->
        <div v-if="historicoPrevios.length > 0">
            <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Histórico
            </h4>
            <div class="overflow-hidden rounded-xl border shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Ano</th>
                            <th class="px-4 py-3">Prazo</th>
                            <th class="px-4 py-3">Atualizado em</th>
                            <th class="px-4 py-3 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                        <tr
                            v-for="censo in historicoPrevios"
                            :key="censo.cen_id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        >
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">
                                {{ censo.ano_letivo?.anl_ano ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                {{ formatDate(censo.ano_letivo?.anl_dt_censo ?? null) }}
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                {{ formatDate(censo.cen_updated_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="abrirVisualizacao(censo.cen_id)"
                                >
                                    <Eye class="mr-1.5 size-3.5" />
                                    Ver
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p v-else-if="!censoAtual" class="text-sm text-slate-400 dark:text-slate-500">
            Nenhum censo registrado anteriormente.
        </p>
    </div>

    <!-- Dialog: replicar do ano anterior? -->
    <Dialog :open="replicateDialogOpen" @update:open="(v) => (replicateDialogOpen = v)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <FilePlus class="size-5 text-indigo-500" />
                    Iniciar Censo {{ anoLetivoAtual?.anl_ano }}
                </DialogTitle>
                <DialogDescription class="pt-2 text-sm text-foreground">
                    Existe um censo preenchido de anos anteriores. Deseja copiar as informações do último censo como ponto de partida?
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="storeForm.processing"
                    @click="submeterIniciar(false)"
                >
                    Não, iniciar em branco
                </Button>
                <Button
                    type="button"
                    :disabled="storeForm.processing"
                    class="bg-indigo-600 text-white hover:bg-indigo-700"
                    @click="submeterIniciar(true)"
                >
                    Sim, replicar dados anteriores
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
