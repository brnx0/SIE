<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { useIsActiveTab } from '@/composables/useIsActiveTab';

interface TurmaPendente {
    tur_id: number;
    turma: string | null;
    serie: string | null;
    segmento: string | null;
    turno: string | null;
    situacao: string | null;
    alunos: number;
    renovados: number;
    pendencia: string;
}

interface Linha {
    esc_id: number;
    esc_nome: string;
    esc_codigo: string | null;
    total_turmas: number;
    sem_encerramento: number;
    sem_renovacao: number;
    total_pendente: number;
    afetados: string[];
    turmas: TurmaPendente[];
}

interface Filtros {
    anl_id: number;
    anl_ano: number;
    esc_id: number | null;
    esc_nome: string | null;
    seg_id: number | null;
    seg_nome: string | null;
    formato: 'consolidado' | 'detalhado';
}

interface Parametros {
    nome_entidade: string;
    msg_cab_secretaria: string | null;
    msg_cab_estado: string | null;
    endereco: string | null;
    logomarca_url: string | null;
    brasao_url: string | null;
}

const props = defineProps<{
    parametros: Parametros | null;
    filtros: Filtros;
    linhas: Linha[];
    resumo: {
        escolas_pendentes: number;
        escolas_avaliadas: number;
        sem_encerramento: number;
        sem_renovacao: number;
        total_pendente: number;
        total_turmas: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pendências de Encerramento', href: '/relatorios/pendencias-encerramento' },
];

const detalhado = computed(() => props.filtros.formato === 'detalhado');
const escopo = computed(() => props.filtros.esc_nome ?? 'Todas as escolas da rede');
const segmentoLabel = computed(() => props.filtros.seg_nome ?? 'Todos os segmentos');

/** Modo detalhado: uma linha por turma pendente. */
const linhasDetalhe = computed(() =>
    props.linhas.flatMap((l) => l.turmas.map((t) => ({ escola: l, turma: t }))),
);

const escolasEmDia = computed(() => props.resumo.escolas_avaliadas - props.resumo.escolas_pendentes);

/** Larguras em % — o print usa table-layout fixed. */
const larguras = computed(() =>
    detalhado.value
        ? ['20%', '8%', '13%', '14%', '11%', '8%', '11%', '7%', '8%']
        : ['26%', '9%', '11%', '11%', '10%', '33%'],
);

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});

const isActiveTab = useIsActiveTab();
const imprimir = () => window.print();

const queryFiltros = () => {
    const p = new URLSearchParams();
    p.set('anl_id', String(props.filtros.anl_id));
    if (props.filtros.esc_id) p.set('esc_id', String(props.filtros.esc_id));
    if (props.filtros.seg_id) p.set('seg_id', String(props.filtros.seg_id));
    p.set('formato', props.filtros.formato);
    return p;
};

const exportarCsv = () => {
    const sep = ';';
    const aspas = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;

    let headers: string[];
    let corpo: (string | number)[][];

    if (detalhado.value) {
        headers = ['Escola', 'Código', 'Segmento', 'Ano Escolar / Série', 'Turma', 'Turno', 'Situação da Turma', 'Pendência', 'Alunos', 'Alunos Renovados'];
        corpo = linhasDetalhe.value.map(({ escola, turma }) => [
            escola.esc_nome, escola.esc_codigo ?? '',
            turma.segmento ?? '', turma.serie ?? '', turma.turma ?? '', turma.turno ?? '',
            turma.situacao ?? '', turma.pendencia, turma.alunos, turma.renovados,
        ]);
    } else {
        headers = ['Escola', 'Código', 'Turmas sem Encerramento', 'Turmas sem Renovação', 'Total Pendente', 'Total de Turmas', 'Segmentos / Anos Escolares Afetados'];
        corpo = props.linhas.map((l) => [
            l.esc_nome, l.esc_codigo ?? '',
            l.sem_encerramento, l.sem_renovacao, l.total_pendente, l.total_turmas,
            l.afetados.join(' / '),
        ]);
    }

    const csv = '﻿' + [headers.join(sep), ...corpo.map((r) => r.map(aspas).join(sep))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `pendencias-encerramento-${props.filtros.anl_ano}-${props.filtros.formato}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const exportarExcel = () => window.open(`/relatorios/pendencias-encerramento/xlsx?${queryFiltros().toString()}`, '_blank');
</script>

<template>
    <Head title="Resultado — Pendências de Encerramento" />

    <!-- Bloco exclusivo p/ impressão -->
    <Teleport to="body" :disabled="!isActiveTab">
    <div id="print-area">
        <header class="cabecalho-impressao">
            <div class="logomarca">
                <img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="Logomarca do Município" />
            </div>
            <div class="cab-textos">
                <div v-if="parametros?.msg_cab_estado" class="cab-linha">{{ parametros.msg_cab_estado }}</div>
                <div v-if="parametros?.msg_cab_secretaria" class="cab-linha">{{ parametros.msg_cab_secretaria }}</div>
                <div class="cab-entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
                <div v-if="parametros?.endereco" class="cab-end">{{ parametros.endereco }}</div>
            </div>
            <div class="brasao">
                <img v-if="parametros?.brasao_url" :src="parametros.brasao_url" alt="Brasão" />
            </div>
        </header>

        <div class="titulo-impressao">
            <h1>Pendências de Encerramento e Renovação</h1>
            <div class="subtitulo-impressao">
                <span><b>Ano Letivo:</b> {{ filtros.anl_ano }}</span>
                <span><b>Escola:</b> {{ escopo }}</span>
                <span><b>Segmento:</b> {{ segmentoLabel }}</span>
                <span><b>Escolas pendentes:</b> {{ resumo.escolas_pendentes }}</span>
            </div>
        </div>

        <table class="tabela-impressao">
            <colgroup>
                <col v-for="(w, i) in larguras" :key="`c-${i}`" :style="{ width: w }" />
            </colgroup>

            <thead v-if="detalhado">
                <tr>
                    <th>Escola</th>
                    <th>Código</th>
                    <th>Segmento</th>
                    <th>Ano Escolar</th>
                    <th>Turma</th>
                    <th>Turno</th>
                    <th>Pendência</th>
                    <th class="centro">Alunos</th>
                    <th class="centro">Renov.</th>
                </tr>
            </thead>
            <thead v-else>
                <tr>
                    <th>Escola</th>
                    <th>Código</th>
                    <th class="centro">Sem Encerr.</th>
                    <th class="centro">Sem Renov.</th>
                    <th class="centro">Total Pend.</th>
                    <th>Segmentos / Anos Escolares Afetados</th>
                </tr>
            </thead>

            <tbody v-if="detalhado">
                <tr v-for="(d, i) in linhasDetalhe" :key="`pd-${i}`">
                    <td class="celula-nome">{{ d.escola.esc_nome }}</td>
                    <td>{{ d.escola.esc_codigo ?? '—' }}</td>
                    <td>{{ d.turma.segmento ?? '—' }}</td>
                    <td>{{ d.turma.serie ?? '—' }}</td>
                    <td>{{ d.turma.turma ?? '—' }}</td>
                    <td>{{ d.turma.turno ?? '—' }}</td>
                    <td>{{ d.turma.pendencia }}</td>
                    <td class="centro">{{ d.turma.alunos }}</td>
                    <td class="centro">{{ d.turma.renovados }}</td>
                </tr>
            </tbody>
            <tbody v-else>
                <tr v-for="l in linhas" :key="`pc-${l.esc_id}`">
                    <td class="celula-nome">{{ l.esc_nome }}</td>
                    <td>{{ l.esc_codigo ?? '—' }}</td>
                    <td class="centro">{{ l.sem_encerramento }}</td>
                    <td class="centro">{{ l.sem_renovacao }}</td>
                    <td class="centro">{{ l.total_pendente }}</td>
                    <td>{{ l.afetados.join(' / ') || '—' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="resumo-impressao">
            <span><b>Escolas pendentes:</b> {{ resumo.escolas_pendentes }} de {{ resumo.escolas_avaliadas }}</span>
            <span><b>Turmas sem encerramento:</b> {{ resumo.sem_encerramento }}</span>
            <span><b>Turmas sem renovação:</b> {{ resumo.sem_renovacao }}</span>
            <span><b>Total pendente:</b> {{ resumo.total_pendente }} de {{ resumo.total_turmas }} turmas</span>
        </div>

        <footer class="rodape-impressao">
            <span>Documento gerado pelo sistema SIE Matrícula</span>
            <span>Emitido em {{ dataEmissao }}</span>
        </footer>
    </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6 print:py-2">
            <div class="flex items-center justify-between mb-4 print:hidden">
                <h1 class="text-xl font-semibold">Resultado: Pendências de Encerramento e Renovação</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/pendencias-encerramento">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button variant="outline" @click="exportarCsv">CSV</Button>
                    <Button variant="outline" @click="exportarExcel">Excel</Button>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm grid gap-1 sm:grid-cols-2">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }}</div>
                <div><strong>Escola:</strong> {{ escopo }}</div>
                <div><strong>Segmento:</strong> {{ segmentoLabel }}</div>
                <div><strong>Escolas com pendência:</strong> {{ resumo.escolas_pendentes }} de {{ resumo.escolas_avaliadas }}</div>
            </div>

            <div class="grid gap-3 sm:grid-cols-4 mb-4 print:hidden">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Turmas sem encerramento</div>
                    <div class="text-2xl font-semibold tabular-nums text-amber-600">{{ resumo.sem_encerramento }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Turmas sem renovação</div>
                    <div class="text-2xl font-semibold tabular-nums text-rose-600">{{ resumo.sem_renovacao }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Total pendente</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ resumo.total_pendente }} <span class="text-sm font-normal text-muted-foreground">/ {{ resumo.total_turmas }}</span></div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Escolas em dia</div>
                    <div class="text-2xl font-semibold tabular-nums text-emerald-600">{{ escolasEmDia }}</div>
                </div>
            </div>

            <div v-if="!linhas.length" class="rounded-xl border border-emerald-300 bg-emerald-50 py-12 text-center text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                <strong>Nenhuma pendência encontrada.</strong>
                <div class="text-sm mt-1">Todas as {{ resumo.escolas_avaliadas }} escolas avaliadas já encerraram suas turmas e renovaram as matrículas.</div>
            </div>

            <div v-else-if="detalhado" class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="min-width: 1180px">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Escola</th>
                                <th class="px-3 py-2 text-left">Código</th>
                                <th class="px-3 py-2 text-left">Segmento</th>
                                <th class="px-3 py-2 text-left">Ano Escolar / Série</th>
                                <th class="px-3 py-2 text-left">Turma</th>
                                <th class="px-3 py-2 text-left">Turno</th>
                                <th class="px-3 py-2 text-left">Situação</th>
                                <th class="px-3 py-2 text-left">Pendência</th>
                                <th class="px-3 py-2 text-center">Alunos</th>
                                <th class="px-3 py-2 text-center">Renovados</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(d, i) in linhasDetalhe" :key="`d-${d.turma.tur_id}`">
                                <td class="px-3 py-1.5 text-muted-foreground tabular-nums">{{ i + 1 }}</td>
                                <td class="px-3 py-1.5 break-words">{{ d.escola.esc_nome }}</td>
                                <td class="px-3 py-1.5 tabular-nums">{{ d.escola.esc_codigo ?? '—' }}</td>
                                <td class="px-3 py-1.5">{{ d.turma.segmento ?? '—' }}</td>
                                <td class="px-3 py-1.5">{{ d.turma.serie ?? '—' }}</td>
                                <td class="px-3 py-1.5 font-medium">{{ d.turma.turma ?? '—' }}</td>
                                <td class="px-3 py-1.5">{{ d.turma.turno ?? '—' }}</td>
                                <td class="px-3 py-1.5">{{ d.turma.situacao ?? '—' }}</td>
                                <td class="px-3 py-1.5">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="d.turma.pendencia === 'Sem encerramento'
                                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'
                                            : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200'"
                                    >{{ d.turma.pendencia }}</span>
                                </td>
                                <td class="px-3 py-1.5 text-center tabular-nums">{{ d.turma.alunos }}</td>
                                <td class="px-3 py-1.5 text-center tabular-nums">{{ d.turma.renovados }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t bg-muted/30 px-4 py-2 text-sm font-medium">
                    Total de turmas pendentes: {{ linhasDetalhe.length }}
                </div>
            </div>

            <div v-else class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="min-width: 1080px">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Escola</th>
                                <th class="px-3 py-2 text-left">Código</th>
                                <th class="px-3 py-2 text-center">Turmas sem Encerramento</th>
                                <th class="px-3 py-2 text-center">Turmas sem Renovação</th>
                                <th class="px-3 py-2 text-center">Total Pendente</th>
                                <th class="px-3 py-2 text-center">Total de Turmas</th>
                                <th class="px-3 py-2 text-left">Segmentos / Anos Escolares Afetados</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(l, i) in linhas" :key="l.esc_id">
                                <td class="px-3 py-1.5 align-top text-muted-foreground tabular-nums">{{ i + 1 }}</td>
                                <td class="px-3 py-1.5 align-top font-medium break-words">{{ l.esc_nome }}</td>
                                <td class="px-3 py-1.5 align-top tabular-nums">{{ l.esc_codigo ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums">
                                    <span v-if="l.sem_encerramento" class="font-semibold text-amber-600">{{ l.sem_encerramento }}</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums">
                                    <span v-if="l.sem_renovacao" class="font-semibold text-rose-600">{{ l.sem_renovacao }}</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums font-semibold">{{ l.total_pendente }}</td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums text-muted-foreground">{{ l.total_turmas }}</td>
                                <td class="px-3 py-1.5 align-top break-words text-xs">{{ l.afetados.join(' / ') || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t bg-muted/30 px-4 py-2 text-sm font-medium">
                    {{ resumo.escolas_pendentes }} escola(s) com pendência · {{ resumo.total_pendente }} turma(s) pendente(s)
                </div>
            </div>
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
        margin-top: 0;
        border-bottom: 2px solid #000;
    }
    .brasao img, .logomarca img { max-width: 105px; max-height: 105px; object-fit: contain; }
    .cab-textos { text-align: center; line-height: 1.35; }
    .cab-linha { font-size: 10pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .cab-entidade { font-size: 12pt; font-weight: 700; text-transform: uppercase; margin: 1px 0; }
    .cab-end { font-size: 9pt; font-style: italic; }

    .titulo-impressao {
        text-align: center;
        margin: 12px 0 8px;
        border-bottom: 1px solid #000;
        padding-bottom: 6px;
    }
    .titulo-impressao h1 {
        font-size: 13pt;
        font-weight: 700;
        margin: 0 0 4px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }
    .subtitulo-impressao {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 14px;
        font-size: 9pt;
    }

    .tabela-impressao {
        width: 100%;
        border-collapse: collapse;
        /* Larguras vêm do <colgroup> em %, pois o nº de colunas muda com o formato. */
        table-layout: fixed;
        font-size: 8pt;
        border: 1px solid #000;
        margin-top: 8px;
    }
    .tabela-impressao th {
        background: transparent;
        color: #000;
        padding: 4px 5px;
        border: 1px solid #000;
        text-align: left;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 7.5pt;
        letter-spacing: 0.3px;
        overflow-wrap: break-word;
    }
    .tabela-impressao td {
        padding: 3px 4px;
        border: 1px solid #000;
        vertical-align: top;
        overflow-wrap: break-word;
        word-break: break-word;
    }
    .tabela-impressao .centro { text-align: center; }
    .tabela-impressao .celula-nome { font-weight: 600; }

    .resumo-impressao {
        border: 1px solid #000;
        border-top: none;
        padding: 3px 8px;
        font-size: 9pt;
        font-weight: 700;
        display: flex;
        gap: 18px;
        flex-wrap: wrap;
    }

    .rodape-impressao {
        margin-top: 24px;
        padding-top: 6px;
        border-top: 1px solid #000;
        font-size: 8.5pt;
        display: flex;
        justify-content: space-between;
        font-style: italic;
    }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
