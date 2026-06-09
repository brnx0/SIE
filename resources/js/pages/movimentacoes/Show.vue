<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

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
</script>

<template>
    <Head :title="`Movimentação #${movimentacao.mva_id}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Movimentação #{{ movimentacao.mva_id }}</h1>
                <Link href="/movimentacoes"><Button variant="outline">Voltar</Button></Link>
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
    </AppLayout>
</template>
