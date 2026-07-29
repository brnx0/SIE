<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import RelatorioCabecalho from '@/components/relatorios/RelatorioCabecalho.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { useIsActiveTab } from '@/composables/useIsActiveTab';

interface Professor { nome: string; disciplina: string | null }

interface Linha {
    tur_id: number;
    esc_id: number;
    esc_nome: string;
    esc_codigo: string | null;
    tur_codigo: string | null;
    turma: string;
    modalidade: string;
    segmento: string | null;
    serie: string | null;
    turno: string | null;
    situacao: string | null;
    capacidade: number;
    expansao: number;
    matriculados: number;
    vagas: number | null;
    ocupacao: number | null;
    transferidos: number;
    evadidos: number;
    cancelados: number;
    total_vinculos: number;
    qt_professores: number;
    professores: Professor[];
    professores_texto: string;
    apoio: string[];
    apoio_texto: string;
    aulas: number;
    registros_chamada: number;
    faltas: number;
    freq_lancada: number | null;
}

interface Filtros {
    anl_id: number;
    anl_ano: number;
    esc_id: number | null;
    esc_nome: string | null;
    seg_id: number | null;
    seg_nome: string | null;
    ser_id: number | null;
    ser_nome: string | null;
    turno: string | null;
}

interface Parametros {
    nome_entidade: string;
    msg_cab_secretaria: string | null;
    msg_cab_estado: string | null;
    endereco: string | null;
    municipio: string | null;
    logomarca_url: string | null;
    brasao_url: string | null;
}

const props = defineProps<{
    parametros: Parametros | null;
    filtros: Filtros;
    linhas: Linha[];
    resumo: {
        escolas: number;
        turmas: number;
        matriculados: number;
        capacidade: number;
        vagas: number;
        ocupacao_media: number | null;
        professores: number;
        turmas_sem_prof: number;
        turmas_sem_aula: number;
        freq_media: number | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Levantamento do Município', href: '/relatorios/levantamento-municipio' },
];

const num = (v: number | null, suf = '') => (v === null ? '—' : `${v}${suf}`);

const filtrosAtivos = computed(() => {
    const f: string[] = [];
    f.push(`Ano ${props.filtros.anl_ano}`);
    f.push(props.filtros.esc_nome ?? 'Todas as escolas');
    if (props.filtros.seg_nome) f.push(props.filtros.seg_nome);
    if (props.filtros.ser_nome) f.push(props.filtros.ser_nome);
    if (props.filtros.turno) f.push(props.filtros.turno);
    return f;
});

/** Larguras do print (%) — subconjunto essencial das colunas da tela. */
const larguras = ['17%', '7%', '10%', '10%', '10%', '7%', '5%', '5%', '5%', '6%', '13%', '5%'];

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
    if (props.filtros.ser_id) p.set('ser_id', String(props.filtros.ser_id));
    if (props.filtros.turno) p.set('turno', props.filtros.turno);
    return p;
};

const exportarCsv = () => {
    const sep = ';';
    const aspas = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;

    const headers = [
        'Escola', 'Cód. Escola', 'Cód. Turma', 'Turma', 'Modalidade', 'Segmento', 'Ano Escolar / Série',
        'Turno', 'Situação da Turma', 'Capacidade', 'Expansão', 'Matriculados', 'Vagas', '% Ocupação',
        'Transferidos', 'Evadidos', 'Cancelados', 'Total de Vínculos',
        'Nº Professores', 'Professor(es) Responsável(is)', 'Professores de Apoio',
        'Aulas Registradas', 'Registros de Chamada', 'Faltas', 'Freq. Lançada (%)',
    ];

    const corpo = props.linhas.map((l) => [
        l.esc_nome, l.esc_codigo ?? '', l.tur_codigo ?? '', l.turma, l.modalidade,
        l.segmento ?? '', l.serie ?? '', l.turno ?? '', l.situacao ?? '',
        l.capacidade, l.expansao, l.matriculados, l.vagas ?? '', l.ocupacao ?? '',
        l.transferidos, l.evadidos, l.cancelados, l.total_vinculos,
        l.qt_professores, l.professores_texto, l.apoio_texto,
        l.aulas, l.registros_chamada, l.faltas, l.freq_lancada ?? '',
    ]);

    const csv = '﻿' + [headers.join(sep), ...corpo.map((r) => r.map(aspas).join(sep))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `levantamento-municipio-${props.filtros.anl_ano}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const exportarExcel = () => window.open(`/relatorios/levantamento-municipio/xlsx?${queryFiltros().toString()}`, '_blank');
</script>

<template>
    <Head title="Resultado — Levantamento do Município" />

    <!-- Bloco exclusivo p/ impressão -->
    <Teleport to="body" :disabled="!isActiveTab">
    <div id="print-area">
        <RelatorioCabecalho :parametros="parametros" />

        <div class="titulo-impressao">
            <h1>Levantamento de Dados do Município</h1>
            <div class="subtitulo-impressao">
                <span v-for="(f, i) in filtrosAtivos" :key="`f-${i}`">{{ f }}</span>
                <span><b>Turmas:</b> {{ resumo.turmas }}</span>
                <span><b>Matriculados:</b> {{ resumo.matriculados }}</span>
            </div>
        </div>

        <table class="tabela-impressao">
            <colgroup>
                <col v-for="(w, i) in larguras" :key="`c-${i}`" :style="{ width: w }" />
            </colgroup>
            <thead>
                <tr>
                    <th>Escola</th>
                    <th>Cód. Turma</th>
                    <th>Turma</th>
                    <th>Segmento</th>
                    <th>Ano Escolar</th>
                    <th>Turno</th>
                    <th class="centro">Cap.</th>
                    <th class="centro">Matr.</th>
                    <th class="centro">Vagas</th>
                    <th class="centro">% Ocup.</th>
                    <th>Professor(es)</th>
                    <th class="centro">Freq.</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="l in linhas" :key="`p-${l.tur_id}`">
                    <td class="celula-nome">{{ l.esc_nome }}</td>
                    <td>{{ l.tur_codigo ?? '—' }}</td>
                    <td class="celula-nome">{{ l.turma }}</td>
                    <td>{{ l.segmento ?? '—' }}</td>
                    <td>{{ l.serie ?? '—' }}</td>
                    <td>{{ l.turno ?? '—' }}</td>
                    <td class="centro">{{ l.capacidade + l.expansao }}</td>
                    <td class="centro">{{ l.matriculados }}</td>
                    <td class="centro">{{ num(l.vagas) }}</td>
                    <td class="centro">{{ num(l.ocupacao, '%') }}</td>
                    <td>{{ l.professores_texto || '—' }}</td>
                    <td class="centro">{{ num(l.freq_lancada, '%') }}</td>
                </tr>
            </tbody>
        </table>

        <div class="resumo-impressao">
            <span><b>Escolas:</b> {{ resumo.escolas }}</span>
            <span><b>Turmas:</b> {{ resumo.turmas }}</span>
            <span><b>Matriculados:</b> {{ resumo.matriculados }}</span>
            <span><b>Vagas:</b> {{ resumo.vagas }}</span>
            <span><b>Ocupação média:</b> {{ num(resumo.ocupacao_media, '%') }}</span>
            <span><b>Turmas sem professor:</b> {{ resumo.turmas_sem_prof }}</span>
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
                <h1 class="text-xl font-semibold">Resultado: Levantamento de Dados do Município</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/levantamento-municipio">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button variant="outline" @click="exportarCsv">CSV</Button>
                    <Button variant="outline" @click="exportarExcel">Excel</Button>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm flex flex-wrap gap-x-6 gap-y-1">
                <span v-for="(f, i) in filtrosAtivos" :key="`fa-${i}`" class="rounded-full bg-muted px-3 py-0.5 text-xs">{{ f }}</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6 mb-4 print:hidden">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Escolas</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ resumo.escolas }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Turmas</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ resumo.turmas }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Matriculados</div>
                    <div class="text-2xl font-semibold tabular-nums text-indigo-600">{{ resumo.matriculados }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Vagas livres</div>
                    <div class="text-2xl font-semibold tabular-nums text-emerald-600">{{ resumo.vagas }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Ocupação média</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ num(resumo.ocupacao_media, '%') }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Turmas sem professor</div>
                    <div class="text-2xl font-semibold tabular-nums" :class="resumo.turmas_sem_prof ? 'text-amber-600' : ''">{{ resumo.turmas_sem_prof }}</div>
                </div>
            </div>

            <div v-if="!linhas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhuma turma encontrada para os filtros selecionados.
            </div>

            <div v-else class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="min-width: 2000px">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Escola</th>
                                <th class="px-3 py-2 text-left">Cód. Escola</th>
                                <th class="px-3 py-2 text-left">Cód. Turma</th>
                                <th class="px-3 py-2 text-left">Turma</th>
                                <th class="px-3 py-2 text-left">Modalidade</th>
                                <th class="px-3 py-2 text-left">Segmento</th>
                                <th class="px-3 py-2 text-left">Ano Escolar / Série</th>
                                <th class="px-3 py-2 text-left">Turno</th>
                                <th class="px-3 py-2 text-center">Capac.</th>
                                <th class="px-3 py-2 text-center">Matric.</th>
                                <th class="px-3 py-2 text-center">Vagas</th>
                                <th class="px-3 py-2 text-center">% Ocup.</th>
                                <th class="px-3 py-2 text-center">Transf.</th>
                                <th class="px-3 py-2 text-center">Evad.</th>
                                <th class="px-3 py-2 text-left">Professor(es) Responsável(is)</th>
                                <th class="px-3 py-2 text-left">Apoio</th>
                                <th class="px-3 py-2 text-center">Aulas</th>
                                <th class="px-3 py-2 text-center">Freq. lançada</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(l, i) in linhas" :key="l.tur_id">
                                <td class="px-3 py-1.5 align-top text-muted-foreground tabular-nums">{{ i + 1 }}</td>
                                <td class="px-3 py-1.5 align-top break-words">{{ l.esc_nome }}</td>
                                <td class="px-3 py-1.5 align-top tabular-nums">{{ l.esc_codigo ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top tabular-nums">{{ l.tur_codigo ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top font-medium break-words">{{ l.turma }}</td>
                                <td class="px-3 py-1.5 align-top">{{ l.modalidade }}</td>
                                <td class="px-3 py-1.5 align-top">{{ l.segmento ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top">{{ l.serie ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top">{{ l.turno ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums">
                                    {{ l.capacidade + l.expansao }}
                                    <span v-if="l.expansao" class="block text-xs text-muted-foreground">(+{{ l.expansao }})</span>
                                </td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums font-semibold">{{ l.matriculados }}</td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums">{{ num(l.vagas) }}</td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums">
                                    <span :class="l.ocupacao !== null && l.ocupacao > 100 ? 'font-semibold text-rose-600' : ''">{{ num(l.ocupacao, '%') }}</span>
                                </td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums">{{ l.transferidos }}</td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums">{{ l.evadidos }}</td>
                                <td class="px-3 py-1.5 align-top break-words">
                                    <span v-if="!l.professores.length" class="text-amber-600">Sem professor alocado</span>
                                    <div v-for="(p, k) in l.professores" :key="`${l.tur_id}-p-${k}`">
                                        {{ p.nome }}
                                        <span v-if="p.disciplina" class="text-xs text-muted-foreground">({{ p.disciplina }})</span>
                                    </div>
                                </td>
                                <td class="px-3 py-1.5 align-top break-words text-xs text-muted-foreground">{{ l.apoio_texto || '—' }}</td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums">{{ l.aulas }}</td>
                                <td class="px-3 py-1.5 align-top text-center tabular-nums">{{ num(l.freq_lancada, '%') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t bg-muted/30 px-4 py-2 text-sm font-medium">
                    {{ resumo.turmas }} turma(s) em {{ resumo.escolas }} escola(s) ·
                    {{ resumo.matriculados }} matriculado(s) ·
                    {{ resumo.professores }} vínculo(s) de professor
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
        table-layout: fixed;
        font-size: 7.5pt;
        border: 1px solid #000;
        margin-top: 8px;
    }
    .tabela-impressao th {
        background: transparent;
        color: #000;
        padding: 4px 4px;
        border: 1px solid #000;
        text-align: left;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 7pt;
        letter-spacing: 0.2px;
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
        font-size: 8.5pt;
        font-weight: 700;
        display: flex;
        gap: 16px;
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
