<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { useIsActiveTab } from '@/composables/useIsActiveTab';

interface Linha {
    sld_id: number;
    estoque: string;
    item_codigo: string | null;
    item_nome: string;
    categoria: string | null;
    lote_codigo: string | null;
    marca: string | null;
    lote_validade: string | null;
    dias_vencer: number | null;
    vencido: boolean;
    qtd: number;
    umd_sigla: string | null;
    valor_total: number;
}
interface Parametros {
    nome_entidade: string;
    msg_cab_secretaria: string | null;
    msg_cab_estado: string | null;
    endereco: string | null;
    logomarca_url: string | null;
    brasao_url: string | null;
}

const props = defineProps<{
    parametros: Parametros | null;
    filtros: { escopo_nome: string; dias: number; incluir_vencidos: boolean };
    linhas: Linha[];
    total: number;
    totalVencido: number;
    valorTotal: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Itens Próximos do Vencimento', href: '/relatorios/itens-vencimento' },
];

const isActiveTab = useIsActiveTab();
const imprimir = () => window.print();

const num = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });
const moeda = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const prazo = (l: Linha) =>
    l.vencido ? 'Vencido' : l.dias_vencer === null ? '—' : `${l.dias_vencer} dia(s)`;

const valorVencido = computed(() =>
    props.linhas.filter((l) => l.vencido).reduce((s, l) => s + l.valor_total, 0),
);

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});
</script>

<template>
    <Head title="Itens Próximos do Vencimento" />

    <Teleport to="body" :disabled="!isActiveTab">
    <div id="print-area">
        <header class="cabecalho-impressao">
            <div class="logomarca">
                <img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="Logomarca" />
            </div>
            <div class="cab-textos">
                <div v-if="parametros?.msg_cab_estado" class="cab-linha">{{ parametros.msg_cab_estado }}</div>
                <div v-if="parametros?.msg_cab_secretaria" class="cab-linha">{{ parametros.msg_cab_secretaria }}</div>
                <div class="cab-entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
                <div v-if="parametros?.endereco" class="cab-end">{{ parametros.endereco }}</div>
            </div>
            <div class="brasao">
                <img v-if="parametros?.brasao_url" :src="parametros.brasao_url" alt="Brasão" />
            </div>
        </header>

        <div class="titulo-impressao"><h1>Itens Próximos do Vencimento</h1></div>

        <div class="resumo-filtros">
            <div><b>Estoque:</b> {{ filtros.escopo_nome }}</div>
            <div><b>Prazo:</b> vence em até {{ filtros.dias }} dia(s)</div>
            <div><b>Vencidos:</b> {{ filtros.incluir_vencidos ? 'incluídos' : 'não incluídos' }}</div>
            <div><b>Valor em risco:</b> {{ moeda(valorTotal) }}</div>
        </div>

        <table class="tabela-impressao">
            <thead>
                <tr>
                    <th class="col-est">ESTOQUE</th>
                    <th class="col-item">ITEM</th>
                    <th class="col-lote">LOTE</th>
                    <th class="col-val">VALIDADE</th>
                    <th class="col-prazo">PRAZO</th>
                    <th class="col-qtd">SALDO</th>
                    <th class="col-vl">VALOR</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="l in linhas" :key="`p-${l.sld_id}`">
                    <td>{{ l.estoque }}</td>
                    <td>{{ l.item_nome }}<span v-if="l.marca"> ({{ l.marca }})</span></td>
                    <td class="centro">{{ l.lote_codigo ?? '—' }}</td>
                    <td class="centro">{{ l.lote_validade ?? '—' }}</td>
                    <td class="centro">{{ prazo(l) }}</td>
                    <td class="centro">{{ num(l.qtd) }} {{ l.umd_sigla }}</td>
                    <td class="direita">{{ moeda(l.valor_total) }}</td>
                </tr>
                <tr v-if="!linhas.length">
                    <td colspan="7" class="centro">Nenhum item vencendo no prazo informado.</td>
                </tr>
                <tr v-else class="linha-total">
                    <td colspan="5"><b>TOTAL — {{ total }} lote(s), {{ totalVencido }} já vencido(s)</b></td>
                    <td class="centro">—</td>
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
                <h1 class="text-xl font-semibold">Itens Próximos do Vencimento</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/itens-vencimento">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!linhas.length" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <div class="mb-4 grid gap-1 rounded-xl border bg-card p-4 text-sm shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><strong>Estoque:</strong> {{ filtros.escopo_nome }}</div>
                <div><strong>Prazo:</strong> vence em até {{ filtros.dias }} dia(s)</div>
                <div><strong>Lotes:</strong> {{ total }} ({{ totalVencido }} vencido(s))</div>
                <div><strong>Valor em risco:</strong> {{ moeda(valorTotal) }}</div>
            </div>

            <div v-if="totalVencido" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200">
                <strong>{{ totalVencido }} lote(s) já vencido(s)</strong>, somando {{ moeda(valorVencido) }}.
                Item vencido continua no saldo até alguém registrar a retirada por vencimento — enquanto isso, ele aparece como disponível na Consulta de Estoque.
            </div>

            <div v-if="!linhas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum item vencendo no prazo informado.
            </div>
            <div v-else class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <table class="w-full text-xs">
                    <thead class="bg-indigo-50 dark:bg-indigo-900/30">
                        <tr>
                            <th class="px-3 py-2 text-left">Estoque</th>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-left">Categoria</th>
                            <th class="px-3 py-2 text-left">Lote</th>
                            <th class="px-3 py-2 text-left">Validade</th>
                            <th class="px-3 py-2 text-left">Prazo</th>
                            <th class="px-3 py-2 text-right">Saldo</th>
                            <th class="px-3 py-2 text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="l in linhas" :key="l.sld_id" class="hover:bg-muted/20">
                            <td class="px-3 py-1.5">{{ l.estoque }}</td>
                            <td class="px-3 py-1.5">
                                <span class="font-medium">{{ l.item_nome }}</span>
                                <span v-if="l.marca" class="text-muted-foreground"> ({{ l.marca }})</span>
                            </td>
                            <td class="px-3 py-1.5">{{ l.categoria ?? '—' }}</td>
                            <td class="px-3 py-1.5">{{ l.lote_codigo ?? '—' }}</td>
                            <td class="px-3 py-1.5 tabular-nums">{{ l.lote_validade ?? '—' }}</td>
                            <td class="px-3 py-1.5">
                                <span
                                    v-if="l.vencido"
                                    class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-900/40 dark:text-rose-300"
                                >Vencido</span>
                                <span
                                    v-else
                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                >{{ l.dias_vencer }} dia(s)</span>
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ num(l.qtd) }} {{ l.umd_sigla }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ moeda(l.valor_total) }}</td>
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
    .tabela-impressao .col-est { width: 150px; }
    .tabela-impressao .col-item { width: auto; }
    .tabela-impressao .col-lote { width: 70px; }
    .tabela-impressao .col-val { width: 62px; }
    .tabela-impressao .col-prazo { width: 60px; }
    .tabela-impressao .col-qtd { width: 80px; }
    .tabela-impressao .col-vl { width: 76px; }
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
