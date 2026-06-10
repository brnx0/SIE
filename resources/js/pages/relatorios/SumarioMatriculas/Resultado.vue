<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface SerieCol { ser_id: number; ser_nome: string; seg_nome: string | null }
interface Linha {
    esc_id: number;
    esc_nome: string;
    valores: number[];
    total: number;
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
    filtros: { anl_ano: number };
    series: SerieCol[];
    linhas: Linha[];
    totaisColuna: number[];
    totalGeral: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sumário de Matrículas', href: '/relatorios/sumario-matriculas' },
];

const imprimir = () => window.print();

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});
</script>

<template>
    <Head title="Sumário de Matrículas" />

    <!-- Bloco impressão -->
    <Teleport to="body">
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

        <div class="titulo-impressao">
            <h1>Sumário de Matrículas</h1>
        </div>

        <div class="resumo-filtros">
            <div><b>Ano Letivo:</b> {{ filtros.anl_ano }}</div>
            <div><b>Total geral:</b> {{ totalGeral }} matrícula(s)</div>
        </div>

        <table class="tabela-impressao">
            <thead>
                <tr>
                    <th class="col-escola">ESCOLA</th>
                    <th v-for="s in series" :key="`p-h-${s.ser_id}`" class="col-num">{{ s.ser_nome }}</th>
                    <th class="col-total">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="l in linhas" :key="`p-${l.esc_id}`">
                    <td class="col-escola">{{ l.esc_nome }}</td>
                    <td v-for="(v, i) in l.valores" :key="`p-${l.esc_id}-${i}`" class="centro">{{ v }}</td>
                    <td class="centro col-total"><b>{{ l.total }}</b></td>
                </tr>
                <tr class="linha-total">
                    <td><b>TOTAIS</b></td>
                    <td v-for="(v, i) in totaisColuna" :key="`p-tot-${i}`" class="centro"><b>{{ v }}</b></td>
                    <td class="centro"><b>{{ totalGeral }}</b></td>
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
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Sumário de Matrículas</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/sumario-matriculas">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!linhas.length" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm grid gap-1 sm:grid-cols-2">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }}</div>
                <div><strong>Total geral:</strong> {{ totalGeral }} matrícula(s)</div>
            </div>

            <div v-if="!linhas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhuma escola encontrada.
            </div>
            <div v-else class="rounded-xl border bg-card shadow-sm overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-indigo-50 dark:bg-indigo-900/30">
                        <tr>
                            <th class="px-2 py-2 text-left sticky left-0 bg-indigo-50 dark:bg-indigo-900/30 z-10">ESCOLA</th>
                            <th v-for="s in series" :key="s.ser_id" class="px-2 py-2 text-center font-semibold" :title="s.seg_nome ?? ''">
                                {{ s.ser_nome }}
                            </th>
                            <th class="px-2 py-2 text-center bg-indigo-100 dark:bg-indigo-900/50">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="l in linhas" :key="l.esc_id" class="hover:bg-muted/20">
                            <td class="px-2 py-1.5 sticky left-0 bg-card z-10 font-medium">{{ l.esc_nome }}</td>
                            <td v-for="(v, i) in l.valores" :key="`s-${l.esc_id}-${i}`" class="px-2 py-1.5 text-center tabular-nums">
                                <span :class="v === 0 ? 'text-muted-foreground' : ''">{{ v }}</span>
                            </td>
                            <td class="px-2 py-1.5 text-center font-semibold text-indigo-700">{{ l.total }}</td>
                        </tr>
                        <tr class="bg-muted/40 font-semibold">
                            <td class="px-2 py-1.5 sticky left-0 bg-muted/40 z-10">TOTAIS</td>
                            <td v-for="(v, i) in totaisColuna" :key="`s-tot-${i}`" class="px-2 py-1.5 text-center">{{ v }}</td>
                            <td class="px-2 py-1.5 text-center text-indigo-800">{{ totalGeral }}</td>
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
    @page { size: A4 landscape; margin: 4mm 8mm 8mm 8mm; }
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

    .resumo-filtros {
        display: flex;
        gap: 18px;
        font-size: 9pt;
        margin: 4px 0 8px;
    }

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
        word-wrap: break-word;
    }
    .tabela-impressao td {
        padding: 2px 3px;
        border: 1px solid #000;
        vertical-align: middle;
    }
    .tabela-impressao .centro { text-align: center; }
    .tabela-impressao .col-escola {
        width: 180px;
        text-align: left;
        font-weight: 600;
    }
    .tabela-impressao .col-total {
        width: 40px;
        font-weight: 700;
    }
    .tabela-impressao .col-num { width: auto; }
    .tabela-impressao .linha-total td {
        border-top: 2px solid #000;
        font-weight: 700;
    }

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
