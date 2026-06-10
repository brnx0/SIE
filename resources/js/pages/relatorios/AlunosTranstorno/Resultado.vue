<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, Printer } from 'lucide-vue-next';

interface Coluna { key: string; label: string }
interface Linha {
    inep_turma: string | null;
    escola: string | null;
    segmento: string | null;
    serie: string | null;
    turma: string | null;
    mat_aluno: number | null;
    aln_nome: string | null;
    filiacao: string | null;
    transt: Record<string, boolean>;
}
interface Parametros {
    nome_entidade: string;
    msg_cab_secretaria: string | null;
    msg_cab_estado: string | null;
    logomarca_url: string | null;
    brasao_url: string | null;
}

const props = defineProps<{
    parametros: Parametros | null;
    anoLetivo: { anl_id: number; anl_ano: number };
    escola: { esc_id: number; esc_nome: string } | null;
    colunasTranstornos: Coluna[];
    linhas: Linha[];
    totais: Record<string, number>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alunos com Transtorno', href: '/relatorios/alunos-transtorno' },
];

const imprimir = () => window.print();
</script>

<template>
    <Head title="Alunos com Transtorno" />

    <!-- Bloco impressão -->
    <Teleport to="body">
        <div id="print-area">
            <header class="cab">
                <div class="logo">
                    <img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" />
                </div>
                <div class="cab-textos">
                    <div v-if="parametros?.msg_cab_estado">{{ parametros.msg_cab_estado }}</div>
                    <div v-if="parametros?.msg_cab_secretaria">{{ parametros.msg_cab_secretaria }}</div>
                    <div class="entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
                </div>
                <div class="logo">
                    <img v-if="parametros?.brasao_url" :src="parametros.brasao_url" />
                </div>
            </header>

            <h1 class="titulo">RELATÓRIO DE ALUNOS COM TRANSTORNO — {{ anoLetivo.anl_ano }}</h1>
            <p v-if="escola" class="subtitulo">Escola: {{ escola.esc_nome }}</p>

            <table class="tab">
                <thead>
                    <tr>
                        <th>INEP Turma</th>
                        <th>Escola</th>
                        <th>Série</th>
                        <th>Turma</th>
                        <th>Mat. Aluno</th>
                        <th>Aluno</th>
                        <th>Filiação</th>
                        <th v-for="c in colunasTranstornos" :key="c.key" class="ind">{{ c.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(l, i) in linhas" :key="i">
                        <td>{{ l.inep_turma ?? '—' }}</td>
                        <td>{{ l.escola ?? '—' }}</td>
                        <td>{{ l.serie ?? '—' }}</td>
                        <td>{{ l.turma ?? '—' }}</td>
                        <td>{{ l.mat_aluno ?? '—' }}</td>
                        <td>{{ l.aln_nome ?? '—' }}</td>
                        <td>{{ l.filiacao ?? '—' }}</td>
                        <td v-for="c in colunasTranstornos" :key="c.key" class="ind">
                            <span v-if="l.transt[c.key]">✓</span>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7"><b>Total alunos: {{ totais.total }}</b></td>
                        <td v-for="c in colunasTranstornos" :key="c.key" class="ind"><b>{{ totais[c.key] ?? 0 }}</b></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Alunos com Transtorno — {{ anoLetivo.anl_ano }}</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/alunos-transtorno">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!totais.total" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <!-- Resumo totais -->
            <div class="grid gap-2 sm:grid-cols-4 mb-4">
                <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                    <div class="text-xs text-muted-foreground">Total alunos</div>
                    <div class="text-2xl font-bold">{{ totais.total }}</div>
                </div>
                <div v-for="c in colunasTranstornos" :key="c.key" class="rounded-xl border bg-card p-3 text-center shadow-sm">
                    <div class="text-xs text-muted-foreground">{{ c.label }}</div>
                    <div class="text-xl font-semibold text-indigo-600">{{ totais[c.key] ?? 0 }}</div>
                </div>
            </div>

            <div v-if="!totais.total" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum aluno com transtorno encontrado.
            </div>

            <div v-else class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-indigo-600 text-white">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">INEP Turma</th>
                            <th class="px-3 py-2 text-left font-semibold">Escola</th>
                            <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Série</th>
                            <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Turma</th>
                            <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Mat.</th>
                            <th class="px-3 py-2 text-left font-semibold">Aluno</th>
                            <th class="px-3 py-2 text-left font-semibold">Filiação</th>
                            <th v-for="c in colunasTranstornos" :key="c.key" class="px-2 py-2 text-center font-semibold">
                                {{ c.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(l, i) in linhas" :key="i" :class="i % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                            <td class="px-3 py-2 text-xs tabular-nums">{{ l.inep_turma ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ l.escola ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ l.serie ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ l.turma ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs tabular-nums">{{ l.mat_aluno ?? '—' }}</td>
                            <td class="px-3 py-2 font-medium">{{ l.aln_nome ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-muted-foreground">{{ l.filiacao ?? '—' }}</td>
                            <td v-for="c in colunasTranstornos" :key="c.key" class="px-2 py-2 text-center">
                                <Check v-if="l.transt[c.key]" class="mx-auto size-4 text-emerald-600" />
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 landscape; margin: 8mm; }
    body > *:not(#print-area) { display: none !important; }
    #print-area {
        display: block !important;
        font-family: Arial, sans-serif;
        font-size: 8pt;
        color: #000;
    }
    .cab { display: grid; grid-template-columns: 70px 1fr 70px; align-items: center; gap: 8px; border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
    .cab img { max-width: 70px; max-height: 70px; object-fit: contain; }
    .cab-textos { text-align: center; }
    .cab-textos div { font-size: 8pt; }
    .entidade { font-weight: 700; font-size: 9pt; }
    .titulo { text-align: center; font-size: 11pt; font-weight: 700; margin: 4px 0 2px; }
    .subtitulo { text-align: center; font-size: 9pt; margin-bottom: 6px; }
    .tab { width: 100%; border-collapse: collapse; }
    .tab th, .tab td { border: 1px solid #444; padding: 2px 4px; font-size: 7.5pt; }
    .tab th { background: #e5e7eb; font-weight: 700; text-transform: uppercase; }
    .tab .ind { text-align: center; width: 35px; }
    .tab tfoot td { background: #f3f4f6; font-weight: 700; }
}
</style>
