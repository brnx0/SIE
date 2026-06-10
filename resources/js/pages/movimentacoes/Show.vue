<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Ban, FileText, Loader2, RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const isTransferencia = computed(() =>
    [3, 4].includes(Number((props.movimentacao?.tipo?.tmv_cod ?? props.movimentacao?.mva_tmv_cod) ?? 0)),
);

const props = defineProps<{
    movimentacao: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Movimentações', href: '/movimentacoes' },
    { title: `#${props.movimentacao.mva_id}`, href: `/movimentacoes/${props.movimentacao.mva_id}` },
];

const formatDate = (iso: string | null) => {
    if (!iso) return '—';
    const [y, m, d] = iso.split('T')[0].split('-');
    return `${d}/${m}/${y}`;
};
const formatDateTime = (iso: string | null) => {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('pt-BR');
};

const dialogOpen = ref(false);
const motivo = ref('');
const processing = ref(false);

const abrirDialog = () => {
    motivo.value = '';
    dialogOpen.value = true;
};

const confirmarDesfazer = () => {
    processing.value = true;
    router.post(
        `/movimentacoes/${props.movimentacao.mva_id}/desfazer`,
        { motivo: motivo.value || null },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                dialogOpen.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="`Movimentação #${movimentacao.mva_id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold flex items-center gap-3">
                    Movimentação #{{ movimentacao.mva_id }}
                    <span
                        v-if="movimentacao.mva_fl_cancelada"
                        class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-900/40 dark:text-rose-300"
                    >
                        <Ban class="size-3.5" /> DESFEITA
                    </span>
                </h1>
                <div class="flex gap-2">
                    <Link href="/movimentacoes"><Button variant="outline">Voltar</Button></Link>
                    <a
                        v-if="isTransferencia && !movimentacao.mva_fl_cancelada"
                        :href="`/movimentacoes/${movimentacao.mva_id}/declaracao-transferencia`"
                        target="_blank"
                        rel="noopener"
                    >
                        <Button class="bg-emerald-600 text-white hover:bg-emerald-700">
                            <FileText class="mr-1 size-4" /> Declaração de Transferência
                        </Button>
                    </a>
                    <Button
                        v-if="!movimentacao.mva_fl_cancelada"
                        class="bg-rose-600 text-white hover:bg-rose-700"
                        @click="abrirDialog"
                    >
                        <RotateCcw class="mr-1 size-4" /> Desfazer Movimentação
                    </Button>
                </div>
            </div>

            <!-- Banner desfeita -->
            <div
                v-if="movimentacao.mva_fl_cancelada"
                class="mb-4 rounded-xl border border-rose-200 bg-rose-50/60 p-4 text-sm dark:bg-rose-900/10"
            >
                <p class="font-semibold text-rose-900 dark:text-rose-200">
                    Esta movimentação foi desfeita em {{ formatDateTime(movimentacao.mva_cancelada_at) }}
                    <span v-if="movimentacao.cancelada_por"> por {{ movimentacao.cancelada_por.name }}</span>.
                </p>
                <p v-if="movimentacao.mva_cancelada_motivo" class="mt-1 text-rose-800 dark:text-rose-300">
                    <span class="font-medium">Motivo:</span> {{ movimentacao.mva_cancelada_motivo }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Matrícula de origem reativada. Matrícula de destino (se houver) removida.
                </p>
            </div>

            <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                <div><span class="text-xs text-muted-foreground">Tipo</span><div class="font-medium">{{ movimentacao.tipo?.tmv_descricao }}</div></div>
                <div><span class="text-xs text-muted-foreground">Data</span><div class="font-medium">{{ formatDate(movimentacao.mva_dt_movimentacao) }}</div></div>
                <div><span class="text-xs text-muted-foreground">Aluno</span><div class="font-medium">{{ movimentacao.aluno?.aln_nome }}</div></div>
                <div><span class="text-xs text-muted-foreground">Protocolo</span><div class="font-medium">{{ movimentacao.mva_protocolo ?? '—' }}</div></div>
                <div><span class="text-xs text-muted-foreground">Usuário</span><div class="font-medium">{{ movimentacao.user?.name ?? '—' }}</div></div>
                <div><span class="text-xs text-muted-foreground">Origem</span>
                    <div class="font-medium">
                        {{ movimentacao.matricula_origem?.turma?.escola?.esc_nome ?? '—' }}
                        — {{ movimentacao.matricula_origem?.turma?.tur_nome ?? '' }}
                    </div>
                </div>
                <div v-if="movimentacao.matricula_destino">
                    <span class="text-xs text-muted-foreground">Destino</span>
                    <div class="font-medium">
                        {{ movimentacao.matricula_destino.turma?.escola?.esc_nome ?? '—' }}
                        — {{ movimentacao.matricula_destino.turma?.tur_nome ?? '' }}
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-muted-foreground">Observação</span>
                    <div class="whitespace-pre-wrap">{{ movimentacao.mva_observacao ?? '—' }}</div>
                </div>
                <div v-if="movimentacao.mva_tmas_extras?.length" class="sm:col-span-2">
                    <span class="text-xs text-muted-foreground">Matrículas AEE/Atividade encerradas junto</span>
                    <ul class="list-disc pl-5 text-sm">
                        <li v-for="(e, i) in movimentacao.mva_tmas_extras" :key="i">
                            tma_id #{{ e.tma_id }} ({{ e.tur_modalidade }})
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Confirmação desfazer -->
        <div
            v-if="dialogOpen"
            class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
            @click.self="dialogOpen = false"
        >
            <div class="w-full max-w-md rounded-xl border bg-card p-6 shadow-xl">
                <h2 class="mb-2 text-lg font-semibold">Desfazer Movimentação</h2>
                <p class="mb-4 text-sm text-muted-foreground">
                    A matrícula de origem será reativada e a matrícula de destino (se houver) será removida. Confirma?
                </p>
                <label class="mb-1 block text-sm font-medium">Motivo (opcional)</label>
                <textarea
                    v-model="motivo"
                    rows="3"
                    maxlength="500"
                    class="w-full rounded-md border bg-background px-3 py-2 text-sm resize-none"
                    placeholder="Ex.: cadastro incorreto, lançamento equivocado..."
                />
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" :disabled="processing" @click="dialogOpen = false">Cancelar</Button>
                    <Button class="bg-rose-600 text-white hover:bg-rose-700" :disabled="processing" @click="confirmarDesfazer">
                        <Loader2 v-if="processing" class="mr-1 size-4 animate-spin" />
                        Confirmar
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
