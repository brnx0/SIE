<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import RelatorioCabecalho from '@/components/relatorios/RelatorioCabecalho.vue';
import { Button } from '@/components/ui/button';
import BarrasComparativas from '@/components/relatorio/BarrasComparativas.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { useIsActiveTab } from '@/composables/useIsActiveTab';

interface Linha {
    mov_id: number;
    dt: string | null;
    estoque: string;
    item_nome: string;
    lote_codigo: string | null;
    lote_validade: string | null;
    qtd: number;
    umd_sigla: string | null;
    tipo_retirada: string;
    responsavel: string | null;
    vl_total: number | null;
    mov_obs: string | null;
}
interface Agregado { nome: string; linhas: number; valor: number }
interface Parametros {
    nome_entidade: string;
    msg_cab_secretaria: string | null;
    msg_cab_estado: string | null;
    endereco: string | null;
    municipio: string | null;
    logomarca_url: string | null;
    brasao_url: string | null;
}

const props = defineProps<{
    parametros: Parametros | null;
    filtros: { esc_nome: string; tpr_nome: string; dt_ini: string; dt_fim: string };
    linhas: Linha[];
    porUnidade: Agregado[];
    porTipo: Agregado[];
    total: number;
    valorTotal: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Retirada de Alimentos', href: '/relatorios/retirada-alimentos' },
];

const isActiveTab = useIsActiveTab();
const imprimir = () => window.print();

const num = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });
const moeda = (v: number | null) =>
    v === null ? '—' : v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const pct = (valor: number) => (props.valorTotal > 0 ? (valor / props.valorTotal) * 100 : 0);

// Altura acompanha a quantidade de barras: com poucas unidades um canvas alto
// deixaria barras gigantes, e com muitas elas ficariam espremidas.
const alturaGrafico = computed(() => Math.max(160, props.porUnidade.length * 34 + 60));

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});
</script>

<template>
    <Head title="Retirada de Alimentos" />

    <Teleport to="body" :disabled="!isActiveTab">
    <div id="print-area">
        <RelatorioCabecalho :parametros="parametros" />

        <div class="titulo-impressao"><h1>Retirada de Alimentos</h1></div>

        <div class="resumo-filtros">
            <div><b>Unidade:</b> {{ filtros.esc_nome }}</div>
            <div><b>Motivo:</b> {{ filtros.tpr_nome }}</div>
            <div><b>Período:</b> {{ filtros.dt_ini }} a {{ filtros.dt_fim }}</div>
            <div><b>Total retirado:</b> {{ moeda(valorTotal) }}</div>
        </div>

        <div v-if="porUnidade.length > 1" class="bloco-grafico">
            <h2 class="subtitulo">Comparativo por unidade escolar (R$)</h2>
            <BarrasComparativas :itens="porUnidade" rotulo="Retirado" fixo :largura="1000" :altura="alturaGrafico" />
        </div>

        <h2 class="subtitulo">Retiradas por motivo</h2>
        <table class="tabela-impressao">
            <thead>
                <tr>
                    <th class="col-nome">MOTIVO</th>
                    <th class="col-qtd">RETIRADAS</th>
                    <th class="col-vl">VALOR</th>
                    <th class="col-pct">% DO TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="t in porTipo" :key="`p-t-${t.nome}`">
                    <td>{{ t.nome }}</td>
                    <td class="centro">{{ t.linhas }}</td>
                    <td class="direita">{{ moeda(t.valor) }}</td>
                    <td class="centro">{{ pct(t.valor).toFixed(1) }}%</td>
                </tr>
                <tr v-if="!porTipo.length"><td colspan="4" class="centro">Nenhuma retirada no período.</td></tr>
            </tbody>
        </table>

        <h2 class="subtitulo">Detalhamento</h2>
        <table class="tabela-impressao">
            <thead>
                <tr>
                    <th class="col-dt">DATA</th>
                    <th class="col-est">ESTOQUE</th>
                    <th class="col-item">ITEM</th>
                    <th class="col-motivo">MOTIVO</th>
                    <th class="col-q">QTD.</th>
                    <th class="col-resp">RESPONSÁVEL</th>
                    <th class="col-v">VALOR</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="l in linhas" :key="`p-${l.mov_id}`">
                    <td class="centro">{{ l.dt }}</td>
                    <td>{{ l.estoque }}</td>
                    <td>{{ l.item_nome }}</td>
                    <td>{{ l.tipo_retirada }}</td>
                    <td class="centro">{{ num(l.qtd) }} {{ l.umd_sigla }}</td>
                    <td>{{ l.responsavel ?? '—' }}</td>
                    <td class="direita">{{ moeda(l.vl_total) }}</td>
                </tr>
                <tr v-if="!linhas.length"><td colspan="7" class="centro">Nenhuma retirada no período.</td></tr>
                <tr v-else class="linha-total">
                    <td colspan="6"><b>TOTAL — {{ total }} retirada(s)</b></td>
                    <td class="direita"><b>{{ moeda(valorTotal) }}</b></td>
                </tr>
            </tbody>
        </table>

        <footer class="rodape-impressao">
            <span>Documento gerado pelo sistema SIE Matrícula</span>
            <span>Emitido em {{ dataEmissao }}</span>
        </footer>
    </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Retirada de Alimentos</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/retirada-alimentos">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!linhas.length" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <div class="mb-4 grid gap-1 rounded-xl border bg-card p-4 text-sm shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><strong>Unidade:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Motivo:</strong> {{ filtros.tpr_nome }}</div>
                <div><strong>Período:</strong> {{ filtros.dt_ini }} a {{ filtros.dt_fim }}</div>
                <div><strong>Total retirado:</strong> {{ moeda(valorTotal) }} em {{ total }} retirada(s)</div>
            </div>

            <div v-if="!linhas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhuma retirada no período.
            </div>

            <template v-else>
                <div v-if="porUnidade.length > 1" class="mb-6 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="mb-1 font-semibold">Comparativo por unidade escolar</h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        Em R$, pelo custo médio do estoque na data da baixa. A comparação é por valor, e não por quantidade,
                        porque o relatório cruza itens medidos em KG, L e UN — somar essas quantidades daria um número sem significado.
                    </p>
                    <div :style="{ height: `${alturaGrafico}px` }">
                        <BarrasComparativas :itens="porUnidade" rotulo="Retirado" />
                    </div>
                </div>

                <div class="mb-6 overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="border-b bg-muted/40 px-4 py-2">
                        <h2 class="font-semibold">Retiradas por motivo</h2>
                    </div>
                    <table class="w-full text-xs">
                        <thead class="bg-indigo-50 dark:bg-indigo-900/30">
                            <tr>
                                <th class="px-3 py-2 text-left">Motivo</th>
                                <th class="px-3 py-2 text-right">Retiradas</th>
                                <th class="px-3 py-2 text-right">Valor</th>
                                <th class="px-3 py-2 text-right">% do total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="t in porTipo" :key="t.nome" class="hover:bg-muted/20">
                                <td class="px-3 py-1.5 font-medium">{{ t.nome }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ t.linhas }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ moeda(t.valor) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ pct(t.valor).toFixed(1) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                    <table class="w-full text-xs">
                        <thead class="bg-indigo-50 dark:bg-indigo-900/30">
                            <tr>
                                <th class="px-3 py-2 text-left">Data</th>
                                <th class="px-3 py-2 text-left">Estoque</th>
                                <th class="px-3 py-2 text-left">Item</th>
                                <th class="px-3 py-2 text-left">Lote</th>
                                <th class="px-3 py-2 text-left">Motivo</th>
                                <th class="px-3 py-2 text-right">Qtd.</th>
                                <th class="px-3 py-2 text-left">Responsável</th>
                                <th class="px-3 py-2 text-right">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="l in linhas" :key="l.mov_id" class="hover:bg-muted/20">
                                <td class="px-3 py-1.5 tabular-nums">{{ l.dt }}</td>
                                <td class="px-3 py-1.5">{{ l.estoque }}</td>
                                <td class="px-3 py-1.5 font-medium">{{ l.item_nome }}</td>
                                <td class="px-3 py-1.5">{{ l.lote_codigo ?? '—' }}</td>
                                <td class="px-3 py-1.5">{{ l.tipo_retirada }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ num(l.qtd) }} {{ l.umd_sigla }}</td>
                                <td class="px-3 py-1.5">{{ l.responsavel ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ moeda(l.vl_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 landscape; margin: 6mm 8mm 8mm 8mm; }
    body > *:not(#print-area) { display: none !important; }
    #print-area {
        display: block !important;
        position: static;
        width: 100%;
        font-family: 'Times New Roman', Times, serif;
        color: #000;
        background: white;
    }

    .cabecalho-impressao {
        display: grid;
        grid-template-columns: 90px 1fr 90px;
        align-items: center;
        gap: 8px;
        padding-bottom: 3px;
        border-bottom: 1.5px solid #000;
    }
    .brasao img, .logomarca img { max-width: 85px; max-height: 85px; object-fit: contain; }
    .cab-textos { text-align: center; line-height: 1.3; }
    .cab-linha { font-size: 9pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .cab-entidade { font-size: 11pt; font-weight: 700; text-transform: uppercase; margin: 1px 0; }
    .cab-end { font-size: 8pt; font-style: italic; }

    .titulo-impressao {
        text-align: center;
        margin: 6px 0 4px;
        border-bottom: 1px solid #000;
        padding-bottom: 3px;
    }
    .titulo-impressao h1 {
        font-size: 12pt;
        font-weight: 700;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1.2px;
    }

    .resumo-filtros { display: flex; gap: 18px; font-size: 9pt; margin: 4px 0 8px; }

    .subtitulo {
        font-size: 9.5pt;
        font-weight: 700;
        text-transform: uppercase;
        margin: 10px 0 4px;
    }

    .bloco-grafico { page-break-inside: avoid; }
    .bloco-grafico canvas { max-width: 100% !important; height: auto !important; }

    .tabela-impressao {
        width: 100%;
        border-collapse: collapse;
        font-size: 7.5pt;
        border: 1px solid #000;
        table-layout: fixed;
    }
    .tabela-impressao th {
        padding: 3px 2px;
        border: 1px solid #000;
        text-align: center;
        font-weight: 700;
        font-size: 6.5pt;
        text-transform: uppercase;
    }
    .tabela-impressao td { padding: 2px 4px; border: 1px solid #000; vertical-align: middle; word-wrap: break-word; }
    .tabela-impressao .centro { text-align: center; }
    .tabela-impressao .direita { text-align: right; }
    .tabela-impressao .col-nome { width: auto; }
    .tabela-impressao .col-qtd { width: 80px; }
    .tabela-impressao .col-vl { width: 90px; }
    .tabela-impressao .col-pct { width: 70px; }
    .tabela-impressao .col-dt { width: 60px; }
    .tabela-impressao .col-est { width: 130px; }
    .tabela-impressao .col-item { width: auto; }
    .tabela-impressao .col-motivo { width: 90px; }
    .tabela-impressao .col-q { width: 72px; }
    .tabela-impressao .col-resp { width: 120px; }
    .tabela-impressao .col-v { width: 76px; }
    .tabela-impressao .linha-total td { border-top: 2px solid #000; font-weight: 700; }

    .rodape-impressao {
        margin-top: 10px;
        padding-top: 3px;
        border-top: 1px solid #000;
        font-size: 8pt;
        display: flex;
        justify-content: space-between;
        font-style: italic;
    }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
