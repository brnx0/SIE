<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { useIsActiveTab } from '@/composables/useIsActiveTab';

interface Linha {
    mov_id: number;
    dt: string | null;
    item_codigo: string | null;
    item_nome: string;
    lote_codigo: string | null;
    lote_validade: string | null;
    marca: string | null;
    qtd: number;
    umd_sigla: string | null;
    vl_total: number | null;
}
interface Unidade {
    esc_id: number;
    esc_nome: string;
    linhas: Linha[];
    total_linhas: number;
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

defineProps<{
    parametros: Parametros | null;
    filtros: { esc_nome: string; dt_ini: string; dt_fim: string };
    unidades: Unidade[];
    valorGeral: number;
    totalLinhas: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Protocolo da Central de Distribuição', href: '/relatorios/protocolo-distribuicao' },
];

const isActiveTab = useIsActiveTab();
const imprimir = () => window.print();

const num = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });
const moeda = (v: number | null) =>
    v === null ? '—' : v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});
</script>

<template>
    <Head title="Protocolo da Central de Distribuição" />

    <!-- Bloco impressão: uma folha por unidade, porque cada uma assina a sua. -->
    <Teleport to="body" :disabled="!isActiveTab">
    <div id="print-area">
        <section v-for="(u, idx) in unidades" :key="`p-${u.esc_id}`" :class="{ 'quebra-pagina': idx > 0 }">
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

            <div class="titulo-impressao">
                <h1>Protocolo da Central de Distribuição</h1>
            </div>

            <div class="resumo-filtros">
                <div><b>Unidade:</b> {{ u.esc_nome }}</div>
                <div><b>Período:</b> {{ filtros.dt_ini }} a {{ filtros.dt_fim }}</div>
            </div>

            <table class="tabela-impressao">
                <thead>
                    <tr>
                        <th class="col-dt">DATA</th>
                        <th class="col-item">ITEM</th>
                        <th class="col-lote">LOTE</th>
                        <th class="col-val">VALIDADE</th>
                        <th class="col-qtd">QTD.</th>
                        <th class="col-vl">VALOR</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in u.linhas" :key="`p-l-${l.mov_id}`">
                        <td class="centro">{{ l.dt }}</td>
                        <td>{{ l.item_nome }}<span v-if="l.marca"> ({{ l.marca }})</span></td>
                        <td class="centro">{{ l.lote_codigo ?? '—' }}</td>
                        <td class="centro">{{ l.lote_validade ?? '—' }}</td>
                        <td class="centro">{{ num(l.qtd) }} {{ l.umd_sigla }}</td>
                        <td class="direita">{{ moeda(l.vl_total) }}</td>
                    </tr>
                    <tr class="linha-total">
                        <td colspan="4"><b>TOTAL — {{ u.total_linhas }} entrega(s)</b></td>
                        <td class="centro">—</td>
                        <td class="direita"><b>{{ moeda(u.valor_total) }}</b></td>
                    </tr>
                </tbody>
            </table>

            <div class="assinaturas">
                <div class="assinatura">
                    <div class="linha-assinatura"></div>
                    <div class="rotulo">Entregue por (Central de Distribuição)</div>
                </div>
                <div class="assinatura">
                    <div class="linha-assinatura"></div>
                    <div class="rotulo">Recebido por (Unidade Escolar) — Nome, cargo e data</div>
                </div>
            </div>

            <footer class="rodape-impressao">
                <span>Documento gerado pelo sistema SIE Matrícula</span>
                <span>Emitido em {{ dataEmissao }}</span>
            </footer>
        </section>

        <section v-if="!unidades.length">
            <div class="titulo-impressao"><h1>Protocolo da Central de Distribuição</h1></div>
            <p style="text-align:center;padding:20px">Nenhuma distribuição no período.</p>
        </section>
    </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Protocolo da Central de Distribuição</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/protocolo-distribuicao">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!unidades.length" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <div class="mb-4 grid gap-1 rounded-xl border bg-card p-4 text-sm shadow-sm sm:grid-cols-3">
                <div><strong>Unidade:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Período:</strong> {{ filtros.dt_ini }} a {{ filtros.dt_fim }}</div>
                <div><strong>Total distribuído:</strong> {{ moeda(valorGeral) }} em {{ totalLinhas }} entrega(s)</div>
            </div>

            <div v-if="!unidades.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhuma distribuição da central no período.
            </div>

            <div v-for="u in unidades" :key="u.esc_id" class="mb-6 overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b bg-muted/40 px-4 py-2">
                    <h2 class="font-semibold">{{ u.esc_nome }}</h2>
                    <span class="text-sm text-muted-foreground">
                        {{ u.total_linhas }} entrega(s) · <strong class="text-indigo-700">{{ moeda(u.valor_total) }}</strong>
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-indigo-50 dark:bg-indigo-900/30">
                            <tr>
                                <th class="px-3 py-2 text-left">Data</th>
                                <th class="px-3 py-2 text-left">Item</th>
                                <th class="px-3 py-2 text-left">Lote</th>
                                <th class="px-3 py-2 text-left">Validade</th>
                                <th class="px-3 py-2 text-right">Qtd.</th>
                                <th class="px-3 py-2 text-right">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="l in u.linhas" :key="l.mov_id" class="hover:bg-muted/20">
                                <td class="px-3 py-1.5 tabular-nums">{{ l.dt }}</td>
                                <td class="px-3 py-1.5">
                                    <span class="font-medium">{{ l.item_nome }}</span>
                                    <span v-if="l.marca" class="text-muted-foreground"> ({{ l.marca }})</span>
                                </td>
                                <td class="px-3 py-1.5">{{ l.lote_codigo ?? '—' }}</td>
                                <td class="px-3 py-1.5 tabular-nums">{{ l.lote_validade ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ num(l.qtd) }} {{ l.umd_sigla }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ moeda(l.vl_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 portrait; margin: 6mm 8mm 8mm 8mm; }
    body > *:not(#print-area) { display: none !important; }
    #print-area {
        display: block !important;
        position: static;
        width: 100%;
        font-family: 'Times New Roman', Times, serif;
        color: #000;
        background: white;
    }

    .quebra-pagina { page-break-before: always; }

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
        font-size: 8pt;
        border: 1px solid #000;
        table-layout: fixed;
    }
    .tabela-impressao th {
        padding: 3px 2px;
        border: 1px solid #000;
        text-align: center;
        font-weight: 700;
        font-size: 7pt;
        text-transform: uppercase;
    }
    .tabela-impressao td { padding: 2px 4px; border: 1px solid #000; vertical-align: middle; word-wrap: break-word; }
    .tabela-impressao .centro { text-align: center; }
    .tabela-impressao .direita { text-align: right; }
    .tabela-impressao .col-dt { width: 62px; }
    .tabela-impressao .col-item { width: auto; text-align: left; }
    .tabela-impressao .col-lote { width: 70px; }
    .tabela-impressao .col-val { width: 62px; }
    .tabela-impressao .col-qtd { width: 78px; }
    .tabela-impressao .col-vl { width: 76px; }
    .tabela-impressao .linha-total td { border-top: 2px solid #000; font-weight: 700; }

    .assinaturas { display: flex; gap: 40px; margin-top: 34px; page-break-inside: avoid; }
    .assinatura { flex: 1; text-align: center; }
    .linha-assinatura { border-top: 1px solid #000; margin-bottom: 3px; }
    .assinatura .rotulo { font-size: 8pt; }

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
