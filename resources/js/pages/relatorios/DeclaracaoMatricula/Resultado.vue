<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';

interface Declaracao {
    aln_id: number | null;
    nome: string | null;
    dt_nascimento: string | null;
    municipio_nasc: string | null;
    filiacao_1: string | null;
    filiacao_2: string | null;
    turma_nome: string | null;
    turma_turno: string | null;
    serie_nome: string | null;
    segmento_nome: string | null;
}

interface Parametros {
    nome_entidade: string;
    msg_cab_secretaria: string | null;
    msg_cab_estado: string | null;
    endereco: string | null;
    logomarca_url: string | null;
    brasao_url: string | null;
    municipio_nome: string | null;
    municipio_uf: string | null;
}

const props = defineProps<{
    parametros: Parametros | null;
    filtros: { anl_ano: number; esc_nome: string };
    declaracoes: Declaracao[];
    total: number;
    dataEmissao: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Declaração de Matrícula', href: '/relatorios/declaracao-matricula' },
];

const imprimir = () => window.print();

const filiacaoText = (d: Declaracao) => {
    const a = d.filiacao_1?.trim();
    const b = d.filiacao_2?.trim();
    if (a && b) return `filho(a) de ${a} e de ${b}`;
    if (a) return `filho(a) de ${a}`;
    if (b) return `filho(a) de ${b}`;
    return '';
};

const localData = () => {
    const mun = props.parametros?.municipio_nome
        ? props.parametros.municipio_nome + (props.parametros.municipio_uf ? ' - ' + props.parametros.municipio_uf : '')
        : '—';
    return `${mun}, ${props.dataEmissao}`;
};
</script>

<template>
    <Head title="Declaração de Matrícula" />

    <!-- Bloco impressão -->
    <div id="print-area">
        <div v-for="(d, idx) in declaracoes" :key="d.aln_id ?? idx" class="folha-declaracao">
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

            <div class="titulo-declaracao">DECLARAÇÃO DE MATRÍCULA</div>

            <div class="corpo">
                Declaramos para os devidos fins de direitos, que o(a) aluno(a)
                <b>{{ d.nome }}</b><span v-if="d.dt_nascimento">, nascido(a) em {{ d.dt_nascimento }}</span><span v-if="d.municipio_nasc">, no município de {{ d.municipio_nasc }}</span><span v-if="filiacaoText(d)">, {{ filiacaoText(d) }}</span>, está regularmente matriculado(a) no(a)
                <b>{{ d.segmento_nome ?? '' }} - {{ d.serie_nome ?? '' }} {{ d.turma_nome ?? '' }}</b>,
                no turno {{ (d.turma_turno ?? '').toLowerCase() }}, neste estabelecimento de Ensino, no ano letivo de
                <b>{{ filtros.anl_ano }}</b>.
            </div>

            <div class="local-data">{{ localData() }}</div>

            <div class="assinatura">
                <div class="linha"></div>
                <div class="cargo">Assinatura do(a) Gestor(a) / Secretário(a)</div>
            </div>
        </div>
    </div>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Declarações Geradas</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/declaracao-matricula">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!total" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }}</div>
                <div><strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Total declarações:</strong> {{ total }}</div>
            </div>

            <div v-if="!total" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum aluno encontrado.
            </div>
            <div v-else class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">
                    {{ total }} declaração(ões) prontas. Clique em "Imprimir / PDF" para visualizar.
                </p>
                <ul class="mt-3 max-h-64 overflow-y-auto text-sm">
                    <li v-for="(d, i) in declaracoes" :key="d.aln_id ?? i" class="border-b py-1.5 last:border-0">
                        <strong>{{ i + 1 }}.</strong> {{ d.nome }} <span class="text-xs text-muted-foreground">— {{ d.turma_nome }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 portrait; margin: 15mm 20mm; }
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

    .folha-declaracao {
        page-break-after: always;
        padding: 0 12mm;
    }
    .folha-declaracao:last-child { page-break-after: auto; }

    .cabecalho-impressao {
        display: grid;
        grid-template-columns: 90px 1fr 90px;
        align-items: center;
        gap: 10px;
        padding-bottom: 4px;
        border-bottom: 2px solid #000;
    }
    .brasao img, .logomarca img { max-width: 85px; max-height: 85px; object-fit: contain; }
    .cab-textos { text-align: center; line-height: 1.35; }
    .cab-linha {
        font-size: 10pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .cab-entidade {
        font-size: 12pt;
        font-weight: 700;
        text-transform: uppercase;
        margin: 1px 0;
    }
    .cab-end { font-size: 9pt; font-style: italic; }

    .titulo-declaracao {
        margin: 28px auto 18px;
        border: 1.5px solid #000;
        padding: 8px 32px;
        font-size: 14pt;
        font-weight: 700;
        text-align: center;
        letter-spacing: 1.5px;
        width: fit-content;
    }

    .corpo {
        font-size: 12pt;
        text-align: justify;
        text-indent: 50px;
        line-height: 1.8;
        margin: 0 0 36px;
    }

    .local-data {
        font-size: 11pt;
        margin: 36px 0 0;
    }

    .assinatura {
        margin-top: 60px;
        text-align: center;
        page-break-inside: avoid;
    }
    .assinatura .linha {
        width: 360px;
        border-top: 1px solid #000;
        margin: 0 auto 4px;
    }
    .assinatura .cargo {
        font-size: 10pt;
    }
}
</style>
