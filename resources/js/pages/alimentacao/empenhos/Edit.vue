<script setup lang="ts">
import EmpenhoForm from '@/components/alimentacao/EmpenhoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import type { Empenho } from '@/types/alimentacao';
import { Head, Link } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import { computed } from 'vue';

interface OrdemResumo {
    ofn_id: number;
    ofn_nr_ordem: string;
    ofn_dt_emissao: string | null;
    ofn_situacao: string;
}

const props = defineProps<{
    empenho: Empenho;
    situacoes: string[];
    saldo: { consumido: number; saldo: number };
    ordens: OrdemResumo[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Empenhos', href: '/alimentacao/empenhos' },
    { title: props.empenho.emp_nr_empenho, href: `/alimentacao/empenhos/${props.empenho.emp_id}/edit` },
]);

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dataBr = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—');

const empenhado = computed(() => Number(props.empenho.emp_vl_empenhado ?? 0));
const temOrdens = computed(() => (props.ordens?.length ?? 0) > 0);
</script>

<template>
    <Head :title="`Empenho ${empenho.emp_nr_empenho}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                    Empenho {{ empenho.emp_nr_empenho }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Contrato {{ empenho.contrato?.ctr_nr_contrato }}/{{ empenho.contrato?.ctr_ano }}
                </p>
            </div>

            <EmpenhoForm mode="edit" :initial="empenho" :situacoes="situacoes" :tem-ordens="temOrdens" />

            <!-- Saldo -->
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Empenhado</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ brl(empenhado) }}</p>
                </div>
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Consumido por ordens</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums">{{ brl(saldo.consumido) }}</p>
                </div>
                <div class="rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Saldo disponível</p>
                    <p :class="['mt-1 text-lg font-semibold tabular-nums', saldo.saldo <= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400']">
                        {{ brl(saldo.saldo) }}
                    </p>
                </div>
            </div>

            <!-- Ordens que consomem este empenho -->
            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
                <div>
                    <h2 class="text-lg font-semibold">Ordens de fornecimento</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Ordens custeadas por este empenho. Ordem cancelada não consome saldo.
                    </p>
                </div>

                <div class="overflow-hidden rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">Ordem</th>
                                <th class="px-4 py-3">Emissão</th>
                                <th class="px-4 py-3 text-center">Situação</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="ordens.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                                    Nenhuma ordem usa este empenho.
                                </td>
                            </tr>
                            <tr v-for="o in ordens" :key="o.ofn_id" class="transition-colors hover:bg-muted/30">
                                <td class="px-4 py-3 font-medium">{{ o.ofn_nr_ordem }}</td>
                                <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ dataBr(o.ofn_dt_emissao) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {{ o.ofn_situacao }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end">
                                        <Link :href="`/alimentacao/ordens-fornecimento/${o.ofn_id}/edit`">
                                            <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
