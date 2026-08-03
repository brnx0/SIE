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
    nome: string | null;
    matricula: string | null;
    cargo: string | null;
    escolaridade: string | null;
    unidade: string | null;
    situacao: string;
    ativo: boolean;
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
    filtros: { esc_id: number | null; esc_nome: string | null; crg_id: number | null; crg_nome: string | null; situacao: string | null };
    linhas: Linha[];
    total: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Relação de Funcionários', href: '/relatorios/relacao-funcionarios' },
];

const situacaoLabel = (s: string | null) => (s === 'ativo' ? 'Ativos' : s === 'inativo' ? 'Inativos' : 'Todos');

const filtrosAtivos = computed(() => {
    const f: string[] = [];
    f.push(`Unidade: ${props.filtros.esc_nome ?? 'Todas'}`);
    f.push(`Cargo: ${props.filtros.crg_nome ?? 'Todos'}`);
    f.push(`Situação: ${situacaoLabel(props.filtros.situacao)}`);
    return f;
});

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});

const isActiveTab = useIsActiveTab();
const imprimir = () => window.print();

const exportarCsv = () => {
    const headers = ['Nome', 'Matrícula', 'Cargo/Função', 'Escolaridade', 'Unidade de Lotação', 'Situação'];
    const sep = ';';
    const linhas = props.linhas.map((l) => [
        l.nome ?? '', l.matricula ?? '', l.cargo ?? '', l.escolaridade ?? '', l.unidade ?? '', l.situacao,
    ].map((v) => `"${String(v).replace(/"/g, '""')}"`).join(sep));
    const csv = '﻿' + [headers.join(sep), ...linhas].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'relacao-funcionarios.csv';
    a.click();
    URL.revokeObjectURL(url);
};

const exportarExcel = () => {
    const p = new URLSearchParams();
    if (props.filtros.esc_id) p.set('esc_id', String(props.filtros.esc_id));
    if (props.filtros.crg_id) p.set('crg_id', String(props.filtros.crg_id));
    if (props.filtros.situacao) p.set('situacao', props.filtros.situacao);
    window.open(`/relatorios/relacao-funcionarios/xlsx?${p.toString()}`, '_blank');
};
</script>

<template>
    <Head title="Resultado — Relação de Funcionários" />

    <!-- Bloco exclusivo p/ impressão -->
    <Teleport to="body" :disabled="!isActiveTab">
    <div id="print-area">
        <RelatorioCabecalho :parametros="parametros" />

        <div class="titulo-impressao">
            <h1>Relação de Funcionários</h1>
            <div class="subtitulo-impressao">
                <span v-for="(f, i) in filtrosAtivos" :key="`f-${i}`">{{ f }}</span>
                <span><b>Total:</b> {{ total }} funcionário(s)</span>
            </div>
        </div>

        <table class="tabela-impressao">
            <colgroup>
                <col class="w-num" />
                <col class="w-nome" />
                <col class="w-mat" />
                <col class="w-cargo" />
                <col class="w-escol" />
                <col class="w-unid" />
                <col class="w-sit" />
            </colgroup>
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th>Nome</th>
                    <th>Matrícula</th>
                    <th>Cargo / Função</th>
                    <th>Escolaridade</th>
                    <th>Unidade de Lotação</th>
                    <th class="centro">Situação</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(l, i) in linhas" :key="`p-${i}`">
                    <td class="col-num">{{ i + 1 }}</td>
                    <td class="celula-nome">{{ l.nome ?? '—' }}</td>
                    <td>{{ l.matricula ?? '—' }}</td>
                    <td>{{ l.cargo ?? '—' }}</td>
                    <td>{{ l.escolaridade ?? '—' }}</td>
                    <td>{{ l.unidade ?? '—' }}</td>
                    <td class="centro">{{ l.situacao }}</td>
                </tr>
            </tbody>
        </table>
        <div class="resumo-impressao">
            <span><b>Total de funcionários:</b> {{ total }}</span>
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
                <h1 class="text-xl font-semibold">Resultado: Relação de Funcionários</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/relacao-funcionarios">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button variant="outline" @click="exportarCsv">CSV</Button>
                    <Button variant="outline" @click="exportarExcel">Excel</Button>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <div><strong>Unidade:</strong> {{ filtros.esc_nome ?? 'Todas' }}</div>
                <div><strong>Cargo:</strong> {{ filtros.crg_nome ?? 'Todos' }}</div>
                <div><strong>Situação:</strong> {{ situacaoLabel(filtros.situacao) }}</div>
                <div><strong>Total:</strong> {{ total }}</div>
            </div>

            <div v-if="!linhas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum funcionário encontrado.
            </div>

            <div v-else class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="min-width: 1100px">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Nome</th>
                                <th class="px-3 py-2 text-left whitespace-nowrap">Matrícula</th>
                                <th class="px-3 py-2 text-left">Cargo / Função</th>
                                <th class="px-3 py-2 text-left">Escolaridade</th>
                                <th class="px-3 py-2 text-left">Unidade de Lotação</th>
                                <th class="px-3 py-2 text-center whitespace-nowrap">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(l, i) in linhas" :key="i" :class="!l.ativo ? 'bg-muted/20' : ''">
                                <td class="px-3 py-1.5 align-top text-muted-foreground tabular-nums">{{ i + 1 }}</td>
                                <td class="px-3 py-1.5 align-top font-medium break-words">{{ l.nome ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top tabular-nums break-words">{{ l.matricula ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top break-words">{{ l.cargo ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top break-words">{{ l.escolaridade ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top break-words">{{ l.unidade ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top text-center whitespace-nowrap">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="l.ativo
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                                            : 'bg-muted text-muted-foreground'"
                                    >{{ l.situacao }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t bg-muted/30 px-4 py-2 text-sm font-medium">
                    Total de funcionários: {{ total }}
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

    /* Cabeçalho institucional (o componente RelatorioCabecalho só traz o markup;
       o dimensionamento vem daqui — sem isto o logo sai no tamanho natural). */
    .cabecalho-impressao {
        display: grid;
        grid-template-columns: 105px 1fr 105px;
        align-items: center;
        gap: 10px;
        padding-bottom: 4px;
        margin-top: 0;
        border-bottom: 2px solid #000;
    }
    .brasao img, .logomarca img { max-width: 100px; max-height: 100px; object-fit: contain; }
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
    /* Larguras fixas — soma ≈ 281mm (A4 paisagem útil). */
    .tabela-impressao .w-num   { width: 8mm; }
    .tabela-impressao .w-nome  { width: 60mm; }
    .tabela-impressao .w-mat   { width: 26mm; }
    .tabela-impressao .w-cargo { width: 52mm; }
    .tabela-impressao .w-escol { width: 48mm; }
    .tabela-impressao .w-unid  { width: 62mm; }
    .tabela-impressao .w-sit   { width: 18mm; }
    .tabela-impressao th {
        background: transparent;
        color: #000;
        padding: 4px 6px;
        border: 1px solid #000;
        text-align: left;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 8pt;
        letter-spacing: 0.3px;
    }
    .tabela-impressao td {
        padding: 3px 4px;
        border: 1px solid #000;
        vertical-align: top;
        overflow-wrap: break-word;
        word-break: break-word;
    }
    .tabela-impressao th { white-space: nowrap; }
    .tabela-impressao .centro { text-align: center; }
    .tabela-impressao .col-num { text-align: center; }
    .tabela-impressao .celula-nome { font-weight: 600; }

    .resumo-impressao {
        border: 1px solid #000;
        border-top: none;
        padding: 3px 8px;
        font-size: 9pt;
        font-weight: 700;
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
