<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import RelatorioCabecalho from '@/components/relatorios/RelatorioCabecalho.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useIsActiveTab } from '@/composables/useIsActiveTab';

interface Indice {
    aprovados: number;
    reprovados: number;
    pendentes: number;
    avaliados: number;
    pct_aprovacao: number | null;
    pct_reprovacao: number | null;
}
interface Turma {
    tur_id: number;
    turma: string;
    serie: string | null;
    alunos: number;
    sem_avaliacao: boolean;
    aviso: string | null;
    indices: Record<string, Indice>;
}
interface Escola {
    esc_id: number;
    esc_nome: string;
    alunos: number;
    sem_avaliacao: number;
    indices: Record<string, Indice>;
    turmas: Turma[];
}
interface Periodo { chave: string; label: string }
interface Filtros {
    anl_id: number;
    anl_ano: number;
    esc_id: number | null;
    esc_nome: string | null;
    ser_id: number | null;
    ser_nome: string | null;
    tur_id: number | null;
    tur_nome: string | null;
    periodo: string | null;
}

const props = defineProps<{
    parametros: any;
    filtros: Filtros;
    periodos: Periodo[];
    escolas: Escola[];
    rede: { alunos: number; sem_avaliacao: number; indices: Record<string, Indice> };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Índices de Aprovação e Reprovação', href: '/relatorios/indices-aprovacao' },
];

const periodoAtivo = ref<string>(props.periodos[0]?.chave ?? 'anual');
const labelPeriodo = (c: string) => props.periodos.find((p) => p.chave === c)?.label ?? c;

const filtrosAtivos = computed(() => {
    const f = [`Ano ${props.filtros.anl_ano}`, props.filtros.esc_nome ?? 'Toda a rede'];
    if (props.filtros.ser_nome) f.push(`Série: ${props.filtros.ser_nome}`);
    if (props.filtros.tur_nome) f.push(`Turma: ${props.filtros.tur_nome}`);
    f.push(props.periodos.length === 1 ? `Período: ${props.periodos[0].label}` : 'Todos os períodos');
    return f;
});

const totalTurmas = computed(() => props.escolas.reduce((s, e) => s + e.turmas.length, 0));
const avisos = computed(() =>
    props.escolas.flatMap((e) => e.turmas.filter((t) => t.aviso && !t.sem_avaliacao).map((t) => ({ escola: e.esc_nome, turma: t.turma, aviso: t.aviso! }))),
);

const pct = (v: number | null) => (v === null ? '—' : `${v.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`);

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});

const isActiveTab = useIsActiveTab();
const imprimir = () => window.print();

const exportarExcel = () => {
    const p = new URLSearchParams();
    p.set('anl_id', String(props.filtros.anl_id));
    if (props.filtros.periodo) p.set('periodo', props.filtros.periodo);
    if (props.filtros.esc_id) p.set('esc_id', String(props.filtros.esc_id));
    if (props.filtros.ser_id) p.set('ser_id', String(props.filtros.ser_id));
    if (props.filtros.tur_id) p.set('tur_id', String(props.filtros.tur_id));
    window.open(`/relatorios/indices-aprovacao/xlsx?${p.toString()}`, '_blank');
};
</script>

<template>
    <Head title="Resultado — Índices de Aprovação" />

    <!-- Bloco exclusivo p/ impressão: resumo da rede + detalhe de CADA período -->
    <Teleport to="body" :disabled="!isActiveTab">
        <div id="print-area">
            <RelatorioCabecalho :parametros="parametros" />

            <div class="titulo-impressao">
                <h1>Índices de Aprovação e Reprovação</h1>
                <div class="subtitulo-impressao">
                    <span v-for="(f, i) in filtrosAtivos" :key="`f-${i}`">{{ f }}</span>
                </div>
            </div>

            <h2 class="secao-impressao">Consolidado{{ filtros.esc_nome ? ` — ${filtros.esc_nome}` : ' da rede' }}</h2>
            <table class="tabela-impressao">
                <thead>
                    <tr>
                        <th>Período</th><th class="centro">Alunos</th><th class="centro">Avaliados</th><th class="centro">Aprovados</th>
                        <th class="centro">Reprovados</th><th class="centro">Pendentes</th><th class="centro">% Aprov.</th><th class="centro">% Reprov.</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in periodos" :key="`pr-${p.chave}`">
                        <td class="celula-nome">{{ p.label }}</td>
                        <td class="centro">{{ rede.alunos }}</td>
                        <td class="centro">{{ rede.indices[p.chave].avaliados }}</td>
                        <td class="centro">{{ rede.indices[p.chave].aprovados }}</td>
                        <td class="centro">{{ rede.indices[p.chave].reprovados }}</td>
                        <td class="centro">{{ rede.indices[p.chave].pendentes }}</td>
                        <td class="centro">{{ pct(rede.indices[p.chave].pct_aprovacao) }}</td>
                        <td class="centro">{{ pct(rede.indices[p.chave].pct_reprovacao) }}</td>
                    </tr>
                </tbody>
            </table>

            <template v-for="p in periodos" :key="`pp-${p.chave}`">
                <h2 class="secao-impressao quebra">Por escola e turma — {{ p.label }}</h2>
                <table class="tabela-impressao">
                    <thead>
                        <tr>
                            <th style="width: 30%">Turma</th><th style="width: 16%">Série</th><th class="centro">Alunos</th><th class="centro">Avaliados</th>
                            <th class="centro">Aprov.</th><th class="centro">Reprov.</th><th class="centro">Pend.</th><th class="centro">% Aprov.</th><th class="centro">% Reprov.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="e in escolas" :key="`pe-${p.chave}-${e.esc_id}`">
                            <tr class="linha-escola">
                                <td colspan="2">{{ e.esc_nome }}</td>
                                <td class="centro">{{ e.alunos }}</td>
                                <td class="centro">{{ e.indices[p.chave].avaliados }}</td>
                                <td class="centro">{{ e.indices[p.chave].aprovados }}</td>
                                <td class="centro">{{ e.indices[p.chave].reprovados }}</td>
                                <td class="centro">{{ e.indices[p.chave].pendentes }}</td>
                                <td class="centro">{{ pct(e.indices[p.chave].pct_aprovacao) }}</td>
                                <td class="centro">{{ pct(e.indices[p.chave].pct_reprovacao) }}</td>
                            </tr>
                            <tr v-for="t in e.turmas" :key="`pt-${p.chave}-${t.tur_id}`">
                                <td>{{ t.turma }}</td>
                                <td>{{ t.serie ?? '—' }}</td>
                                <td class="centro">{{ t.alunos }}</td>
                                <template v-if="t.sem_avaliacao">
                                    <td colspan="6" class="centro">Sem avaliação por nota</td>
                                </template>
                                <template v-else>
                                    <td class="centro">{{ t.indices[p.chave].avaliados }}</td>
                                    <td class="centro">{{ t.indices[p.chave].aprovados }}</td>
                                    <td class="centro">{{ t.indices[p.chave].reprovados }}</td>
                                    <td class="centro">{{ t.indices[p.chave].pendentes }}</td>
                                    <td class="centro">{{ pct(t.indices[p.chave].pct_aprovacao) }}</td>
                                    <td class="centro">{{ pct(t.indices[p.chave].pct_reprovacao) }}</td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>

            <p class="nota-impressao">
                Percentuais sobre os alunos classificados (aprovados + reprovados); pendentes — com nota faltando — ficam fora do cálculo.
                <template v-if="rede.sem_avaliacao"> {{ rede.sem_avaliacao }} aluno(s) em séries sem avaliação por nota não entram no índice.</template>
            </p>

            <footer class="rodape-impressao">
                <span>Documento gerado pelo sistema SIE Matrícula</span>
                <span>Emitido em {{ dataEmissao }}</span>
            </footer>
        </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold">Resultado: Índices de Aprovação e Reprovação</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/indices-aprovacao">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button variant="outline" @click="exportarExcel">Excel</Button>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap gap-2 rounded-xl border bg-card p-4 shadow-sm">
                <span v-for="(f, i) in filtrosAtivos" :key="`fa-${i}`" class="rounded-full bg-muted px-3 py-0.5 text-xs">{{ f }}</span>
                <span class="rounded-full bg-muted px-3 py-0.5 text-xs">{{ escolas.length }} escola(s) · {{ totalTurmas }} turma(s)</span>
            </div>

            <div v-if="!escolas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhuma turma encontrada para os filtros selecionados.
            </div>

            <template v-else>
                <!-- Consolidado (rede ou escola filtrada) por período -->
                <section class="mb-4 overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="border-b bg-muted/30 px-4 py-2 text-sm font-semibold">
                        Consolidado{{ filtros.esc_nome ? ` — ${filtros.esc_nome}` : ' da rede' }}
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2 text-left">Período</th>
                                    <th class="px-3 py-2 text-center">Alunos</th>
                                    <th class="px-3 py-2 text-center">Avaliados</th>
                                    <th class="px-3 py-2 text-center">Aprovados</th>
                                    <th class="px-3 py-2 text-center">Reprovados</th>
                                    <th class="px-3 py-2 text-center">Pendentes</th>
                                    <th class="px-3 py-2 text-center">% Aprovação</th>
                                    <th class="px-3 py-2 text-center">% Reprovação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="p in periodos" :key="`r-${p.chave}`" :class="p.chave === 'anual' ? 'bg-indigo-50/50 font-semibold dark:bg-indigo-950/20' : ''">
                                    <td class="px-3 py-2">{{ p.label }}</td>
                                    <td class="px-3 py-2 text-center tabular-nums">{{ rede.alunos }}</td>
                                    <td class="px-3 py-2 text-center tabular-nums">{{ rede.indices[p.chave].avaliados }}</td>
                                    <td class="px-3 py-2 text-center tabular-nums text-emerald-700 dark:text-emerald-400">{{ rede.indices[p.chave].aprovados }}</td>
                                    <td class="px-3 py-2 text-center tabular-nums text-rose-700 dark:text-rose-400">{{ rede.indices[p.chave].reprovados }}</td>
                                    <td class="px-3 py-2 text-center tabular-nums text-amber-700 dark:text-amber-400">{{ rede.indices[p.chave].pendentes }}</td>
                                    <td class="px-3 py-2 text-center tabular-nums">{{ pct(rede.indices[p.chave].pct_aprovacao) }}</td>
                                    <td class="px-3 py-2 text-center tabular-nums">{{ pct(rede.indices[p.chave].pct_reprovacao) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="border-t px-4 py-2 text-xs text-muted-foreground">
                        Percentuais sobre os alunos classificados (aprovados + reprovados). Pendente = falta nota em alguma
                        disciplina no período (no anual, em algum trimestre).
                        <template v-if="rede.sem_avaliacao"> {{ rede.sem_avaliacao }} aluno(s) em séries sem avaliação por nota não entram no índice.</template>
                    </p>
                </section>

                <div v-if="avisos.length" class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <ul class="space-y-0.5">
                        <li v-for="(a, i) in avisos" :key="`av-${i}`"><strong>{{ a.escola }} · {{ a.turma }}:</strong> {{ a.aviso }}</li>
                    </ul>
                </div>

                <!-- Detalhe por escola e turma, um período por vez -->
                <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="flex flex-wrap items-center gap-2 border-b bg-muted/30 px-4 py-2">
                        <span class="mr-2 text-sm font-semibold">Por escola e turma</span>
                        <button
                            v-for="p in periodos"
                            :key="`chip-${p.chave}`"
                            type="button"
                            :class="[
                                'rounded-full border px-3 py-1 text-xs font-medium transition',
                                periodoAtivo === p.chave ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-input bg-background hover:border-indigo-300',
                            ]"
                            @click="periodoAtivo = p.chave"
                        >
                            {{ p.label }}
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" style="min-width: 900px">
                            <thead class="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2 text-left">Turma</th>
                                    <th class="px-3 py-2 text-left">Série</th>
                                    <th class="px-3 py-2 text-center">Alunos</th>
                                    <th class="px-3 py-2 text-center">Avaliados</th>
                                    <th class="px-3 py-2 text-center">Aprovados</th>
                                    <th class="px-3 py-2 text-center">Reprovados</th>
                                    <th class="px-3 py-2 text-center">Pendentes</th>
                                    <th class="px-3 py-2 text-center">% Aprovação</th>
                                    <th class="px-3 py-2 text-center">% Reprovação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="e in escolas" :key="`e-${e.esc_id}`">
                                    <tr class="border-t bg-muted/20 font-semibold">
                                        <td class="px-3 py-2" colspan="2">{{ e.esc_nome }}</td>
                                        <td class="px-3 py-2 text-center tabular-nums">{{ e.alunos }}</td>
                                        <td class="px-3 py-2 text-center tabular-nums">{{ e.indices[periodoAtivo].avaliados }}</td>
                                        <td class="px-3 py-2 text-center tabular-nums">{{ e.indices[periodoAtivo].aprovados }}</td>
                                        <td class="px-3 py-2 text-center tabular-nums">{{ e.indices[periodoAtivo].reprovados }}</td>
                                        <td class="px-3 py-2 text-center tabular-nums">{{ e.indices[periodoAtivo].pendentes }}</td>
                                        <td class="px-3 py-2 text-center tabular-nums">{{ pct(e.indices[periodoAtivo].pct_aprovacao) }}</td>
                                        <td class="px-3 py-2 text-center tabular-nums">{{ pct(e.indices[periodoAtivo].pct_reprovacao) }}</td>
                                    </tr>
                                    <tr v-for="t in e.turmas" :key="`t-${t.tur_id}`" class="border-t">
                                        <td class="px-3 py-1.5 pl-6">{{ t.turma }}</td>
                                        <td class="px-3 py-1.5 text-muted-foreground">{{ t.serie ?? '—' }}</td>
                                        <td class="px-3 py-1.5 text-center tabular-nums">{{ t.alunos }}</td>
                                        <template v-if="t.sem_avaliacao">
                                            <td colspan="6" class="px-3 py-1.5 text-center text-xs text-muted-foreground">Sem avaliação por nota (fora do índice)</td>
                                        </template>
                                        <template v-else>
                                            <td class="px-3 py-1.5 text-center tabular-nums">{{ t.indices[periodoAtivo].avaliados }}</td>
                                            <td class="px-3 py-1.5 text-center tabular-nums text-emerald-700 dark:text-emerald-400">{{ t.indices[periodoAtivo].aprovados }}</td>
                                            <td class="px-3 py-1.5 text-center tabular-nums text-rose-700 dark:text-rose-400">{{ t.indices[periodoAtivo].reprovados }}</td>
                                            <td class="px-3 py-1.5 text-center tabular-nums text-amber-700 dark:text-amber-400">{{ t.indices[periodoAtivo].pendentes }}</td>
                                            <td class="px-3 py-1.5 text-center tabular-nums">{{ pct(t.indices[periodoAtivo].pct_aprovacao) }}</td>
                                            <td class="px-3 py-1.5 text-center tabular-nums">
                                                <span :class="(t.indices[periodoAtivo].pct_reprovacao ?? 0) > 0 ? 'font-semibold text-rose-700 dark:text-rose-400' : ''">
                                                    {{ pct(t.indices[periodoAtivo].pct_reprovacao) }}
                                                </span>
                                            </td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t bg-muted/30 px-4 py-2 text-xs text-muted-foreground">
                        Exibindo: {{ labelPeriodo(periodoAtivo) }}. A impressão e o Excel trazem todos os períodos do filtro.
                    </div>
                </section>
            </template>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 landscape; margin: 3mm 8mm 8mm 8mm; }
    body > *:not(#print-area) { display: none !important; }
    #print-area {
        display: block !important;
        position: static;
        width: 100%;
        font-family: 'Times New Roman', Times, serif;
        color: #000;
        background: white;
    }

    .cabecalho-impressao {
        display: grid;
        grid-template-columns: 110px 1fr 110px;
        align-items: center;
        gap: 10px;
        padding-bottom: 4px;
        border-bottom: 2px solid #000;
    }
    .brasao img, .logomarca img { max-width: 105px; max-height: 105px; object-fit: contain; }
    .cab-textos { text-align: center; line-height: 1.35; }
    .cab-linha { font-size: 10pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .cab-entidade { font-size: 12pt; font-weight: 700; text-transform: uppercase; margin: 1px 0; }
    .cab-end { font-size: 9pt; font-style: italic; }

    .titulo-impressao { text-align: center; margin: 12px 0 8px; border-bottom: 1px solid #000; padding-bottom: 6px; }
    .titulo-impressao h1 { font-size: 13pt; font-weight: 700; margin: 0 0 4px; text-transform: uppercase; letter-spacing: 1.5px; }
    .subtitulo-impressao { display: flex; justify-content: center; flex-wrap: wrap; gap: 14px; font-size: 9pt; }

    .secao-impressao { font-size: 10.5pt; font-weight: 700; margin: 10px 0 4px; text-transform: uppercase; }
    .quebra { page-break-before: always; }

    .tabela-impressao { width: 100%; border-collapse: collapse; font-size: 8.5pt; border: 1px solid #000; }
    .tabela-impressao th {
        padding: 4px 5px; border: 1px solid #000; text-align: left; font-weight: 700;
        text-transform: uppercase; font-size: 7.5pt; letter-spacing: 0.3px;
    }
    .tabela-impressao td { padding: 3px 4px; border: 1px solid #000; vertical-align: top; }
    .tabela-impressao .centro { text-align: center; }
    .tabela-impressao .celula-nome { font-weight: 600; }
    .tabela-impressao .linha-escola td { font-weight: 700; background: #eee; }

    .nota-impressao { font-size: 8.5pt; font-style: italic; margin-top: 8px; }
    .rodape-impressao {
        margin-top: 24px; padding-top: 6px; border-top: 1px solid #000; font-size: 8.5pt;
        display: flex; justify-content: space-between; font-style: italic;
    }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
