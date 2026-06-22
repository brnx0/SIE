<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';

interface Avaliacao { dt: string; descricao: string }
interface AlunoLinha { aln_id: number; nome: string; matricula: string | null; situacao: string; avaliacoes: Avaliacao[] }
interface TurmaLinha { tur_id: number; turma: string | null; escola: string | null; alunos: AlunoLinha[] }
interface Parametros {
    nome_entidade: string;
    msg_cab_secretaria: string | null;
    msg_cab_estado: string | null;
    logomarca_url: string | null;
    brasao_url: string | null;
}

defineProps<{
    parametros: Parametros | null;
    anoLetivo: { anl_id: number; anl_ano: number };
    escola: { esc_id: number; esc_nome: string } | null;
    linhas: TurmaLinha[];
    total_turmas: number;
    total_alunos: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Desempenho AEE', href: '/relatorios/desempenho-aee' },
];

const fmtBr = (d: string) => {
    if (!d) return '—';
    const [y, m, day] = d.slice(0, 10).split('-');
    return `${day}/${m}/${y}`;
};

const imprimir = () => window.print();
</script>

<template>
    <Head title="Desempenho AEE" />

    <!-- Impressão -->
    <Teleport to="body">
        <div id="print-area">
            <header class="cab">
                <div class="logo"><img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" /></div>
                <div class="cab-textos">
                    <div v-if="parametros?.msg_cab_estado">{{ parametros.msg_cab_estado }}</div>
                    <div v-if="parametros?.msg_cab_secretaria">{{ parametros.msg_cab_secretaria }}</div>
                    <div class="entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
                </div>
                <div class="logo"><img v-if="parametros?.brasao_url" :src="parametros.brasao_url" /></div>
            </header>

            <h1 class="titulo">RELATÓRIO DE DESEMPENHO AEE — {{ anoLetivo.anl_ano }}</h1>
            <p v-if="escola" class="subtitulo">Escola: {{ escola.esc_nome }}</p>

            <div v-for="t in linhas" :key="t.tur_id" class="turma-bloco">
                <h2 class="turma-tit">{{ t.escola }} — Turma {{ t.turma }}</h2>
                <div v-for="al in t.alunos" :key="al.aln_id" class="aluno-bloco">
                    <div class="aluno-cab">
                        <span class="aluno-nome">{{ al.nome }}</span>
                        <span v-if="al.matricula"> — Matr. {{ al.matricula }}</span>
                        <span class="aluno-sit">Situação: {{ al.situacao }}</span>
                    </div>
                    <div v-if="al.avaliacoes.length === 0" class="sem-av">Sem avaliações registradas.</div>
                    <div v-for="(av, i) in al.avaliacoes" :key="i" class="av">
                        <div class="av-data">{{ fmtBr(av.dt) }}</div>
                        <div class="av-texto" v-html="av.descricao"></div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Desempenho AEE — {{ anoLetivo.anl_ano }}</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/desempenho-aee">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!total_turmas" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-3 mb-4">
                <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                    <div class="text-xs text-muted-foreground">Total turmas</div>
                    <div class="text-2xl font-bold">{{ total_turmas }}</div>
                </div>
                <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                    <div class="text-xs text-muted-foreground">Total alunos</div>
                    <div class="text-2xl font-bold text-indigo-600">{{ total_alunos }}</div>
                </div>
                <div v-if="escola" class="rounded-xl border bg-card p-3 text-center shadow-sm">
                    <div class="text-xs text-muted-foreground">Escola</div>
                    <div class="text-sm font-medium truncate">{{ escola.esc_nome }}</div>
                </div>
            </div>

            <div v-if="!total_turmas" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum aluno de turma AEE encontrado para o filtro selecionado.
            </div>

            <div v-else class="grid gap-6">
                <section v-for="t in linhas" :key="t.tur_id" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <header class="bg-fuchsia-600 px-4 py-2.5 text-white">
                        <h2 class="text-sm font-semibold">{{ t.escola }} — Turma {{ t.turma }}</h2>
                    </header>
                    <div class="divide-y">
                        <div v-for="al in t.alunos" :key="al.aln_id" class="p-4">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ al.nome }}</span>
                                <span v-if="al.matricula" class="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">Matr. {{ al.matricula }}</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="al.situacao === 'Ativa'
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'"
                                >{{ al.situacao }}</span>
                            </div>

                            <p v-if="al.avaliacoes.length === 0" class="text-sm text-muted-foreground">Sem avaliações registradas.</p>
                            <div v-else class="grid gap-2">
                                <div v-for="(av, i) in al.avaliacoes" :key="i" class="rounded-lg border bg-background p-3">
                                    <div class="mb-1 text-xs font-medium text-muted-foreground">{{ fmtBr(av.dt) }}</div>
                                    <div class="prose prose-sm max-w-none dark:prose-invert" v-html="av.descricao"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 portrait; margin: 10mm; }
    body > *:not(#print-area) { display: none !important; }
    #print-area {
        display: block !important;
        font-family: Arial, sans-serif;
        font-size: 9pt;
        color: #000;
    }
    .cab { display: grid; grid-template-columns: 70px 1fr 70px; align-items: center; gap: 8px; border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
    .cab img { max-width: 70px; max-height: 70px; object-fit: contain; }
    .cab-textos { text-align: center; }
    .cab-textos div { font-size: 8pt; }
    .entidade { font-weight: 700; font-size: 9pt; }
    .titulo { text-align: center; font-size: 12pt; font-weight: 700; margin: 4px 0 2px; }
    .subtitulo { text-align: center; font-size: 9pt; margin-bottom: 6px; }
    .turma-bloco { margin-bottom: 10px; page-break-inside: avoid; }
    .turma-tit { font-size: 10pt; font-weight: 700; background: #e5e7eb; padding: 3px 6px; margin: 8px 0 4px; }
    .aluno-bloco { border: 1px solid #444; padding: 4px 6px; margin-bottom: 4px; page-break-inside: avoid; }
    .aluno-cab { font-size: 9pt; border-bottom: 1px solid #ccc; padding-bottom: 2px; margin-bottom: 3px; }
    .aluno-nome { font-weight: 700; }
    .aluno-sit { float: right; font-weight: 700; }
    .sem-av { font-size: 8pt; font-style: italic; color: #555; }
    .av { margin-bottom: 3px; }
    .av-data { font-size: 8pt; font-weight: 700; }
    .av-texto { font-size: 8.5pt; }
    .av-texto :is(p, ul, ol) { margin: 2px 0; }
}
</style>
