<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { TAB_ID } from '@/lib/tabRegistry';
import { useTabStore } from '@/stores/tabs';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed, inject } from 'vue';

interface Cell { texto: string; tipo: string | null; faltas: number }
interface Aluno { aln_id: number; aln_nome: string; aln_nr_matricula: string | null; celulas: Cell[] }
interface Parametros { nome_entidade: string; msg_cab_secretaria: string | null; msg_cab_estado: string | null; logomarca_url: string | null; brasao_url: string | null }

defineProps<{
    parametros: Parametros | null;
    filtros: { anl_ano: number; esc_nome: string; turma: string; periodo: string | null; periodo_datas: string | null };
    disciplinas: string[];
    alunos: Aluno[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notas por Unidade', href: '/relatorios/mapa-notas' }];

// Só a aba ativa imprime (abas ficam em cache via v-show no TabShell).
const tabId = inject(TAB_ID, null);
const tabStore = useTabStore();
const isActiveTab = computed(() => !tabId || tabId === tabStore.activeId);

const imprimir = () => window.print();
</script>

<template>
    <Head title="Resultado — Notas por Unidade" />

    <!-- Área de impressão (teleportada p/ o body; imprime só quando esta aba está ativa) -->
    <Teleport to="body">
        <div class="report-print" :class="{ 'is-active': isActiveTab }">
            <div class="nu-area">
                <div v-if="parametros" class="nu-head">
                    <div v-if="parametros.msg_cab_estado">{{ parametros.msg_cab_estado }}</div>
                    <div class="b">{{ parametros.nome_entidade }}</div>
                    <div v-if="parametros.msg_cab_secretaria">{{ parametros.msg_cab_secretaria }}</div>
                    <div v-if="filtros.esc_nome">{{ filtros.esc_nome }}</div>
                </div>

                <div class="nu-title">Relatório de Notas por Unidade</div>

                <div class="nu-meta">
                    <div><b>Turma:</b> {{ filtros.turma }}</div>
                    <div class="nu-meta-uni">
                        {{ filtros.periodo }}<span v-if="filtros.periodo_datas"> ({{ filtros.periodo_datas }})</span>
                    </div>
                </div>

                <table class="nu-tbl">
                    <thead>
                        <tr>
                            <th class="nu-num">Nº</th>
                            <th class="nu-nome">Nome do Aluno</th>
                            <th v-for="(d, i) in disciplinas" :key="i" class="nu-dis"><span>{{ d }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(a, i) in alunos" :key="a.aln_id">
                            <td class="nu-num">{{ i + 1 }}</td>
                            <td class="nu-nome">{{ a.aln_nome }}</td>
                            <td v-for="(c, j) in a.celulas" :key="j">
                                <div class="nu-md">{{ c.texto }}</div>
                                <div class="nu-ft">{{ c.faltas }}</div>
                            </td>
                        </tr>
                        <tr v-if="!alunos.length">
                            <td :colspan="disciplinas.length + 2" class="nu-vazio">Nenhum aluno ativo nesta turma.</td>
                        </tr>
                    </tbody>
                </table>
                <p class="nu-leg">Em cada célula: <b>média</b> (acima) e <b>faltas</b> (abaixo) na unidade.</p>
            </div>
        </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Resultado: Notas por Unidade</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/mapa-notas"><Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button></Link>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 rounded-xl border bg-card p-4 text-sm shadow-sm">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }} · <strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div>
                    <strong>Turma:</strong> {{ filtros.turma }} · <strong>Unidade:</strong> {{ filtros.periodo }}
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
                            <th v-for="(d, i) in disciplinas" :key="i" class="px-2 py-2 text-center">{{ d }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(a, i) in alunos" :key="a.aln_id" class="hover:bg-muted/20">
                            <td class="px-3 py-1.5 text-muted-foreground">{{ i + 1 }}</td>
                            <td class="sticky left-0 bg-card px-3 py-1.5 font-medium">{{ a.aln_nome }}</td>
                            <td v-for="(c, j) in a.celulas" :key="j" class="px-2 py-1.5 text-center">
                                <div class="font-medium tabular-nums">{{ c.texto }}</div>
                                <div class="text-[10px] text-muted-foreground tabular-nums" title="Faltas na unidade">{{ c.faltas }} f</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    @page nu-unidade { size: A4 landscape; margin: 8mm 10mm; }

    .nu-area {
        page: nu-unidade;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
        font-size: 8pt;
    }
    .nu-head { text-align: center; line-height: 1.25; font-size: 8.5pt; margin-bottom: 6px; }
    .nu-head .b { font-weight: 700; text-transform: uppercase; }

    .nu-title {
        border: 1px solid #000;
        text-align: center;
        font-weight: 700;
        font-size: 11pt;
        padding: 5px;
    }
    .nu-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #000;
        border-top: none;
        padding: 4px 8px;
        font-size: 9pt;
    }
    .nu-meta-uni { font-weight: 700; }

    .nu-tbl { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .nu-tbl th, .nu-tbl td { border: 1px solid #000; text-align: center; padding: 2px 3px; }
    .nu-tbl thead th { background: #f0f0f0; vertical-align: bottom; }
    .nu-tbl .nu-num { width: 26px; }
    .nu-tbl .nu-nome { width: 190px; text-align: left; }
    .nu-tbl tbody .nu-nome { font-size: 7.5pt; }

    /* Cabeçalho da disciplina na vertical (lê de baixo p/ cima). */
    .nu-tbl th.nu-dis { height: 120px; padding: 2px 0; }
    .nu-tbl th.nu-dis span {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        white-space: nowrap;
        font-size: 7.5pt;
        font-weight: 700;
        line-height: 1;
        margin: 0 auto;
    }
    .nu-vazio { padding: 12px; font-style: italic; }

    .nu-tbl td .nu-md { font-weight: 700; line-height: 1.1; }
    .nu-tbl td .nu-ft { font-size: 6.5pt; color: #555; line-height: 1.1; }
    .nu-leg { margin-top: 6px; font-size: 7.5pt; font-style: italic; }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
