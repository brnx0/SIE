<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import RelatorioCabecalho from '@/components/relatorios/RelatorioCabecalho.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { useIsActiveTab } from '@/composables/useIsActiveTab';

interface Linha {
    aln_id: number;
    esc_id: number;
    esc_nome: string;
    esc_codigo: string | null;
    tur_id: number;
    turma: string;
    serie: string | null;
    segmento: string | null;
    matricula: string | null;
    nome: string;
    dt_nascimento: string | null;
    idade: number | null;
    idade_esperada: number | null;
    distorcao: number | null;
}

interface Filtros {
    anl_id: number;
    anl_ano: number;
    esc_id: number | null;
    esc_nome: string | null;
    tur_id: number | null;
    tur_nome: string | null;
    ser_id: number | null;
    ser_nome: string | null;
    data_ref: string;
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
        total: number;
        escolas: number;
        turmas: number;
        distorcao_max: number | null;
        avaliados: number;
        sem_referencia: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Distorção Idade-Série', href: '/relatorios/distorcao-idade' },
];

const filtrosAtivos = computed(() => {
    const f = [
        `Ano ${props.filtros.anl_ano}`,
        `Idade acima da recomendada em ${props.filtros.data_ref}`,
        props.filtros.esc_nome ?? 'Todas as escolas',
    ];
    if (props.filtros.ser_nome) f.push(`Série: ${props.filtros.ser_nome}`);
    if (props.filtros.tur_nome) f.push(`Turma: ${props.filtros.tur_nome}`);
    return f;
});

/** Larguras do print (%) — soma 100. */
const larguras = ['20%', '13%', '11%', '9%', '21%', '9%', '6%', '6%', '5%'];

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
    if (props.filtros.tur_id) p.set('tur_id', String(props.filtros.tur_id));
    if (props.filtros.ser_id) p.set('ser_id', String(props.filtros.ser_id));
    return p;
};

const exportarCsv = () => {
    const sep = ';';
    const aspas = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;

    const headers = [
        'Escola', 'Cód. Escola', 'Turma', 'Ano Escolar / Série', 'Segmento',
        'Matrícula', 'Nome do Aluno', 'Data de Nascimento', 'Idade Atual',
        'Idade Esperada', 'Distorção (anos)',
    ];

    const corpo = props.linhas.map((l) => [
        l.esc_nome, l.esc_codigo ?? '', l.turma, l.serie ?? '', l.segmento ?? '',
        l.matricula ?? '', l.nome, l.dt_nascimento ?? '', l.idade ?? '',
        l.idade_esperada ?? '', l.distorcao ?? '',
    ]);

    const csv = '﻿' + [headers.join(sep), ...corpo.map((r) => r.map(aspas).join(sep))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `distorcao-idade-serie-${props.filtros.anl_ano}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const exportarExcel = () => window.open(`/relatorios/distorcao-idade/xlsx?${queryFiltros().toString()}`, '_blank');
</script>

<template>
    <Head title="Resultado — Distorção Idade-Série" />

    <!-- Bloco exclusivo p/ impressão -->
    <Teleport to="body" :disabled="!isActiveTab">
    <div id="print-area">
        <RelatorioCabecalho :parametros="parametros" />

        <div class="titulo-impressao">
            <h1>Distorção Idade-Série</h1>
            <div class="subtitulo-impressao">
                <span v-for="(f, i) in filtrosAtivos" :key="`f-${i}`">{{ f }}</span>
                <span><b>Total:</b> {{ resumo.total }} aluno(s)</span>
            </div>
        </div>

        <table class="tabela-impressao">
            <colgroup>
                <col v-for="(w, i) in larguras" :key="`c-${i}`" :style="{ width: w }" />
            </colgroup>
            <thead>
                <tr>
                    <th>Escola</th>
                    <th>Turma</th>
                    <th>Ano Escolar</th>
                    <th>Matrícula</th>
                    <th>Nome do Aluno</th>
                    <th class="centro">Nascimento</th>
                    <th class="centro">Idade</th>
                    <th class="centro">Esperada</th>
                    <th class="centro">Dist.</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="l in linhas" :key="`p-${l.aln_id}`">
                    <td class="celula-nome">{{ l.esc_nome }}</td>
                    <td>{{ l.turma }}</td>
                    <td>{{ l.serie ?? '—' }}</td>
                    <td>{{ l.matricula ?? '—' }}</td>
                    <td class="celula-nome">{{ l.nome }}</td>
                    <td class="centro">{{ l.dt_nascimento ?? '—' }}</td>
                    <td class="centro">{{ l.idade ?? '—' }}</td>
                    <td class="centro">{{ l.idade_esperada ?? '—' }}</td>
                    <td class="centro">+{{ l.distorcao ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="resumo-impressao">
            <span><b>Total:</b> {{ resumo.total }}</span>
            <span><b>Escolas:</b> {{ resumo.escolas }}</span>
            <span><b>Turmas:</b> {{ resumo.turmas }}</span>
            <span v-if="resumo.distorcao_max !== null"><b>Distorção máx.:</b> +{{ resumo.distorcao_max }} anos</span>
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
                <h1 class="text-xl font-semibold">Resultado: Distorção Idade-Série</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/distorcao-idade">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button variant="outline" @click="exportarCsv">CSV</Button>
                    <Button variant="outline" @click="exportarExcel">Excel</Button>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 flex flex-wrap gap-2">
                <span v-for="(f, i) in filtrosAtivos" :key="`fa-${i}`" class="rounded-full bg-muted px-3 py-0.5 text-xs">{{ f }}</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-4 print:hidden">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Alunos em distorção</div>
                    <div class="text-2xl font-semibold tabular-nums text-indigo-600">{{ resumo.total }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Escolas envolvidas</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ resumo.escolas }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Turmas envolvidas</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ resumo.turmas }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Distorção máxima</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ resumo.distorcao_max !== null ? '+' + resumo.distorcao_max : '—' }}</div>
                </div>
            </div>

            <div
                v-if="resumo.sem_referencia"
                class="rounded-xl border bg-card p-3 shadow-sm mb-4 text-sm text-muted-foreground print:hidden"
            >
                <strong class="text-foreground">{{ resumo.sem_referencia }}</strong> aluno(s) estão em séries sem idade
                recomendada cadastrada e não puderam ser avaliados quanto à distorção.
            </div>

            <div v-if="!linhas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum aluno em distorção idade-série para os filtros selecionados.
            </div>

            <div v-else class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="min-width: 1300px">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Escola</th>
                                <th class="px-3 py-2 text-left">Turma</th>
                                <th class="px-3 py-2 text-left">Ano Escolar / Série</th>
                                <th class="px-3 py-2 text-left">Segmento</th>
                                <th class="px-3 py-2 text-left">Matrícula</th>
                                <th class="px-3 py-2 text-left">Nome do Aluno</th>
                                <th class="px-3 py-2 text-center">Nascimento</th>
                                <th class="px-3 py-2 text-center">Idade Atual</th>
                                <th class="px-3 py-2 text-center">Idade Esperada</th>
                                <th class="px-3 py-2 text-center">Distorção</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(l, i) in linhas" :key="l.aln_id">
                                <td class="px-3 py-1.5 text-muted-foreground tabular-nums">{{ i + 1 }}</td>
                                <td class="px-3 py-1.5 break-words">{{ l.esc_nome }}</td>
                                <td class="px-3 py-1.5 break-words">{{ l.turma }}</td>
                                <td class="px-3 py-1.5">{{ l.serie ?? '—' }}</td>
                                <td class="px-3 py-1.5">{{ l.segmento ?? '—' }}</td>
                                <td class="px-3 py-1.5 tabular-nums">{{ l.matricula ?? '—' }}</td>
                                <td class="px-3 py-1.5 font-medium break-words">{{ l.nome }}</td>
                                <td class="px-3 py-1.5 text-center tabular-nums">{{ l.dt_nascimento ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-center tabular-nums font-semibold">{{ l.idade ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-center tabular-nums">{{ l.idade_esperada ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-center">
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                                        +{{ l.distorcao }} ano(s)
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t bg-muted/30 px-4 py-2 text-sm font-medium">
                    {{ resumo.total }} aluno(s) em distorção · {{ resumo.escolas }} escola(s) · {{ resumo.turmas }} turma(s)
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
