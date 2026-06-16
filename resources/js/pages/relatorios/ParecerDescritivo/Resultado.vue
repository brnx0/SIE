<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Circle, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface Parecer {
    periodo: string;
    disciplina: string | null;
    descricao: string;
}
interface Linha {
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: string | null;
    pareceres: Parecer[];
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
    filtros: { anl_ano: number; esc_nome: string; turma: string; modo: string | null; disciplina: string | null; aluno: string | null; periodo: string | null };
    linhas: Linha[];
    total: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Parecer Descritivo', href: '/relatorios/parecer-descritivo' },
];

const preenchidos = computed(() => props.linhas.filter((l) => l.pareceres.length > 0).length);

const modoLabel = computed(() =>
    props.filtros.modo === 'por_aluno'
        ? 'Parecer por aluno'
        : props.filtros.modo === 'por_disciplina'
          ? 'Parecer por disciplina'
          : '—',
);

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});

const imprimir = () => window.print();

const exportarCsv = () => {
    const headers = ['Nº', 'Aluno', 'Matrícula', 'Período', 'Disciplina', 'Parecer'];
    const sep = ';';
    const rows: string[] = [];
    props.linhas.forEach((l, i) => {
        if (!l.pareceres.length) {
            rows.push([i + 1, l.aln_nome, l.aln_nr_matricula ?? '', '', '', 'NÃO PREENCHIDO']
                .map((v) => `"${String(v).replace(/"/g, '""')}"`).join(sep));
        } else {
            l.pareceres.forEach((p) => {
                rows.push([i + 1, l.aln_nome, l.aln_nr_matricula ?? '', p.periodo, p.disciplina ?? '', p.descricao]
                    .map((v) => `"${String(v).replace(/"/g, '""')}"`).join(sep));
            });
        }
    });
    const csv = '﻿' + [headers.join(sep), ...rows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `parecer-descritivo-${props.filtros.anl_ano}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <Head title="Resultado — Parecer Descritivo" />

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
            <h1>Relatório de Parecer Descritivo</h1>
            <div class="subtitulo-impressao">
                <span><b>Ano Letivo:</b> {{ filtros.anl_ano }}</span>
                <span><b>Escola:</b> {{ filtros.esc_nome }}</span>
                <span><b>Turma:</b> {{ filtros.turma }}</span>
                <span v-if="filtros.periodo"><b>Período:</b> {{ filtros.periodo }}</span>
                <span v-if="filtros.disciplina"><b>Disciplina:</b> {{ filtros.disciplina }}</span>
                <span v-if="filtros.aluno"><b>Aluno:</b> {{ filtros.aluno }}</span>
                <span><b>Total:</b> {{ total }} aluno(s)</span>
            </div>
        </div>

        <section v-for="(l, i) in linhas" :key="`p-${l.aln_id}`" class="parecer-aluno">
            <div class="parecer-nome">
                {{ i + 1 }}. {{ l.aln_nome }}<span v-if="l.aln_nr_matricula" class="parecer-mat"> — Matrícula {{ l.aln_nr_matricula }}</span>
            </div>
            <div v-if="!l.pareceres.length" class="parecer-vazio">— Parecer não preenchido —</div>
            <template v-else>
                <div v-for="(p, j) in l.pareceres" :key="j" class="parecer-bloco">
                    <div class="parecer-disc">{{ p.periodo }}<template v-if="p.disciplina"> · {{ p.disciplina }}</template></div>
                    <div class="parecer-texto">{{ p.descricao }}</div>
                </div>
            </template>
        </section>

        <footer class="rodape-impressao">
            <span>Documento gerado pelo sistema SIE Matrícula</span>
            <span>Emitido em {{ dataEmissao }}</span>
        </footer>
    </div>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6 print:py-2">
            <div class="mb-4 flex items-center justify-between print:hidden">
                <h1 class="text-xl font-semibold">Resultado: Parecer Descritivo</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/parecer-descritivo">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button variant="outline" @click="exportarCsv">CSV</Button>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 rounded-xl border bg-card p-4 text-sm shadow-sm">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }}</div>
                <div><strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Turma:</strong> {{ filtros.turma }} <span class="text-muted-foreground">· {{ modoLabel }}</span></div>
                <div v-if="filtros.periodo"><strong>Período:</strong> {{ filtros.periodo }}</div>
                <div v-if="filtros.disciplina"><strong>Disciplina:</strong> {{ filtros.disciplina }}</div>
                <div v-if="filtros.aluno"><strong>Aluno:</strong> {{ filtros.aluno }}</div>
                <div><strong>Preenchidos:</strong> {{ preenchidos }} de {{ total }}</div>
            </div>

            <div v-if="!linhas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum aluno ativo nesta turma.
            </div>

            <div class="flex flex-col gap-3">
                <div
                    v-for="(l, i) in linhas"
                    :key="l.aln_id"
                    class="rounded-xl border bg-card p-4 shadow-sm"
                >
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div class="text-sm font-semibold text-slate-900 dark:text-slate-50">
                            {{ i + 1 }}. {{ l.aln_nome }}
                            <span v-if="l.aln_nr_matricula" class="text-xs font-normal text-muted-foreground">· Mat. {{ l.aln_nr_matricula }}</span>
                        </div>
                        <span
                            v-if="l.pareceres.length"
                            class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-emerald-600"
                        >
                            <CheckCircle2 class="size-3.5" /> Preenchido
                        </span>
                        <span v-else class="inline-flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                            <Circle class="size-3.5" /> Não preenchido
                        </span>
                    </div>

                    <div v-if="l.pareceres.length" class="flex flex-col gap-3">
                        <div v-for="(p, j) in l.pareceres" :key="j">
                            <div class="mb-0.5 text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">
                                {{ p.periodo }}<template v-if="p.disciplina"> · {{ p.disciplina }}</template>
                            </div>
                            <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ p.descricao }}</p>
                        </div>
                    </div>
                    <p v-else class="text-sm italic text-muted-foreground">— Parecer não preenchido —</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 portrait; margin: 8mm 12mm 10mm 12mm; }
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

    .cabecalho-impressao {
        display: grid;
        grid-template-columns: 100px 1fr 100px;
        align-items: center;
        gap: 10px;
        padding-bottom: 4px;
        border-bottom: 2px solid #000;
    }
    .brasao img, .logomarca img { max-width: 95px; max-height: 95px; object-fit: contain; }
    .cab-textos { text-align: center; line-height: 1.35; }
    .cab-linha { font-size: 10pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .cab-entidade { font-size: 12pt; font-weight: 700; text-transform: uppercase; margin: 1px 0; }
    .cab-end { font-size: 9pt; font-style: italic; }

    .titulo-impressao { text-align: center; margin: 12px 0 8px; border-bottom: 1px solid #000; padding-bottom: 6px; }
    .titulo-impressao h1 { font-size: 13pt; font-weight: 700; margin: 0 0 4px; text-transform: uppercase; letter-spacing: 1.5px; }
    .subtitulo-impressao { display: flex; justify-content: center; flex-wrap: wrap; gap: 14px; font-size: 9pt; }

    /* Bloco por aluno */
    .parecer-aluno { margin: 10px 0; page-break-inside: avoid; }
    .parecer-nome {
        font-size: 10.5pt;
        font-weight: 700;
        border-bottom: 1px solid #000;
        padding-bottom: 2px;
        margin-bottom: 4px;
    }
    .parecer-mat { font-weight: 400; font-size: 9pt; }
    .parecer-bloco { margin: 4px 0 6px; }
    .parecer-disc { font-size: 9pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
    .parecer-texto { font-size: 10.5pt; text-align: justify; line-height: 1.45; white-space: pre-wrap; }
    .parecer-vazio { font-size: 9.5pt; font-style: italic; color: #333; }

    .rodape-impressao {
        margin-top: 24px;
        padding-top: 6px;
        border-top: 1px solid #000;
        font-size: 8.5pt;
        display: flex;
        justify-content: space-between;
        font-style: italic;
    }
}
</style>
