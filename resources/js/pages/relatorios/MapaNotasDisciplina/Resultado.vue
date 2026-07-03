<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { TAB_ID } from '@/lib/tabRegistry';
import { useTabStore } from '@/stores/tabs';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed, inject } from 'vue';

interface Instrumento { ava_id: number; descricao: string; valor: string | null; dt: string | null }
interface Aluno {
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: string | null;
    notas: string[];
    media: string;
    recuperacao: string;
    mf: string;
}
interface Parametros { nome_entidade: string; msg_cab_secretaria: string | null; msg_cab_estado: string | null; logomarca_url: string | null; brasao_url: string | null }
interface Filtros { anl_ano: number; esc_nome: string; turma: string; disciplina: string; professor: string; periodo: string; periodo_datas: string | null }

const props = defineProps<{
    parametros: Parametros | null;
    filtros: Filtros;
    instrumentos: Instrumento[];
    tem_recuperacao: boolean;
    alunos: Aluno[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mapa de Notas', href: '/relatorios/mapa-notas-disciplina' }];

// Nº de colunas fixas (Nº + Nome) + instrumentos + Média + [Rec] + MF (p/ colspan do "vazio").
const totalCols = computed(() => 2 + props.instrumentos.length + 2 + (props.tem_recuperacao ? 1 : 0));

const tabId = inject(TAB_ID, null);
const tabStore = useTabStore();
const isActiveTab = computed(() => !tabId || tabId === tabStore.activeId);

const imprimir = () => window.print();
</script>

<template>
    <Head title="Resultado — Mapa de Notas" />

    <!-- Área de impressão (teleportada p/ o body; imprime só quando esta aba está ativa) -->
    <Teleport to="body" :disabled="!isActiveTab">
        <div class="report-print" :class="{ 'is-active': isActiveTab }">
            <div class="mn-area">
                <div v-if="parametros" class="mn-head">
                    <div v-if="parametros.msg_cab_estado">{{ parametros.msg_cab_estado }}</div>
                    <div class="b">{{ parametros.nome_entidade }}</div>
                    <div v-if="parametros.msg_cab_secretaria">{{ parametros.msg_cab_secretaria }}</div>
                    <div v-if="filtros.esc_nome" class="b">{{ filtros.esc_nome }}</div>
                </div>

                <div class="mn-title">Mapa de Notas</div>

                <div class="mn-meta">
                    <div><b>Turma:</b> {{ filtros.turma }}</div>
                    <div><b>Disciplina:</b> {{ filtros.disciplina }}</div>
                    <div><b>Professor(a):</b> {{ filtros.professor }}</div>
                    <div class="mn-meta-uni">
                        {{ filtros.periodo }}<span v-if="filtros.periodo_datas"> ({{ filtros.periodo_datas }})</span>
                    </div>
                </div>

                <table class="mn-tbl">
                    <thead>
                        <tr>
                            <th class="mn-num">Nº</th>
                            <th class="mn-nome">Nome do Aluno</th>
                            <th v-for="ins in instrumentos" :key="ins.ava_id" class="mn-ins">
                                <div class="mn-ins-nome">{{ ins.descricao }}<template v-if="ins.valor"> ({{ ins.valor }})</template></div>
                                <div v-if="ins.dt" class="mn-ins-dt">{{ ins.dt }}</div>
                            </th>
                            <th class="mn-res">Média</th>
                            <th v-if="tem_recuperacao" class="mn-res">Rec.</th>
                            <th class="mn-res">M.F</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(a, i) in alunos" :key="a.aln_id">
                            <td class="mn-num">{{ i + 1 }}</td>
                            <td class="mn-nome">{{ a.aln_nome }}</td>
                            <td v-for="(n, j) in a.notas" :key="j">{{ n }}</td>
                            <td class="mn-res">{{ a.media }}</td>
                            <td v-if="tem_recuperacao" class="mn-res">{{ a.recuperacao }}</td>
                            <td class="mn-res mn-mf">{{ a.mf }}</td>
                        </tr>
                        <tr v-if="!alunos.length">
                            <td :colspan="totalCols" class="mn-vazio">Nenhum aluno ativo nesta turma.</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mn-leg">
                    <b>Média</b>: soma das avaliações do período (sem recuperação). <b>M.F</b>: média final da unidade (considera recuperação e lançamento manual da secretaria).
                </p>
            </div>
        </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Resultado: Mapa de Notas</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/mapa-notas-disciplina"><Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button></Link>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 rounded-xl border bg-card p-4 text-sm shadow-sm">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }} · <strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Turma:</strong> {{ filtros.turma }} · <strong>Disciplina:</strong> {{ filtros.disciplina }}</div>
                <div>
                    <strong>Professor(a):</strong> {{ filtros.professor }} · <strong>Período:</strong> {{ filtros.periodo }}
                    <span v-if="filtros.periodo_datas" class="text-muted-foreground">({{ filtros.periodo_datas }})</span>
                </div>
            </div>

            <div v-if="!alunos.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhum aluno ativo nesta turma.</div>

            <div v-else class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 text-left">Nº</th>
                            <th class="sticky left-0 bg-muted/40 px-3 py-2 text-left">Nome do Aluno</th>
                            <th v-for="ins in instrumentos" :key="ins.ava_id" class="px-2 py-2 text-center">
                                {{ ins.descricao }}
                                <span v-if="ins.dt || ins.valor" class="block text-[10px] font-normal normal-case text-muted-foreground">
                                    <template v-if="ins.dt">{{ ins.dt }}</template><template v-if="ins.dt && ins.valor"> · </template><template v-if="ins.valor">({{ ins.valor }})</template>
                                </span>
                            </th>
                            <th class="px-2 py-2 text-center">Média</th>
                            <th v-if="tem_recuperacao" class="px-2 py-2 text-center">Rec.</th>
                            <th class="px-2 py-2 text-center">M.F</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(a, i) in alunos" :key="a.aln_id" class="hover:bg-muted/20">
                            <td class="px-3 py-1.5 text-muted-foreground">{{ i + 1 }}</td>
                            <td class="sticky left-0 bg-card px-3 py-1.5 font-medium">{{ a.aln_nome }}</td>
                            <td v-for="(n, j) in a.notas" :key="j" class="px-2 py-1.5 text-center tabular-nums">{{ n }}</td>
                            <td class="px-2 py-1.5 text-center font-medium tabular-nums">{{ a.media }}</td>
                            <td v-if="tem_recuperacao" class="px-2 py-1.5 text-center tabular-nums">{{ a.recuperacao }}</td>
                            <td class="px-2 py-1.5 text-center font-semibold tabular-nums text-indigo-700">{{ a.mf }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    @page mn-mapa { size: A4 landscape; margin: 8mm 10mm; }

    .mn-area {
        page: mn-mapa;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
        font-size: 8pt;
    }
    .mn-head { text-align: center; line-height: 1.25; font-size: 8.5pt; margin-bottom: 6px; }
    .mn-head .b { font-weight: 700; text-transform: uppercase; }

    .mn-title {
        border: 1px solid #000;
        text-align: center;
        font-weight: 700;
        font-size: 11pt;
        padding: 5px;
    }
    .mn-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 18px;
        align-items: center;
        border: 1px solid #000;
        border-top: none;
        padding: 4px 8px;
        font-size: 9pt;
    }
    .mn-meta-uni { margin-left: auto; font-weight: 700; }

    .mn-tbl { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .mn-tbl th, .mn-tbl td { border: 1px solid #000; text-align: center; padding: 2px 3px; }
    .mn-tbl thead th { background: #f0f0f0; vertical-align: bottom; }
    .mn-tbl .mn-num { width: 26px; }
    .mn-tbl .mn-nome { width: 200px; text-align: left; }
    .mn-tbl tbody .mn-nome { font-size: 7.5pt; }
    .mn-tbl .mn-res { width: 46px; font-weight: 700; }
    .mn-tbl td.mn-mf { background: #f0f0f0; }

    /* Cabeçalho do instrumento na horizontal: nome em cima, data abaixo. */
    .mn-tbl th.mn-ins { padding: 3px 4px; vertical-align: middle; }
    .mn-tbl th.mn-ins .mn-ins-nome { font-size: 7pt; font-weight: 700; line-height: 1.1; }
    .mn-tbl th.mn-ins .mn-ins-dt { font-size: 6.5pt; font-weight: 400; color: #333; line-height: 1.1; margin-top: 1px; }
    .mn-vazio { padding: 12px; font-style: italic; }
    .mn-leg { margin-top: 6px; font-size: 7.5pt; font-style: italic; }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
