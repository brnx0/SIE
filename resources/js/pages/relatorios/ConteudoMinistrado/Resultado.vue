<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface Dia {
    dt: string;
    periodo: string;
    plano_executado: boolean;
    conteudo: string | null;
    metodologia: string | null;
}
interface Disciplina { dis_nome: string; total: number; dias: Dia[] }
interface Parametros { nome_entidade: string; logomarca_url: string | null; brasao_url: string | null }

defineProps<{
    parametros: Parametros | null;
    filtros: { anl_ano: number; esc_nome: string; turma: string; periodo: string };
    consolidado: boolean;
    disciplinas: Disciplina[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Conteúdo Ministrado', href: '/relatorios/conteudo-ministrado' }];

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});
const imprimir = () => window.print();
</script>

<template>
    <Head title="Resultado — Conteúdo Ministrado" />

    <!-- Área de impressão -->
    <div id="print-area">
        <header class="cab">
            <div class="logo"><img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="" /></div>
            <div class="cab-textos">
                <div class="cab-entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
                <div class="cab-tit">Conteúdo Ministrado</div>
            </div>
            <div class="logo"><img v-if="parametros?.brasao_url" :src="parametros.brasao_url" alt="" /></div>
        </header>
        <div class="ident">
            <span><b>Ano:</b> {{ filtros.anl_ano }}</span>
            <span><b>Escola:</b> {{ filtros.esc_nome }}</span>
            <span><b>Turma:</b> {{ filtros.turma }}</span>
            <span><b>Período:</b> {{ filtros.periodo }}</span>
        </div>

        <p v-if="!disciplinas.length" class="vazio">Nenhum dia de aula registrado para os filtros selecionados.</p>

        <section v-for="(d, di) in disciplinas" :key="di" class="disc">
            <div class="disc-tit">{{ d.dis_nome }} <span class="disc-qtd">({{ d.total }} {{ d.total === 1 ? 'dia' : 'dias' }})</span></div>
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="w-data">Data</th>
                        <th v-if="consolidado" class="w-per">Período</th>
                        <th class="w-plan">Planej.</th>
                        <th class="esq">Conteúdo</th>
                        <th class="esq">Metodologia</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(dia, j) in d.dias" :key="j">
                        <td class="w-data">{{ dia.dt }}</td>
                        <td v-if="consolidado" class="w-per">{{ dia.periodo }}</td>
                        <td class="w-plan">{{ dia.plano_executado ? 'Sim' : 'Não' }}</td>
                        <td class="esq">{{ dia.conteudo || '—' }}</td>
                        <td class="esq">{{ dia.metodologia || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <footer class="rodape"><span>SIE Matrícula</span><span>Emitido em {{ dataEmissao }}</span></footer>
    </div>

    <!-- Tela -->
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6 print:py-2">
            <div class="mb-4 flex items-center justify-between print:hidden">
                <h1 class="text-xl font-semibold">Resultado: Conteúdo Ministrado</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/conteudo-ministrado"><Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button></Link>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 rounded-xl border bg-card p-4 text-sm shadow-sm">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }} · <strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Turma:</strong> {{ filtros.turma }} · <strong>Período:</strong> {{ filtros.periodo }}</div>
            </div>

            <div v-if="!disciplinas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum dia de aula registrado para os filtros selecionados.
            </div>

            <div v-else class="space-y-6">
                <section v-for="(d, di) in disciplinas" :key="di" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <header class="flex items-center justify-between gap-2 border-b bg-muted/40 px-4 py-2.5">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">{{ d.dis_nome }}</h2>
                        <span class="text-xs text-muted-foreground">{{ d.total }} {{ d.total === 1 ? 'dia' : 'dias' }}</span>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/20 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="w-24 px-3 py-2 text-left">Data</th>
                                    <th v-if="consolidado" class="w-28 px-3 py-2 text-left">Período</th>
                                    <th class="w-20 px-3 py-2 text-center">Planej.</th>
                                    <th class="px-3 py-2 text-left">Conteúdo</th>
                                    <th class="px-3 py-2 text-left">Metodologia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(dia, j) in d.dias" :key="j" class="align-top hover:bg-muted/20">
                                    <td class="whitespace-nowrap px-3 py-2 tabular-nums">{{ dia.dt }}</td>
                                    <td v-if="consolidado" class="whitespace-nowrap px-3 py-2 text-muted-foreground">{{ dia.periodo }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <span
                                            :class="[
                                                'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                                dia.plano_executado
                                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300'
                                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                            ]"
                                        >{{ dia.plano_executado ? 'Sim' : 'Não' }}</span>
                                    </td>
                                    <td class="whitespace-pre-wrap px-3 py-2">{{ dia.conteudo || '—' }}</td>
                                    <td class="whitespace-pre-wrap px-3 py-2 text-muted-foreground">{{ dia.metodologia || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
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
    body * { visibility: hidden !important; }
    #print-area, #print-area * { visibility: visible !important; }
    #print-area { display: block !important; position: absolute; left: 0; top: 0; width: 100%; font-family: 'Times New Roman', Times, serif; color: #000; }
    .cab { display: grid; grid-template-columns: 80px 1fr 80px; align-items: center; border-bottom: 2px solid #000; padding-bottom: 4px; }
    .logo img { max-width: 75px; max-height: 75px; object-fit: contain; }
    .cab-textos { text-align: center; }
    .cab-entidade { font-size: 12pt; font-weight: 700; text-transform: uppercase; }
    .cab-tit { font-size: 11pt; font-weight: 700; }
    .ident { display: flex; flex-wrap: wrap; gap: 16px; font-size: 9pt; margin: 8px 0; }
    .vazio { font-size: 9.5pt; font-style: italic; margin-top: 12px; }
    .disc { margin-top: 12px; break-inside: avoid; }
    .disc-tit { font-size: 10pt; font-weight: 700; background: #e5e5e5; border: 1px solid #000; border-bottom: 0; padding: 3px 6px; }
    .disc-qtd { font-weight: 400; font-size: 8.5pt; }
    .tbl { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    .tbl th, .tbl td { border: 1px solid #000; padding: 3px 5px; text-align: center; vertical-align: top; }
    .tbl th.esq, .tbl td.esq { text-align: left; white-space: pre-wrap; }
    .tbl thead th { background: #eee; }
    .tbl tbody tr { break-inside: avoid; }
    .w-data { width: 70px; white-space: nowrap; }
    .w-per { width: 80px; white-space: nowrap; }
    .w-plan { width: 48px; }
    .rodape { margin-top: 14px; padding-top: 6px; border-top: 1px solid #000; font-size: 8pt; display: flex; justify-content: space-between; font-style: italic; }
}
</style>
