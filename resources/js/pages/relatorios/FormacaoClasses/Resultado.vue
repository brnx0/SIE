<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface Linha {
    num: number;
    segmento: string | null;
    turno: string | null;
    serie: string | null;
    turma: string | null;
    fluxo: string;
    pcd: number;
    idade: string;
    sala: string;
    capacidade: number;
    expansao: number;
    rede: number;
    novos: number;
    vagas: number;
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
    filtros: { anl_ano: number; esc_nome: string };
    linhas: Linha[];
    total: number;
}>();

const grupos = computed(() => {
    const map = new Map<string, Linha[]>();
    for (const l of props.linhas) {
        const k = l.segmento ?? '—';
        if (!map.has(k)) map.set(k, []);
        map.get(k)!.push(l);
    }
    return [...map.entries()];
});

const totaisGrupo = (lista: Linha[]) =>
    lista.reduce((acc, l) => ({
        capacidade: acc.capacidade + l.capacidade,
        expansao:   acc.expansao + l.expansao,
        pcd:        acc.pcd + l.pcd,
        rede:       acc.rede + l.rede,
        novos:      acc.novos + l.novos,
        vagas:      acc.vagas + l.vagas,
    }), { capacidade: 0, expansao: 0, pcd: 0, rede: 0, novos: 0, vagas: 0 });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Formação de Classes', href: '/relatorios/formacao-classes' },
];

const imprimir = () => window.print();

const totais = computed(() => {
    return props.linhas.reduce((acc, l) => ({
        capacidade: acc.capacidade + l.capacidade,
        expansao:   acc.expansao + l.expansao,
        pcd:        acc.pcd + l.pcd,
        rede:       acc.rede + l.rede,
        novos:      acc.novos + l.novos,
        vagas:      acc.vagas + l.vagas,
    }), { capacidade: 0, expansao: 0, pcd: 0, rede: 0, novos: 0, vagas: 0 });
});

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});
</script>

<template>
    <Head title="Formação de Classes" />

    <!-- Bloco impressão -->
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
            <h1>Formação de Classes</h1>
        </div>

        <div class="resumo-filtros">
            <div><b>Ano:</b> {{ filtros.anl_ano }}</div>
            <div><b>Escola:</b> {{ filtros.esc_nome }}</div>
        </div>

        <div v-for="[seg, lista] in grupos" :key="`p-${seg}`" class="grupo-segmento">
            <div class="grupo-titulo">Segmento: {{ seg }}</div>
            <table class="tabela-impressao">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Turno</th>
                        <th>Ano/Série</th>
                        <th>Turma</th>
                        <th>Fluxo</th>
                        <th>PCD</th>
                        <th>Idade</th>
                        <th>Sala</th>
                        <th>Capacidade</th>
                        <th>Expansão</th>
                        <th>Alunos da Rede</th>
                        <th>Alunos novos</th>
                        <th>Vagas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(l, i) in lista" :key="`p-${seg}-${l.num}`">
                        <td class="centro">{{ i + 1 }}</td>
                        <td>{{ l.turno }}</td>
                        <td>{{ l.serie }}</td>
                        <td>{{ l.turma }}</td>
                        <td class="centro">{{ l.fluxo }}</td>
                        <td class="centro">{{ l.pcd }}</td>
                        <td class="centro">{{ l.idade }}</td>
                        <td class="centro">{{ l.sala }}</td>
                        <td class="centro">{{ l.capacidade }}</td>
                        <td class="centro">{{ l.expansao }}</td>
                        <td class="centro">{{ l.rede }}</td>
                        <td class="centro">{{ l.novos }}</td>
                        <td class="centro"><b>{{ l.vagas }}</b></td>
                    </tr>
                    <tr class="linha-total">
                        <td colspan="5"><b>SUBTOTAL</b></td>
                        <td class="centro"><b>{{ totaisGrupo(lista).pcd }}</b></td>
                        <td colspan="2"></td>
                        <td class="centro"><b>{{ totaisGrupo(lista).capacidade }}</b></td>
                        <td class="centro"><b>{{ totaisGrupo(lista).expansao }}</b></td>
                        <td class="centro"><b>{{ totaisGrupo(lista).rede }}</b></td>
                        <td class="centro"><b>{{ totaisGrupo(lista).novos }}</b></td>
                        <td class="centro"><b>{{ totaisGrupo(lista).vagas }}</b></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <table v-if="linhas.length" class="tabela-impressao total-geral-tabela">
            <tbody>
                <tr class="linha-total-geral">
                    <td colspan="5"><b>TOTAL GERAL</b></td>
                    <td class="centro"><b>{{ totais.pcd }}</b></td>
                    <td colspan="2"></td>
                    <td class="centro"><b>{{ totais.capacidade }}</b></td>
                    <td class="centro"><b>{{ totais.expansao }}</b></td>
                    <td class="centro"><b>{{ totais.rede }}</b></td>
                    <td class="centro"><b>{{ totais.novos }}</b></td>
                    <td class="centro"><b>{{ totais.vagas }}</b></td>
                </tr>
            </tbody>
        </table>

        <footer class="rodape-impressao">
            <span>Documento gerado pelo sistema SIE Matrícula</span>
            <span>Emitido em {{ dataEmissao }}</span>
        </footer>
    </div>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Resultado: Formação de Classes</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/formacao-classes">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!total" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm grid gap-1 sm:grid-cols-3">
                <div><strong>Ano:</strong> {{ filtros.anl_ano }}</div>
                <div><strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Total turmas:</strong> {{ total }}</div>
            </div>

            <div v-if="!total" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhuma turma encontrada.
            </div>
            <div v-for="[seg, lista] in grupos" :key="seg" class="mb-6 rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="border-b bg-indigo-50 px-4 py-2 font-semibold text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-200">
                    Segmento: {{ seg }} <span class="text-xs font-normal text-muted-foreground">({{ lista.length }} turma{{ lista.length !== 1 ? 's' : '' }})</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-2 py-2 text-center">Nº</th>
                                <th class="px-2 py-2 text-left">Turno</th>
                                <th class="px-2 py-2 text-left">Ano/Série</th>
                                <th class="px-2 py-2 text-left">Turma</th>
                                <th class="px-2 py-2 text-center">Fluxo</th>
                                <th class="px-2 py-2 text-center">PCD</th>
                                <th class="px-2 py-2 text-center">Idade</th>
                                <th class="px-2 py-2 text-center">Sala</th>
                                <th class="px-2 py-2 text-center">Capacidade</th>
                                <th class="px-2 py-2 text-center">Expansão</th>
                                <th class="px-2 py-2 text-center">Rede</th>
                                <th class="px-2 py-2 text-center">Novos</th>
                                <th class="px-2 py-2 text-center">Vagas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(l, i) in lista" :key="`s-${seg}-${l.num}`">
                                <td class="px-2 py-1.5 text-center">{{ i + 1 }}</td>
                                <td class="px-2 py-1.5">{{ l.turno }}</td>
                                <td class="px-2 py-1.5">{{ l.serie }}</td>
                                <td class="px-2 py-1.5 font-medium">{{ l.turma }}</td>
                                <td class="px-2 py-1.5 text-center">{{ l.fluxo }}</td>
                                <td class="px-2 py-1.5 text-center">{{ l.pcd }}</td>
                                <td class="px-2 py-1.5 text-center">{{ l.idade }}</td>
                                <td class="px-2 py-1.5 text-center">{{ l.sala }}</td>
                                <td class="px-2 py-1.5 text-center">{{ l.capacidade }}</td>
                                <td class="px-2 py-1.5 text-center">{{ l.expansao }}</td>
                                <td class="px-2 py-1.5 text-center">{{ l.rede }}</td>
                                <td class="px-2 py-1.5 text-center">{{ l.novos }}</td>
                                <td class="px-2 py-1.5 text-center font-semibold text-indigo-700">{{ l.vagas }}</td>
                            </tr>
                            <tr class="bg-muted/30 font-semibold">
                                <td colspan="5" class="px-2 py-1.5">SUBTOTAL</td>
                                <td class="px-2 py-1.5 text-center">{{ totaisGrupo(lista).pcd }}</td>
                                <td colspan="2"></td>
                                <td class="px-2 py-1.5 text-center">{{ totaisGrupo(lista).capacidade }}</td>
                                <td class="px-2 py-1.5 text-center">{{ totaisGrupo(lista).expansao }}</td>
                                <td class="px-2 py-1.5 text-center">{{ totaisGrupo(lista).rede }}</td>
                                <td class="px-2 py-1.5 text-center">{{ totaisGrupo(lista).novos }}</td>
                                <td class="px-2 py-1.5 text-center">{{ totaisGrupo(lista).vagas }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-if="total" class="rounded-xl border bg-indigo-50 dark:bg-indigo-900/20 p-4 text-sm font-semibold grid gap-1 sm:grid-cols-7">
                <div class="sm:col-span-2">TOTAL GERAL</div>
                <div class="text-center">PCD: {{ totais.pcd }}</div>
                <div class="text-center">Cap: {{ totais.capacidade }}</div>
                <div class="text-center">Exp: {{ totais.expansao }}</div>
                <div class="text-center">Rede: {{ totais.rede }}</div>
                <div class="text-center">Novos: {{ totais.novos }}</div>
                <div class="text-center">Vagas: {{ totais.vagas }}</div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 landscape; margin: 3mm 10mm 8mm 10mm; }
    body * { visibility: hidden !important; }
    #print-area, #print-area * { visibility: visible !important; }
    #print-area {
        display: block !important;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        font-family: 'Times New Roman', Times, serif;
        color: #000;
        background: white;
    }

    .cabecalho-impressao {
        display: grid;
        grid-template-columns: 110px 1fr 110px;
        align-items: center;
        gap: 10px;
        padding-bottom: 4px;
        border-bottom: 2px solid #000;
    }
    .brasao img, .logomarca img { max-width: 105px; max-height: 105px; object-fit: contain; }
    .cab-textos { text-align: center; line-height: 1.35; }
    .cab-linha { font-size: 10pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .cab-entidade { font-size: 12pt; font-weight: 700; text-transform: uppercase; margin: 1px 0; }
    .cab-end { font-size: 9pt; font-style: italic; }

    .titulo-impressao {
        text-align: center;
        margin: 10px 0 6px;
        border-bottom: 1px solid #000;
        padding-bottom: 4px;
    }
    .titulo-impressao h1 {
        font-size: 13pt;
        font-weight: 700;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }

    .resumo-filtros {
        display: flex;
        gap: 22px;
        font-size: 10pt;
        margin: 4px 0 10px;
    }

    .tabela-impressao {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
        border: 1px solid #000;
    }
    .tabela-impressao th {
        padding: 4px 5px;
        border: 1px solid #000;
        text-align: left;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 7.5pt;
        letter-spacing: 0.3px;
    }
    .tabela-impressao td {
        padding: 3px 5px;
        border: 1px solid #000;
        vertical-align: top;
    }
    .tabela-impressao .centro { text-align: center; }
    .tabela-impressao .linha-total td { font-weight: 700; }
    .tabela-impressao .linha-total-geral td {
        font-weight: 700;
        border-top: 2px solid #000;
        border-bottom: 2px solid #000;
    }

    .grupo-segmento { margin: 10px 0 6px; page-break-inside: avoid; }
    .grupo-titulo {
        border: 1px solid #000;
        border-bottom: none;
        padding: 3px 8px;
        font-size: 10pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .total-geral-tabela { margin-top: 8px; }

    .rodape-impressao {
        margin-top: 16px;
        padding-top: 4px;
        border-top: 1px solid #000;
        font-size: 8.5pt;
        display: flex;
        justify-content: space-between;
        font-style: italic;
    }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
