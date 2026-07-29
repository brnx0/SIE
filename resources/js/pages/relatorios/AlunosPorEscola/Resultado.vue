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
    aln_id: number | null;
    nome: string | null;
    serie: string | null;
    turma: string | null;
    cpf: string | null;
    sexo: string | null;
    endereco: string | null;
    deficiencia: string | null;
    dt_nascimento: string | null;
    mae: string | null;
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
    filtros: { anl_id: number; anl_ano: number; esc_id: number; esc_nome: string };
    linhas: Linha[];
    total: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alunos por Escola', href: '/relatorios/alunos-por-escola' },
];

const formatCpf = (cpf: string | null) => {
    if (!cpf) return '—';
    const d = cpf.replace(/\D/g, '');
    return d.length === 11 ? `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9)}` : cpf;
};

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});

const isActiveTab = useIsActiveTab();
const imprimir = () => window.print();

const exportarCsv = () => {
    const headers = ['Nome', 'Série', 'Turma', 'CPF', 'Sexo', 'Endereço', 'Deficiência', 'Data de Nascimento', 'Nome da Mãe'];
    const sep = ';';
    const linhas = props.linhas.map(l => [
        l.nome ?? '', l.serie ?? '', l.turma ?? '', formatCpf(l.cpf), l.sexo ?? '',
        l.endereco ?? '', l.deficiencia ?? '', l.dt_nascimento ?? '', l.mae ?? '',
    ].map(v => `"${String(v).replace(/"/g, '""')}"`).join(sep));
    const csv = '﻿' + [headers.join(sep), ...linhas].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `alunos-por-escola-${props.filtros.anl_ano}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const exportarExcel = () => {
    const p = new URLSearchParams();
    p.set('esc_id', String(props.filtros.esc_id));
    window.open(`/relatorios/alunos-por-escola/xlsx?${p.toString()}`, '_blank');
};
</script>

<template>
    <Head title="Resultado — Alunos por Escola" />

    <!-- Bloco exclusivo p/ impressão -->
    <Teleport to="body" :disabled="!isActiveTab">
    <div id="print-area">
        <RelatorioCabecalho :parametros="parametros" />

        <div class="titulo-impressao">
            <h1>Relatório de Alunos por Escola</h1>
            <div class="subtitulo-impressao">
                <span><b>Ano Letivo:</b> {{ filtros.anl_ano }}</span>
                <span><b>Escola:</b> {{ filtros.esc_nome }}</span>
                <span><b>Total:</b> {{ total }} aluno(s)</span>
            </div>
        </div>

        <table class="tabela-impressao">
            <colgroup>
                <col class="w-num" />
                <col class="w-nome" />
                <col class="w-serie" />
                <col class="w-turma" />
                <col class="w-cpf" />
                <col class="w-sexo" />
                <col class="w-endereco" />
                <col class="w-defic" />
                <col class="w-nasc" />
                <col class="w-mae" />
            </colgroup>
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th>Nome</th>
                    <th>Série</th>
                    <th>Turma</th>
                    <th>CPF</th>
                    <th class="centro">Sexo</th>
                    <th>Endereço</th>
                    <th class="centro">Defic.</th>
                    <th class="centro">Nascimento</th>
                    <th>Nome da Mãe</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(a, i) in linhas" :key="`p-${a.aln_id ?? i}`">
                    <td class="col-num">{{ i + 1 }}</td>
                    <td class="celula-nome">{{ a.nome ?? '—' }}</td>
                    <td>{{ a.serie ?? '—' }}</td>
                    <td>{{ a.turma ?? '—' }}</td>
                    <td>{{ formatCpf(a.cpf) }}</td>
                    <td class="centro">{{ a.sexo ?? '—' }}</td>
                    <td>{{ a.endereco ?? '—' }}</td>
                    <td class="centro">{{ a.deficiencia ?? '—' }}</td>
                    <td class="centro">{{ a.dt_nascimento ?? '—' }}</td>
                    <td>{{ a.mae ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
        <div class="resumo-sexo-impressao">
            <span><b>Total de alunos:</b> {{ total }}</span>
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
                <h1 class="text-xl font-semibold">Resultado: Alunos por Escola</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/alunos-por-escola">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button variant="outline" @click="exportarCsv">CSV</Button>
                    <Button variant="outline" @click="exportarExcel">Excel</Button>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }}</div>
                <div><strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Total de alunos:</strong> {{ total }}</div>
            </div>

            <div v-if="!linhas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum aluno encontrado.
            </div>

            <div v-else class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full table-fixed text-sm" style="min-width: 1280px">
                        <colgroup>
                            <col style="width: 48px" />
                            <col style="width: 260px" />
                            <col style="width: 100px" />
                            <col style="width: 90px" />
                            <col style="width: 130px" />
                            <col style="width: 60px" />
                            <col style="width: 300px" />
                            <col style="width: 80px" />
                            <col style="width: 110px" />
                            <col style="width: 240px" />
                        </colgroup>
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Nome</th>
                                <th class="px-3 py-2 text-left">Série</th>
                                <th class="px-3 py-2 text-left">Turma</th>
                                <th class="px-3 py-2 text-left">CPF</th>
                                <th class="px-3 py-2 text-center">Sexo</th>
                                <th class="px-3 py-2 text-left">Endereço</th>
                                <th class="px-3 py-2 text-center">Defic.</th>
                                <th class="px-3 py-2 text-center">Nascimento</th>
                                <th class="px-3 py-2 text-left">Nome da Mãe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(a, i) in linhas" :key="a.aln_id ?? i">
                                <td class="px-3 py-1.5 text-muted-foreground tabular-nums">{{ i + 1 }}</td>
                                <td class="px-3 py-1.5 font-medium break-words">{{ a.nome ?? '—' }}</td>
                                <td class="px-3 py-1.5 break-words">{{ a.serie ?? '—' }}</td>
                                <td class="px-3 py-1.5 break-words">{{ a.turma ?? '—' }}</td>
                                <td class="px-3 py-1.5 tabular-nums">{{ formatCpf(a.cpf) }}</td>
                                <td class="px-3 py-1.5 text-center">{{ a.sexo ?? '—' }}</td>
                                <td class="px-3 py-1.5 break-words">{{ a.endereco ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-center">{{ a.deficiencia ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-center tabular-nums">{{ a.dt_nascimento ?? '—' }}</td>
                                <td class="px-3 py-1.5 break-words">{{ a.mae ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t bg-muted/30 px-4 py-2 text-sm font-medium">
                    Total de alunos: {{ total }}
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
        /* Colunas de tamanho fixo: respeita as larguras do <colgroup>. */
        table-layout: fixed;
        font-size: 8pt;
        border: 1px solid #000;
        margin-top: 8px;
    }

    /* Larguras fixas — soma ≈ 281mm (A4 paisagem útil ≈ 281mm). */
    .tabela-impressao .w-num      { width: 8mm; }
    .tabela-impressao .w-nome     { width: 48mm; }
    .tabela-impressao .w-serie    { width: 22mm; }
    .tabela-impressao .w-turma    { width: 16mm; }
    .tabela-impressao .w-cpf      { width: 26mm; }
    .tabela-impressao .w-sexo     { width: 12mm; }
    .tabela-impressao .w-endereco { width: 56mm; }
    .tabela-impressao .w-defic    { width: 14mm; }
    .tabela-impressao .w-nasc     { width: 25mm; }
    .tabela-impressao .w-mae      { width: 52mm; }
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
        /* Coluna fixa: quebra o texto em vez de estourar a largura. */
        overflow-wrap: break-word;
        word-break: break-word;
    }
    /* Cabeçalhos nunca quebram (ex.: o "O" de SEXO/NASCIMENTO caía p/ a linha
       de baixo); as larguras acima já comportam os títulos numa única linha. */
    .tabela-impressao th { white-space: nowrap; }
    .tabela-impressao .centro { text-align: center; }
    .tabela-impressao .col-num { text-align: center; }
    .tabela-impressao .celula-nome { font-weight: 600; }

    .resumo-sexo-impressao {
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
