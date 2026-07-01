<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { TAB_ID } from '@/lib/tabRegistry';
import { useTabStore } from '@/stores/tabs';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed, inject } from 'vue';

interface Aluno { aln_nr_matricula: string | null; aln_nome: string; aln_dt_nascimento: string | null; situacao: string }
interface TurmaBloco { tur_id: number; escola: string; turma: string; turno: string; segmento: string | null; alunos: Aluno[] }
interface Parametros { nome_entidade: string; msg_cab_secretaria: string | null; msg_cab_estado: string | null; logomarca_url: string | null; brasao_url: string | null }

const props = defineProps<{
    parametros: Parametros | null;
    filtros: { anl_ano: number; esc_nome: string | null; turma: string | null };
    turmas: TurmaBloco[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Final da Educação Infantil', href: '/relatorios/final-educacao-infantil' }];

// Só a aba ativa imprime (abas ficam em cache via v-show no TabShell).
const tabId = inject(TAB_ID, null);
const tabStore = useTabStore();
const isActiveTab = computed(() => !tabId || tabId === tabStore.activeId);

const totalAlunos = computed(() => props.turmas.reduce((n, t) => n + t.alunos.length, 0));
const imprimir = () => window.print();
</script>

<template>
    <Head title="Resultado — Final da Educação Infantil" />

    <!-- Área de impressão (teleportada p/ o body; imprime só quando esta aba está ativa) -->
    <Teleport to="body" :disabled="!isActiveTab">
        <div class="report-print" :class="{ 'is-active': isActiveTab }">
            <div class="ei-doc">
                <section v-for="t in turmas" :key="t.tur_id" class="ei-page">
                    <div v-if="parametros" class="ei-head">
                        <div v-if="parametros.msg_cab_estado">{{ parametros.msg_cab_estado }}</div>
                        <div class="b">{{ parametros.nome_entidade }}</div>
                        <div v-if="parametros.msg_cab_secretaria">{{ parametros.msg_cab_secretaria }}</div>
                        <div v-if="t.escola">{{ t.escola }}</div>
                    </div>

                    <div class="ei-title">Relatório Final da Educação Infantil — {{ filtros.anl_ano }}</div>
                    <div class="ei-meta">
                        <div><b>Turma:</b> {{ t.turma }}<span v-if="t.turno"> · {{ t.turno }}</span></div>
                        <div v-if="t.segmento" class="ei-meta-seg">{{ t.segmento }}</div>
                    </div>

                    <table class="ei-tbl">
                        <thead>
                            <tr>
                                <th class="ei-num">Nº</th>
                                <th class="ei-mat">Matrícula</th>
                                <th class="ei-nome">Nome do Aluno</th>
                                <th class="ei-nasc">Nascimento</th>
                                <th class="ei-sit">Situação Final</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(a, i) in t.alunos" :key="i">
                                <td class="ei-num">{{ i + 1 }}</td>
                                <td class="ei-mat">{{ a.aln_nr_matricula ?? '—' }}</td>
                                <td class="ei-nome">{{ a.aln_nome }}</td>
                                <td class="ei-nasc">{{ a.aln_dt_nascimento ?? '—' }}</td>
                                <td class="ei-sit">{{ a.situacao }}</td>
                            </tr>
                            <tr v-if="!t.alunos.length">
                                <td colspan="5" class="ei-vazio">Nenhum aluno nesta turma.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Resultado: Final da Educação Infantil</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/final-educacao-infantil"><Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button></Link>
                    <Button variant="outline" :disabled="!turmas.length" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 rounded-xl border bg-card p-4 text-sm shadow-sm">
                <div>
                    <strong>Ano Letivo:</strong> {{ filtros.anl_ano }}
                    · <strong>Escola:</strong> {{ filtros.esc_nome ?? 'Todas' }}
                    · <strong>Turma:</strong> {{ filtros.turma ?? 'Todas' }}
                </div>
                <div class="mt-0.5 text-muted-foreground">{{ turmas.length }} turma(s) · {{ totalAlunos }} aluno(s) — 1 turma por página na impressão.</div>
            </div>

            <div v-if="!turmas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhuma turma de Educação Infantil encontrada para o filtro.
            </div>

            <div v-else class="space-y-6">
                <div v-for="t in turmas" :key="t.tur_id" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/30 px-4 py-2.5">
                        <div>
                            <span class="font-semibold">{{ t.turma }}</span>
                            <span v-if="t.turno" class="ml-2 text-xs text-muted-foreground">{{ t.turno }}</span>
                            <span v-if="t.segmento" class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">{{ t.segmento }}</span>
                        </div>
                        <span class="text-xs text-muted-foreground">{{ t.escola }} · {{ t.alunos.length }} aluno(s)</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2 text-left">Nº</th>
                                    <th class="px-3 py-2 text-left">Matrícula</th>
                                    <th class="px-3 py-2 text-left">Nome do Aluno</th>
                                    <th class="px-3 py-2 text-left">Nascimento</th>
                                    <th class="px-3 py-2 text-left">Situação Final</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(a, i) in t.alunos" :key="i" class="hover:bg-muted/20">
                                    <td class="px-3 py-1.5 text-muted-foreground">{{ i + 1 }}</td>
                                    <td class="px-3 py-1.5 tabular-nums">{{ a.aln_nr_matricula ?? '—' }}</td>
                                    <td class="px-3 py-1.5 font-medium">{{ a.aln_nome }}</td>
                                    <td class="px-3 py-1.5 tabular-nums">{{ a.aln_dt_nascimento ?? '—' }}</td>
                                    <td class="px-3 py-1.5">{{ a.situacao }}</td>
                                </tr>
                                <tr v-if="!t.alunos.length">
                                    <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">Nenhum aluno nesta turma.</td>
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
    @page ei-final { size: A4 portrait; margin: 10mm 12mm; }

    .ei-doc { font-family: Arial, Helvetica, sans-serif; color: #000; }
    .ei-page { page: ei-final; }
    .ei-page + .ei-page { page-break-before: always; }

    .ei-head { text-align: center; line-height: 1.25; font-size: 9pt; margin-bottom: 8px; }
    .ei-head .b { font-weight: 700; text-transform: uppercase; }

    .ei-title { border: 1px solid #000; text-align: center; font-weight: 700; font-size: 12pt; padding: 5px; }
    .ei-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #000;
        border-top: none;
        padding: 4px 8px;
        font-size: 10pt;
    }
    .ei-meta-seg { font-weight: 700; }

    .ei-tbl { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9.5pt; }
    .ei-tbl th, .ei-tbl td { border: 1px solid #000; text-align: left; padding: 3px 5px; }
    .ei-tbl thead th { background: #f0f0f0; }
    .ei-tbl .ei-num { width: 32px; text-align: center; }
    .ei-tbl .ei-mat { width: 90px; }
    .ei-tbl .ei-nasc { width: 90px; text-align: center; }
    .ei-tbl .ei-sit { width: 130px; }
    .ei-tbl td.ei-num, .ei-tbl td.ei-nasc { text-align: center; }
    .ei-vazio { text-align: center; font-style: italic; padding: 10px; }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
