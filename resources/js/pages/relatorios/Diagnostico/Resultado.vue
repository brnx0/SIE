<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { TAB_ID } from '@/lib/tabRegistry';
import { useTabStore } from '@/stores/tabs';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed, inject } from 'vue';

interface Unidade { uni_id: number; label: string }
interface Linha { ind_descricao: string; valores: (string | null)[] }
interface Aluno { aln_nome: string; aln_nr_matricula: string | null; linhas: Linha[] }
interface Parametros { nome_entidade: string; msg_cab_secretaria: string | null; msg_cab_estado: string | null; logomarca_url: string | null; brasao_url: string | null }

const props = defineProps<{
    parametros: Parametros | null;
    filtros: { anl_ano: number; esc_nome: string; turma: string; disciplina: string; aluno: string | null };
    unidades: Unidade[];
    opcoes: Record<string, string>;
    alunos: Aluno[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Avaliações por Diagnóstico', href: '/relatorios/diagnostico' }];

const tabId = inject(TAB_ID, null);
const tabStore = useTabStore();
const isActiveTab = computed(() => !tabId || tabId === tabStore.activeId);

const SIGLA: Record<string, string> = { autonomia: 'A', apoio: 'Ap', nao_realiza: 'NR', nao_trabalha: 'NT' };
const COR: Record<string, string> = {
    autonomia: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
    apoio: 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-300',
    nao_realiza: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
    nao_trabalha: 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

const opcoesKeys = computed(() => Object.keys(props.opcoes));
const temIndicadores = computed(() => props.alunos.some((a) => a.linhas.length));
const sigla = (k: string | null) => (k ? SIGLA[k] ?? '?' : '—');
const imprimir = () => window.print();
</script>

<template>
    <Head title="Resultado — Avaliações por Diagnóstico" />

    <Teleport to="body" :disabled="!isActiveTab">
        <div class="report-print" :class="{ 'is-active': isActiveTab }">
            <div class="dg-doc">
                <div v-if="parametros" class="dg-head">
                    <div v-if="parametros.msg_cab_estado">{{ parametros.msg_cab_estado }}</div>
                    <div class="b">{{ parametros.nome_entidade }}</div>
                    <div v-if="parametros.msg_cab_secretaria">{{ parametros.msg_cab_secretaria }}</div>
                    <div v-if="filtros.esc_nome">{{ filtros.esc_nome }}</div>
                </div>

                <div class="dg-title">Avaliações por Diagnóstico — {{ filtros.anl_ano }}</div>
                <div class="dg-meta">
                    <div><b>Turma:</b> {{ filtros.turma }}</div>
                    <div><b>Disciplina:</b> {{ filtros.disciplina }}</div>
                </div>

                <div v-if="!alunos.length" class="dg-vazio">Nenhum aluno encontrado.</div>
                <div v-else-if="!temIndicadores" class="dg-vazio">Nenhum indicador cadastrado para esta disciplina/série.</div>

                <template v-else>
                    <div v-for="(a, ai) in alunos" :key="ai" class="dg-aluno">
                        <div class="dg-aluno-nome">{{ a.aln_nome }}<span v-if="a.aln_nr_matricula"> · Mat. {{ a.aln_nr_matricula }}</span></div>
                        <table class="dg-tbl">
                            <thead>
                                <tr>
                                    <th class="dg-ind">Indicador</th>
                                    <th v-for="u in unidades" :key="u.uni_id" class="dg-per">{{ u.label }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(l, li) in a.linhas" :key="li">
                                    <td class="dg-ind">{{ l.ind_descricao }}</td>
                                    <td v-for="(v, vi) in l.valores" :key="vi" class="dg-per">{{ sigla(v) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="dg-leg"><b>Legenda:</b> <span v-for="k in opcoesKeys" :key="k">{{ SIGLA[k] }} = {{ opcoes[k] }}&nbsp;&nbsp;</span></div>
                </template>
            </div>
        </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Resultado: Avaliações por Diagnóstico</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/diagnostico"><Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button></Link>
                    <Button variant="outline" :disabled="!alunos.length || !temIndicadores" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 rounded-xl border bg-card p-4 text-sm shadow-sm">
                <div>
                    <strong>Ano Letivo:</strong> {{ filtros.anl_ano }}
                    · <strong>Escola:</strong> {{ filtros.esc_nome }}
                    · <strong>Turma:</strong> {{ filtros.turma }}
                    · <strong>Disciplina:</strong> {{ filtros.disciplina }}
                </div>
                <div class="mt-0.5 text-muted-foreground">Aluno: {{ filtros.aluno ?? 'Todos' }} · {{ alunos.length }} aluno(s)</div>
            </div>

            <!-- Legenda -->
            <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                <span class="font-semibold text-muted-foreground">Legenda:</span>
                <span v-for="k in opcoesKeys" :key="k" class="inline-flex items-center gap-1.5">
                    <span :class="['inline-flex size-6 items-center justify-center rounded text-[11px] font-bold', COR[k]]">{{ SIGLA[k] }}</span>
                    {{ opcoes[k] }}
                </span>
            </div>

            <div v-if="!alunos.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhum aluno encontrado.</div>
            <div v-else-if="!temIndicadores" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhum indicador cadastrado para esta disciplina/série.</div>

            <div v-else class="space-y-5">
                <div v-for="(a, ai) in alunos" :key="ai" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="flex items-center justify-between gap-2 border-b bg-muted/30 px-4 py-2.5">
                        <span class="font-semibold">{{ a.aln_nome }}</span>
                        <span v-if="a.aln_nr_matricula" class="text-xs text-muted-foreground">Mat. {{ a.aln_nr_matricula }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="min-w-[260px] px-3 py-2 text-left">Indicador</th>
                                    <th v-for="u in unidades" :key="u.uni_id" class="px-2 py-2 text-center">{{ u.label }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(l, li) in a.linhas" :key="li" class="hover:bg-muted/20">
                                    <td class="px-3 py-1.5">{{ l.ind_descricao }}</td>
                                    <td v-for="(v, vi) in l.valores" :key="vi" class="px-2 py-1.5 text-center">
                                        <span v-if="v" :class="['inline-flex min-w-[34px] justify-center rounded px-1.5 py-0.5 text-[11px] font-bold', COR[v]]" :title="opcoes[v]">{{ sigla(v) }}</span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                </tr>
                                <tr v-if="!a.linhas.length">
                                    <td :colspan="unidades.length + 1" class="px-4 py-6 text-center text-muted-foreground">Sem indicadores.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    @page dg-diag { size: A4 portrait; margin: 10mm 12mm; }

    .dg-doc { page: dg-diag; font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 9pt; }
    .dg-head { text-align: center; line-height: 1.25; font-size: 9pt; margin-bottom: 8px; }
    .dg-head .b { font-weight: 700; text-transform: uppercase; }

    .dg-title { border: 1px solid #000; text-align: center; font-weight: 700; font-size: 12pt; padding: 5px; }
    .dg-meta {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        border: 1px solid #000;
        border-top: none;
        padding: 4px 8px;
        font-size: 10pt;
    }

    .dg-aluno { margin-top: 10px; page-break-inside: avoid; }
    .dg-aluno-nome { font-weight: 700; font-size: 10pt; background: #eee; border: 1px solid #000; border-bottom: none; padding: 3px 6px; }

    .dg-tbl { width: 100%; border-collapse: collapse; }
    .dg-tbl th, .dg-tbl td { border: 1px solid #000; padding: 2px 5px; }
    .dg-tbl thead th { background: #f0f0f0; text-align: center; font-size: 8.5pt; }
    .dg-tbl .dg-ind { text-align: left; }
    .dg-tbl .dg-per { width: 64px; text-align: center; font-weight: 700; }
    .dg-tbl td.dg-ind { font-size: 8.5pt; }

    .dg-vazio { padding: 16px; text-align: center; font-style: italic; }
    .dg-leg { margin-top: 8px; font-size: 8pt; }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
