<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface Linha {
    turma: string | null;
    tur_id: number | null;
    aln_id: number | null;
    nome: string | null;
    sexo: string | null;
    idade: number | null;
    usa_transporte: string;
    situacao: string;
    possui_deficiencia: string;
    dt_nascimento: string | null;
    cpf: string | null;
    cartao_sus: string | null;
    filiacao: string | null;
    contato: string | null;
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
    filtros: { anl_id: number; anl_ano: number; esc_id: number; esc_nome: string; tur_id: number | null; incluir_saidas: boolean };
    linhas: Linha[];
    total: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alunos por Turma', href: '/relatorios/alunos-por-turma' },
];

const grupos = computed(() => {
    const map = new Map<string, Linha[]>();
    for (const l of props.linhas) {
        const k = l.turma ?? '—';
        if (!map.has(k)) map.set(k, []);
        map.get(k)!.push(l);
    }
    return [...map.entries()];
});

const formatDate = (iso: string | null) => {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
};

const formatCpf = (cpf: string | null) => {
    if (!cpf) return '—';
    const d = cpf.replace(/\D/g, '');
    return d.length === 11 ? `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9)}` : cpf;
};

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});

const imprimir = () => window.print();

const contarSexos = (alunos: Linha[]) => {
    let m = 0, f = 0, o = 0;
    for (const a of alunos) {
        const s = (a.sexo ?? '').toUpperCase();
        if (s === 'M') m++;
        else if (s === 'F') f++;
        else o++;
    }
    return { m, f, o, total: alunos.length };
};

const exportarCsv = () => {
    const headers = ['Turma','Nome','Sexo','Idade','Transporte','Situação','Deficiência','Nascimento','CPF','Cartão SUS','Filiação','Contato'];
    const sep = ';';
    const linhas = props.linhas.map(l => [
        l.turma ?? '', l.nome ?? '', l.sexo ?? '', l.idade ?? '', l.usa_transporte,
        l.situacao, l.possui_deficiencia, formatDate(l.dt_nascimento), formatCpf(l.cpf),
        l.cartao_sus ?? '', l.filiacao ?? '', l.contato ?? '',
    ].map(v => `"${String(v).replace(/"/g, '""')}"`).join(sep));
    const csv = '﻿' + [headers.join(sep), ...linhas].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `alunos-por-turma-${props.filtros.anl_ano}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <Head title="Resultado — Alunos por Turma" />

    <!-- Bloco exclusivo p/ impressão -->
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
            <h1>Relatório de Alunos por Turma</h1>
            <div class="subtitulo-impressao">
                <span><b>Ano Letivo:</b> {{ filtros.anl_ano }}</span>
                <span><b>Escola:</b> {{ filtros.esc_nome }}</span>
                <span><b>Total:</b> {{ total }} aluno(s)</span>
                <span v-if="filtros.incluir_saidas" class="aviso">Inclui alunos com saída</span>
            </div>
        </div>

        <section v-for="[turma, alunos] in grupos" :key="`p-${turma}`" class="grupo-impressao">
            <div class="grupo-impressao-header">
                <span>Turma {{ turma }}</span>
                <span>{{ alunos.length }} aluno(s)</span>
            </div>
            <table class="tabela-impressao">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th>Nome</th>
                        <th>Sexo</th>
                        <th>Idade</th>
                        <th>Transp.</th>
                        <th>Situação</th>
                        <th>Defic.</th>
                        <th>Nascimento</th>
                        <th>CPF</th>
                        <th>Cartão SUS</th>
                        <th>Filiação</th>
                        <th>Contato</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(a, i) in alunos" :key="`p-${a.aln_id ?? i}`">
                        <td class="col-num">{{ i + 1 }}</td>
                        <td class="celula-nome">{{ a.nome ?? '—' }}</td>
                        <td class="centro">{{ a.sexo ?? '—' }}</td>
                        <td class="centro">{{ a.idade ?? '—' }}</td>
                        <td class="centro">{{ a.usa_transporte }}</td>
                        <td>{{ a.situacao }}</td>
                        <td class="centro">{{ a.possui_deficiencia }}</td>
                        <td class="centro">{{ formatDate(a.dt_nascimento) }}</td>
                        <td>{{ formatCpf(a.cpf) }}</td>
                        <td>{{ a.cartao_sus ?? '—' }}</td>
                        <td>{{ a.filiacao ?? '—' }}</td>
                        <td>{{ a.contato ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="resumo-sexo-impressao">
                <span><b>Total:</b> {{ contarSexos(alunos).total }}</span>
                <span><b>Masculino:</b> {{ contarSexos(alunos).m }}</span>
                <span><b>Feminino:</b> {{ contarSexos(alunos).f }}</span>
                <span v-if="contarSexos(alunos).o > 0"><b>Outros:</b> {{ contarSexos(alunos).o }}</span>
            </div>
        </section>

        <footer class="rodape-impressao">
            <span>Documento gerado pelo sistema SIE Matrícula</span>
            <span>Emitido em {{ dataEmissao }}</span>
        </footer>
    </div>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6 print:py-2">
            <div class="flex items-center justify-between mb-4 print:hidden">
                <h1 class="text-xl font-semibold">Resultado: Alunos por Turma</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/alunos-por-turma">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button variant="outline" @click="exportarCsv">CSV</Button>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }}</div>
                <div><strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Total alunos:</strong> {{ total }}</div>
                <div v-if="filtros.incluir_saidas" class="text-amber-700">Incluindo alunos com saída.</div>
            </div>

            <div v-if="!linhas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum aluno encontrado.
            </div>

            <div v-for="[turma, alunos] in grupos" :key="turma" class="mb-6 rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="border-b bg-indigo-50 px-4 py-2 font-semibold text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-200">
                    Turma {{ turma }} <span class="text-xs font-normal text-muted-foreground">({{ alunos.length }} aluno{{ alunos.length !== 1 ? 's' : '' }})</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-2 py-2 text-left">Nome</th>
                                <th class="px-2 py-2">Sexo</th>
                                <th class="px-2 py-2">Idade</th>
                                <th class="px-2 py-2">Transp.</th>
                                <th class="px-2 py-2">Situação</th>
                                <th class="px-2 py-2">Defic.</th>
                                <th class="px-2 py-2">Nascimento</th>
                                <th class="px-2 py-2">CPF</th>
                                <th class="px-2 py-2">SUS</th>
                                <th class="px-2 py-2 text-left">Filiação</th>
                                <th class="px-2 py-2 text-left">Contato</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(a, i) in alunos" :key="a.aln_id ?? i">
                                <td class="px-2 py-1.5 font-medium">{{ a.nome ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-center">{{ a.sexo ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-center tabular-nums">{{ a.idade ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-center">{{ a.usa_transporte }}</td>
                                <td class="px-2 py-1.5">{{ a.situacao }}</td>
                                <td class="px-2 py-1.5 text-center">{{ a.possui_deficiencia }}</td>
                                <td class="px-2 py-1.5 text-center">{{ formatDate(a.dt_nascimento) }}</td>
                                <td class="px-2 py-1.5">{{ formatCpf(a.cpf) }}</td>
                                <td class="px-2 py-1.5">{{ a.cartao_sus ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ a.filiacao ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ a.contato ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t bg-muted/30 px-4 py-2 text-xs flex gap-4">
                    <span><strong>Total:</strong> {{ contarSexos(alunos).total }}</span>
                    <span><strong>Masculino:</strong> {{ contarSexos(alunos).m }}</span>
                    <span><strong>Feminino:</strong> {{ contarSexos(alunos).f }}</span>
                    <span v-if="contarSexos(alunos).o > 0"><strong>Outros/Não informado:</strong> {{ contarSexos(alunos).o }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 landscape; margin: 3mm 10mm 8mm 10mm; }
    body * { visibility: hidden !important; }
    #print-area, #print-area * { visibility: visible !important; }
    #print-area {
        display: block !important;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        font-family: 'Times New Roman', Times, serif;
        color: #000;
        background: white;
    }

    /* Cabeçalho institucional formal */
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
    .cab-linha {
        font-size: 10pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .cab-entidade {
        font-size: 12pt;
        font-weight: 700;
        text-transform: uppercase;
        margin: 1px 0;
    }
    .cab-end { font-size: 9pt; font-style: italic; }

    /* Título do documento */
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
    .subtitulo-impressao .aviso { font-style: italic; }

    /* Bloco de turma */
    .grupo-impressao {
        margin: 10px 0 6px;
        page-break-inside: avoid;
    }
    .grupo-impressao-header {
        background: transparent;
        color: #000;
        padding: 3px 8px;
        display: flex;
        justify-content: space-between;
        font-size: 10pt;
        font-weight: 700;
        border: 1px solid #000;
        border-bottom: none;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Tabela formal */
    .tabela-impressao {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5pt;
        border: 1px solid #000;
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
    }
    .tabela-impressao td {
        padding: 3px 5px;
        border: 1px solid #000;
        vertical-align: top;
    }
    .resumo-sexo-impressao {
        border: 1px solid #000;
        border-top: none;
        padding: 3px 8px;
        display: flex;
        gap: 16px;
        font-size: 9pt;
    }
    .tabela-impressao .centro { text-align: center; }
    .tabela-impressao .col-num { width: 22px; text-align: center; }
    .tabela-impressao .celula-nome { font-weight: 600; width: 16%; }

    /* Rodapé com assinatura */
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
