<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { TAB_ID } from '@/lib/tabRegistry';
import { useTabStore } from '@/stores/tabs';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed, inject } from 'vue';

interface Linha { aln_nome: string; faltas: number; medias: Record<number, string>; mf: string; resultado: string }
interface Bloco { dis_nome: string; professor: string; alunos: Linha[] }
interface Unidade { uni_id: number; label: string }
interface Parametros { estado: string | null; entidade: string | null; secretaria: string | null; logomarca_url: string | null }
interface Escola { nome: string | null; endereco: string | null }

defineProps<{
    parametros: Parametros | null;
    escola: Escola;
    cabecalho: { anl_ano: number; turma: string | null; turno: string | null; media_aprovacao: number | null };
    unidades: Unidade[];
    disciplinas: Bloco[];
    esc_nome: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Boletim Avaliativo', href: '/relatorios/boletim-avaliativo' }];

const tabId = inject(TAB_ID, null);
const tabStore = useTabStore();
const isActiveTab = computed(() => !tabId || tabId === tabStore.activeId);

const hoje = computed(() => new Date().toLocaleDateString('pt-BR'));
const imprimir = () => window.print();
</script>

<template>
    <Head title="Resultado — Boletim Avaliativo" />

    <Teleport to="body">
        <div class="report-print" :class="{ 'is-active': isActiveTab }">
            <section v-for="(bloco, bi) in disciplinas" :key="bi" class="ba-pagina">
                <!-- Cabeçalho institucional -->
                <div class="ba-cab">
                    <div class="ba-logo"><img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="" /></div>
                    <div class="ba-cab-txt">
                        <div v-if="parametros?.estado">{{ parametros.estado }}</div>
                        <div v-if="parametros?.entidade" class="b">{{ parametros.entidade }}</div>
                        <div v-if="parametros?.secretaria" class="b">{{ parametros.secretaria }}</div>
                        <div v-if="escola.nome" class="b">{{ escola.nome }}</div>
                        <div v-if="escola.endereco">{{ escola.endereco }}</div>
                    </div>
                    <div class="ba-cab-data">
                        <div><b>Data:</b> {{ hoje }}</div>
                        <div><b>Ano Letivo:</b> {{ cabecalho.anl_ano }}</div>
                    </div>
                </div>

                <div class="ba-title">BOLETIM AVALIATIVO</div>

                <div class="ba-prof"><b>PROFESSOR:</b> {{ bloco.professor }}</div>

                <table class="ba-meta">
                    <tbody>
                        <tr>
                            <th>TURMA</th>
                            <th>TURNO</th>
                            <th>COMPONENTE CURRICULAR</th>
                        </tr>
                        <tr>
                            <td>{{ cabecalho.turma ?? '—' }}</td>
                            <td>{{ cabecalho.turno ?? '—' }}</td>
                            <td>{{ bloco.dis_nome }}</td>
                        </tr>
                    </tbody>
                </table>

                <table class="ba-tbl">
                    <thead>
                        <tr>
                            <th class="nome">Nome do Aluno</th>
                            <th class="rot"><span>Faltas</span></th>
                            <th v-for="u in unidades" :key="u.uni_id" class="rot"><span>{{ u.label }}</span></th>
                            <th class="rot"><span>M.F</span></th>
                            <th class="res">Resultado Final</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(a, i) in bloco.alunos" :key="i">
                            <td class="nome">{{ a.aln_nome }}</td>
                            <td>{{ a.faltas }}</td>
                            <td v-for="u in unidades" :key="u.uni_id">{{ a.medias[u.uni_id] || '' }}</td>
                            <td>{{ a.mf }}</td>
                            <td class="res">{{ a.resultado }}</td>
                        </tr>
                        <tr v-if="!bloco.alunos.length">
                            <td :colspan="unidades.length + 4" class="ba-vazio">Nenhum aluno nesta turma.</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Resultado: Boletim Avaliativo</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/boletim-avaliativo"><Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button></Link>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 rounded-xl border bg-card p-4 text-sm shadow-sm">
                <div><strong>Escola:</strong> {{ esc_nome }} · <strong>Turma:</strong> {{ cabecalho.turma }} <span v-if="cabecalho.turno" class="text-muted-foreground">· {{ cabecalho.turno }}</span></div>
                <div>
                    <strong>Ano Letivo:</strong> {{ cabecalho.anl_ano }} · {{ disciplinas.length }} disciplina(s)
                    <span v-if="cabecalho.media_aprovacao != null" class="text-muted-foreground">· Média p/ aprovação (numérica): {{ cabecalho.media_aprovacao }}</span>
                </div>
            </div>

            <div v-if="!disciplinas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhuma disciplina na grade desta turma.</div>

            <!-- Pré-visualização: 1 card por disciplina -->
            <div v-else class="flex flex-col gap-4">
                <div v-for="(bloco, bi) in disciplinas" :key="bi" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/40 px-4 py-2 text-sm">
                        <span class="font-semibold">{{ bloco.dis_nome }}</span>
                        <span class="text-xs text-muted-foreground">Prof.: {{ bloco.professor }}</span>
                    </div>
                    <div class="overflow-x-auto p-2">
                        <table class="w-full border-collapse text-center text-xs">
                            <thead class="bg-muted/30">
                                <tr>
                                    <th class="border px-2 py-1 text-left">Nome do Aluno</th>
                                    <th class="border px-2 py-1">Faltas</th>
                                    <th v-for="u in unidades" :key="u.uni_id" class="border px-2 py-1">{{ u.label }}</th>
                                    <th class="border px-2 py-1">M.F</th>
                                    <th class="border px-2 py-1">Resultado Final</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(a, i) in bloco.alunos" :key="i">
                                    <td class="border px-2 py-1 text-left">{{ a.aln_nome }}</td>
                                    <td class="border px-2 py-1 tabular-nums">{{ a.faltas }}</td>
                                    <td v-for="u in unidades" :key="u.uni_id" class="border px-2 py-1 tabular-nums">{{ a.medias[u.uni_id] || '—' }}</td>
                                    <td class="border px-2 py-1 font-semibold tabular-nums">{{ a.mf }}</td>
                                    <td class="border px-2 py-1">{{ a.resultado }}</td>
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
    @page boletim-aval { size: A4 portrait; margin: 6mm 8mm; }

    .ba-pagina {
        page: boletim-aval;
        page-break-after: always;
        break-after: page;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
        font-size: 8.5pt;
    }
    .ba-pagina:last-child { page-break-after: auto; break-after: auto; }

    .ba-cab { display: grid; grid-template-columns: 110px 1fr 150px; align-items: center; gap: 8px; border: 1px solid #000; padding: 6px 8px; }
    .ba-logo img { max-width: 100px; max-height: 70px; object-fit: contain; }
    .ba-cab-txt { line-height: 1.3; font-size: 8.5pt; }
    .ba-cab-txt .b { font-weight: 700; }
    .ba-cab-data { font-size: 8.5pt; line-height: 1.6; }

    .ba-title { border: 1px solid #000; border-top: none; text-align: center; font-weight: 700; font-size: 11pt; padding: 8px; }

    .ba-prof { border: 1px solid #000; border-top: none; padding: 4px 8px; font-size: 9pt; }

    .ba-meta { width: 100%; border-collapse: collapse; }
    .ba-meta th, .ba-meta td { border: 1px solid #000; text-align: center; padding: 3px 4px; }
    .ba-meta th { background: #f0f0f0; font-size: 8.5pt; }

    .ba-tbl { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .ba-tbl th, .ba-tbl td { border: 1px solid #000; text-align: center; padding: 2px 4px; }
    .ba-tbl thead th { background: #f7f7f7; vertical-align: bottom; }
    .ba-tbl .nome { text-align: left; width: 38%; }
    .ba-tbl .res { width: 90px; }
    .ba-tbl th.rot { height: 80px; padding: 2px 0; }
    .ba-tbl th.rot span { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; font-size: 8pt; font-weight: 700; margin: 0 auto; }
    .ba-vazio { padding: 12px; font-style: italic; }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
