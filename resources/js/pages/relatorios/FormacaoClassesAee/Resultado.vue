<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface Linha {
    num: number;
    turno: string | null;
    turma: string | null;
    sala: string;
    pcd: number;
    idade: string;
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Formação de Classes — AEE', href: '/relatorios/formacao-classes-aee' },
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
    <Head title="Formação de Classes — AEE" />

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
            <h1>Formação de Classes — AEE</h1>
        </div>

        <div class="resumo-filtros">
            <div><b>Ano:</b> {{ filtros.anl_ano }}</div>
            <div><b>Escola:</b> {{ filtros.esc_nome }}</div>
        </div>

        <table class="tabela-impressao">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Turno</th>
                    <th>Turma</th>
                    <th>Sala AEE</th>
                    <th>PCD</th>
                    <th>Idade</th>
                    <th>Capacidade</th>
                    <th>Expansão</th>
                    <th>Alunos da Rede</th>
                    <th>Alunos novos</th>
                    <th>Vagas</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="l in linhas" :key="`p-${l.num}`">
                    <td class="centro">{{ l.num }}</td>
                    <td>{{ l.turno }}</td>
                    <td>{{ l.turma }}</td>
                    <td>{{ l.sala }}</td>
                    <td class="centro">{{ l.pcd }}</td>
                    <td class="centro">{{ l.idade }}</td>
                    <td class="centro">{{ l.capacidade }}</td>
                    <td class="centro">{{ l.expansao }}</td>
                    <td class="centro">{{ l.rede }}</td>
                    <td class="centro">{{ l.novos }}</td>
                    <td class="centro"><b>{{ l.vagas }}</b></td>
                </tr>
                <tr v-if="linhas.length" class="linha-total">
                    <td colspan="4"><b>TOTAIS</b></td>
                    <td class="centro"><b>{{ totais.pcd }}</b></td>
                    <td></td>
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
                <h1 class="text-xl font-semibold">Resultado: Formação de Classes — AEE</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/formacao-classes-aee">
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
                Nenhuma turma AEE encontrada.
            </div>
            <div v-else class="rounded-xl border bg-card shadow-sm overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-muted/40">
                        <tr>
                            <th class="px-2 py-2 text-center">Nº</th>
                            <th class="px-2 py-2 text-left">Turno</th>
                            <th class="px-2 py-2 text-left">Turma</th>
                            <th class="px-2 py-2 text-left">Sala AEE</th>
                            <th class="px-2 py-2 text-center">PCD</th>
                            <th class="px-2 py-2 text-center">Idade</th>
                            <th class="px-2 py-2 text-center">Capacidade</th>
                            <th class="px-2 py-2 text-center">Expansão</th>
                            <th class="px-2 py-2 text-center">Rede</th>
                            <th class="px-2 py-2 text-center">Novos</th>
                            <th class="px-2 py-2 text-center">Vagas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="l in linhas" :key="l.num">
                            <td class="px-2 py-1.5 text-center">{{ l.num }}</td>
                            <td class="px-2 py-1.5">{{ l.turno }}</td>
                            <td class="px-2 py-1.5 font-medium">{{ l.turma }}</td>
                            <td class="px-2 py-1.5">{{ l.sala }}</td>
                            <td class="px-2 py-1.5 text-center">{{ l.pcd }}</td>
                            <td class="px-2 py-1.5 text-center">{{ l.idade }}</td>
                            <td class="px-2 py-1.5 text-center">{{ l.capacidade }}</td>
                            <td class="px-2 py-1.5 text-center">{{ l.expansao }}</td>
                            <td class="px-2 py-1.5 text-center">{{ l.rede }}</td>
                            <td class="px-2 py-1.5 text-center">{{ l.novos }}</td>
                            <td class="px-2 py-1.5 text-center font-semibold text-indigo-700">{{ l.vagas }}</td>
                        </tr>
                        <tr class="bg-muted/30 font-semibold">
                            <td colspan="4" class="px-2 py-1.5">TOTAIS</td>
                            <td class="px-2 py-1.5 text-center">{{ totais.pcd }}</td>
                            <td></td>
                            <td class="px-2 py-1.5 text-center">{{ totais.capacidade }}</td>
                            <td class="px-2 py-1.5 text-center">{{ totais.expansao }}</td>
                            <td class="px-2 py-1.5 text-center">{{ totais.rede }}</td>
                            <td class="px-2 py-1.5 text-center">{{ totais.novos }}</td>
                            <td class="px-2 py-1.5 text-center">{{ totais.vagas }}</td>
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
