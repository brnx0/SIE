<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { TAB_ID } from '@/lib/tabRegistry';
import { useTabStore } from '@/stores/tabs';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer, TriangleAlert } from 'lucide-vue-next';
import { computed, inject } from 'vue';

interface Cell { texto: string; tipo: string | null }
interface Aluno { aln_id: number; aln_nome: string; aln_nr_matricula: string | null; celulas: Cell[]; resultado: string | null; aprovado: boolean; saiu: boolean; encerrado: boolean }
interface Parametros { nome_entidade: string; msg_cab_secretaria: string | null; msg_cab_estado: string | null; logomarca_url: string | null; brasao_url: string | null; texto_ata_final: string | null }

// Uma ata por turma: avulsa vem com 1 item; "Todas as turmas" vem com N.
interface Ata { tur_id: number; turma: string; encerrada: boolean; disciplinas: string[]; alunos: Aluno[] }

const props = defineProps<{
    parametros: Parametros | null;
    filtros: { anl_ano: number; esc_nome: string; todas: boolean };
    atas: Ata[];
    falhas: { turma: string; erro: string }[];
}>();

const lote = computed(() => props.filtros.todas);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Ata Final do Encerramento', href: '/relatorios/ata-final' }];

const tabId = inject(TAB_ID, null);
const tabStore = useTabStore();
const isActiveTab = computed(() => !tabId || tabId === tabStore.activeId);

// Resultado final (Aprovado/Reprovado) só p/ quem encerrou e não saiu; saídos vão pela enturmação ao lado do nome.
const resultadoTxt = (a: Aluno) => (!a.saiu && a.encerrado ? (a.resultado ?? '—') : '—');
const imprimir = () => window.print();
</script>

<template>
    <Head title="Resultado — Ata Final" />

    <Teleport to="body" :disabled="!isActiveTab">
        <div class="report-print" :class="{ 'is-active': isActiveTab }">
            <div v-for="(ata, n) in atas" :key="ata.tur_id" class="af-area" :class="{ 'af-quebra': n < atas.length - 1 }">
                <div v-if="parametros" class="af-head">
                    <div v-if="parametros.msg_cab_estado">{{ parametros.msg_cab_estado }}</div>
                    <div class="b">{{ parametros.nome_entidade }}</div>
                    <div v-if="parametros.msg_cab_secretaria">{{ parametros.msg_cab_secretaria }}</div>
                    <div v-if="filtros.esc_nome">{{ filtros.esc_nome }}</div>
                </div>

                <div class="af-title">Ata Final do Encerramento — {{ filtros.anl_ano }}</div>
                <div class="af-meta"><div><b>Turma:</b> {{ ata.turma }}</div></div>

                <table class="af-tbl">
                    <thead>
                        <tr>
                            <th class="af-num">Nº</th>
                            <th class="af-nome">Nome do Aluno</th>
                            <th v-for="(d, i) in ata.disciplinas" :key="i" class="af-dis"><span>{{ d }}</span></th>
                            <th class="af-res">Resultado Final</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(a, i) in ata.alunos" :key="a.aln_id">
                            <td class="af-num">{{ i + 1 }}</td>
                            <td class="af-nome">{{ a.aln_nome }}<span v-if="a.saiu && a.resultado" class="af-saida"> — {{ a.resultado }}</span></td>
                            <td v-for="(c, j) in a.celulas" :key="j">{{ c.texto }}</td>
                            <td class="af-res">{{ resultadoTxt(a) }}</td>
                        </tr>
                        <tr v-if="!ata.alunos.length">
                            <td :colspan="ata.disciplinas.length + 3" class="af-vazio">Nenhum aluno nesta turma.</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="parametros?.texto_ata_final" class="af-texto">{{ parametros.texto_ata_final }}</div>

                <p class="af-leg">Nota final consolidada por disciplina. Alunos que saíram aparecem com a situação de saída ao lado do nome e sem resultado final.</p>
            </div>
        </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Resultado: {{ lote ? 'Atas Finais' : 'Ata Final' }}</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/ata-final"><Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button></Link>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 rounded-xl border bg-card p-4 text-sm shadow-sm">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }} · <strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div v-if="lote">
                    <strong>{{ atas.length }}</strong> {{ atas.length === 1 ? 'ata gerada' : 'atas geradas' }}, uma por turma.
                    <template v-if="!falhas.length && atas.length">Todas as turmas da escola foram contempladas.</template>
                </div>
                <div v-else-if="atas[0]"><strong>Turma:</strong> {{ atas[0].turma }}</div>
            </div>

            <div v-if="falhas.length" class="mb-4 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <div>
                    <strong>{{ falhas.length }} {{ falhas.length === 1 ? 'turma não pôde ser gerada' : 'turmas não puderam ser geradas' }}:</strong>
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="(f, i) in falhas" :key="i">{{ f.turma }} — {{ f.erro }}</li>
                    </ul>
                </div>
            </div>

            <div v-if="!atas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhuma turma encontrada para esta escola e ano letivo.</div>

            <section v-for="ata in atas" :key="ata.tur_id" class="mb-6">
                <h2 v-if="lote" class="mb-2 flex items-center gap-2 text-base font-semibold">
                    {{ ata.turma }}
                    <span v-if="!ata.encerrada" class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">não encerrada</span>
                </h2>

                <div v-if="!ata.alunos.length" class="rounded-xl border bg-card py-8 text-center text-muted-foreground">Nenhum aluno nesta turma.</div>

                <div v-else class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left">Nº</th>
                                <th class="sticky left-0 bg-muted/40 px-3 py-2 text-left">Nome do Aluno</th>
                                <th v-for="(d, i) in ata.disciplinas" :key="i" class="px-2 py-2 text-center">{{ d }}</th>
                                <th class="px-3 py-2 text-center">Resultado Final</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(a, i) in ata.alunos" :key="a.aln_id" class="hover:bg-muted/20">
                                <td class="px-3 py-1.5 text-muted-foreground">{{ i + 1 }}</td>
                                <td class="sticky left-0 bg-card px-3 py-1.5 font-medium">
                                    {{ a.aln_nome }}
                                    <span v-if="a.saiu" class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ a.resultado ?? 'Saiu da turma' }}</span>
                                </td>
                                <td v-for="(c, j) in a.celulas" :key="j" class="px-2 py-1.5 text-center font-medium tabular-nums">{{ c.texto }}</td>
                                <td class="px-3 py-1.5 text-center">
                                    <span v-if="!a.saiu && a.encerrado" :class="['rounded-full px-2 py-0.5 text-[11px] font-medium', a.aprovado ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300']">
                                        {{ a.resultado ?? '—' }}
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    @page af-ata { size: A4 landscape; margin: 8mm 10mm; }

    /* Lote: cada turma começa em página nova. */
    .af-area.af-quebra { break-after: page; page-break-after: always; }
    .af-area { page: af-ata; font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 8pt; }
    .af-head { text-align: center; line-height: 1.25; font-size: 8.5pt; margin-bottom: 6px; }
    .af-head .b { font-weight: 700; text-transform: uppercase; }
    .af-title { border: 1px solid #000; text-align: center; font-weight: 700; font-size: 11pt; padding: 5px; }
    .af-meta { border: 1px solid #000; border-top: none; padding: 4px 8px; font-size: 9pt; }

    .af-tbl { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .af-tbl th, .af-tbl td { border: 1px solid #000; text-align: center; padding: 2px 3px; }
    .af-tbl thead th { background: #f0f0f0; vertical-align: bottom; }
    .af-tbl .af-num { width: 26px; }
    .af-tbl .af-nome { width: 190px; text-align: left; }
    .af-tbl tbody .af-nome { font-size: 7.5pt; }
    .af-saida { font-weight: 400; font-style: italic; color: #444; }
    .af-tbl .af-res { width: 120px; font-weight: 700; }
    .af-tbl tbody td { font-weight: 700; }

    .af-tbl th.af-dis { height: 120px; padding: 2px 0; }
    .af-tbl th.af-dis span { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; font-size: 7.5pt; font-weight: 700; line-height: 1; margin: 0 auto; }
    .af-vazio { padding: 12px; font-style: italic; }
    .af-texto { margin-top: 12px; font-size: 9pt; text-align: justify; white-space: pre-line; line-height: 1.45; }
    .af-leg { margin-top: 6px; font-size: 7.5pt; font-style: italic; }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
