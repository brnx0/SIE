<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

interface Aluno {
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: string | null;
    base: number | null;
    faltas: number;
    frequencia: number | null;
    situacao: string;
}
interface Parametros { nome_entidade: string; logomarca_url: string | null; brasao_url: string | null }

const props = defineProps<{
    parametros: Parametros | null;
    filtros: { anl_ano: number; esc_nome: string; turma: string; segmento: string; mes_label: string };
    modo: 'dias' | 'aulas';
    dias_letivos: number | null;
    total_aulas: number;
    alunos: Aluno[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Diário de Frequência Mensal', href: '/relatorios/frequencia-mensal' }];

const baseLabel = computed(() => (props.modo === 'dias' ? 'Dias Letivos' : 'Aulas'));
const semBase = computed(() => props.modo === 'dias' && !props.dias_letivos);

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});
const freqTxt = (f: number | null) => (f === null ? '—' : `${f.toFixed(1)}%`);
const imprimir = () => window.print();
</script>

<template>
    <Head title="Resultado — Frequência Mensal" />

    <div id="print-area">
        <header class="cab">
            <div class="logo"><img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="" /></div>
            <div class="cab-textos">
                <div class="cab-entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
                <div class="cab-tit">Diário de Frequência Mensal — {{ filtros.mes_label }}</div>
            </div>
            <div class="logo"><img v-if="parametros?.brasao_url" :src="parametros.brasao_url" alt="" /></div>
        </header>
        <div class="ident">
            <span><b>Escola:</b> {{ filtros.esc_nome }}</span>
            <span><b>Turma:</b> {{ filtros.turma }}</span>
            <span><b>Segmento:</b> {{ filtros.segmento }}</span>
            <span><b>{{ baseLabel }}:</b> {{ modo === 'dias' ? (dias_letivos ?? '—') : total_aulas }}</span>
        </div>
        <table class="tbl">
            <thead>
                <tr>
                    <th>#</th>
                    <th class="esq">Aluno</th>
                    <th>{{ baseLabel }}</th>
                    <th>Faltas</th>
                    <th>Frequência</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(a, i) in alunos" :key="a.aln_id">
                    <td>{{ i + 1 }}</td>
                    <td class="esq">{{ a.aln_nome }}</td>
                    <td>{{ a.base ?? '—' }}</td>
                    <td>{{ a.faltas }}</td>
                    <td>{{ freqTxt(a.frequencia) }}</td>
                    <td>{{ a.situacao }}</td>
                </tr>
            </tbody>
        </table>
        <footer class="rodape"><span>SIE Matrícula</span><span>Emitido em {{ dataEmissao }}</span></footer>
    </div>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6 print:py-2">
            <div class="mb-4 flex items-center justify-between print:hidden">
                <h1 class="text-xl font-semibold">Resultado: Frequência Mensal</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/frequencia-mensal"><Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button></Link>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 rounded-xl border bg-card p-4 text-sm shadow-sm">
                <div><strong>Mês:</strong> {{ filtros.mes_label }} · <strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Turma:</strong> {{ filtros.turma }} · <strong>Segmento:</strong> {{ filtros.segmento }}</div>
                <div>
                    <strong>{{ baseLabel }}:</strong> {{ modo === 'dias' ? (dias_letivos ?? '—') : total_aulas }}
                    <span class="text-muted-foreground">· {{ modo === 'dias' ? 'falta = dia sem presença em nenhuma disciplina' : 'faltas por aula' }}</span>
                </div>
            </div>

            <div v-if="semBase" class="mb-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                <TriangleAlert class="size-4 shrink-0" /> Dias letivos não cadastrados para este segmento/mês em Parâmetros. A frequência não pôde ser calculada.
            </div>

            <div v-if="!alunos.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhum aluno na turma.</div>

            <div v-else class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 text-left">#</th>
                            <th class="px-3 py-2 text-left">Aluno</th>
                            <th class="px-3 py-2 text-center">{{ baseLabel }}</th>
                            <th class="px-3 py-2 text-center">Faltas</th>
                            <th class="px-3 py-2 text-center">Frequência</th>
                            <th class="px-3 py-2 text-left">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(a, i) in alunos" :key="a.aln_id" class="hover:bg-muted/20">
                            <td class="px-3 py-1.5 text-muted-foreground">{{ i + 1 }}</td>
                            <td class="px-3 py-1.5 font-medium">
                                {{ a.aln_nome }}
                                <span v-if="a.aln_nr_matricula" class="text-[10px] text-muted-foreground">· Mat. {{ a.aln_nr_matricula }}</span>
                            </td>
                            <td class="px-3 py-1.5 text-center tabular-nums">{{ a.base ?? '—' }}</td>
                            <td class="px-3 py-1.5 text-center tabular-nums">{{ a.faltas }}</td>
                            <td class="px-3 py-1.5 text-center font-semibold tabular-nums" :class="a.frequencia !== null && a.frequencia < 75 ? 'text-rose-600' : 'text-emerald-600'">{{ freqTxt(a.frequencia) }}</td>
                            <td class="px-3 py-1.5">{{ a.situacao }}</td>
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
    @page { size: A4 portrait; margin: 10mm 12mm; }
    body * { visibility: hidden !important; }
    #print-area, #print-area * { visibility: visible !important; }
    #print-area { display: block !important; position: absolute; left: 0; top: 0; width: 100%; font-family: 'Times New Roman', Times, serif; color: #000; }
    .cab { display: grid; grid-template-columns: 90px 1fr 90px; align-items: center; border-bottom: 2px solid #000; padding-bottom: 4px; }
    .logo img { max-width: 85px; max-height: 85px; object-fit: contain; }
    .cab-textos { text-align: center; }
    .cab-entidade { font-size: 12pt; font-weight: 700; text-transform: uppercase; }
    .cab-tit { font-size: 11pt; font-weight: 700; }
    .ident { display: flex; flex-wrap: wrap; gap: 16px; font-size: 9.5pt; margin: 10px 0; }
    .tbl { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    .tbl th, .tbl td { border: 1px solid #000; padding: 4px 6px; text-align: center; }
    .tbl th.esq, .tbl td.esq { text-align: left; }
    .tbl thead th { background: #eee; text-transform: uppercase; font-size: 8.5pt; }
    .rodape { margin-top: 16px; padding-top: 6px; border-top: 1px solid #000; font-size: 8.5pt; display: flex; justify-content: space-between; font-style: italic; }
}
</style>
