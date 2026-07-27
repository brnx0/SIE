<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { useIsActiveTab } from '@/composables/useIsActiveTab';

interface Declaracao {
    aln_id: number | null;
    nome: string | null;
    matricula: string | null;
    dt_nascimento: string | null;
    turma_nome: string | null;
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

interface EscolaInfo {
    nome: string | null;
    endereco: string | null;
    zona: string | null;
    municipio: string | null;
    codigo: string | null;
}

const props = defineProps<{
    parametros: Parametros | null;
    escola: EscolaInfo;
    filtros: { anl_ano: number; anl_ano_prox: number; esc_nome: string };
    declaracoes: Declaracao[];
    total: number;
    dataEmissao: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Termo de Renovação', href: '/relatorios/termo-renovacao' },
];

const isActiveTab = useIsActiveTab();
const imprimir = () => window.print();

// Autorização (imagem/eventos) — texto padrão do termo, igual para todos.
const AUTORIZACAO = 'Autorizo meu filho(a) a participar de eventos escolares no decorrer do ano letivo dentro e fora do município utilizando o transporte escolar. Pela assinatura desta Declaração de Dados Cadastrais igualmente AUTORIZO o uso da imagem do(a) aluno(a) ______________________ o(a) qual está sob minha responsabilidade, em todo e qualquer material entre fotos, documentos e outros meios de comunicação, para ser utilizada em campanhas promocionais e institucionais da Escola, Secretaria e/ou Prefeitura Municipal, bem como fundos, fundações e autarquias dela derivados, sejam essas destinadas à divulgação ao público em geral e/ou apenas para uso interno desta instituição, desde que não haja desvirtuamento da sua finalidade. A presente autorização é concedida a título gratuito, abrangendo o uso da imagem acima mencionada em todo território nacional e no exterior, em todas as suas modalidades e, em destaque, das seguintes formas: (I) out-door; (II) busdoor; (III) folhetos em geral (encartes, mala direta, catálogo, etc.); (IV) Folder de apresentação; (V) anúncios em revistas e jornais em geral; (VI) home page; (VII) cartazes; (VIII) back-light; (IX) mídia.';

const municipioTermo = () => props.escola.municipio ?? (props.parametros?.municipio_nome
    ? props.parametros.municipio_nome + (props.parametros.municipio_uf ? ' (' + props.parametros.municipio_uf + ')' : '')
    : '________________');
</script>

<template>
    <Head title="Termo de Renovação" />

    <!-- Bloco impressão (teleportado p/ body; imprime só quando esta aba está ativa) -->
    <Teleport to="body" :disabled="!isActiveTab">
    <div id="print-area">
        <div v-for="(d, idx) in declaracoes" :key="d.aln_id ?? idx" class="folha-termo">
            <header class="cabecalho-impressao">
                <div class="logomarca">
                    <img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="Logomarca" />
                </div>
                <div class="cab-textos">
                    <div v-if="parametros?.msg_cab_estado" class="cab-linha">{{ parametros.msg_cab_estado }}</div>
                    <div v-if="parametros?.msg_cab_secretaria" class="cab-linha">{{ parametros.msg_cab_secretaria }}</div>
                    <div class="cab-entidade">{{ parametros?.nome_entidade ?? '' }}</div>
                </div>
                <div class="brasao">
                    <img v-if="parametros?.brasao_url" :src="parametros.brasao_url" alt="Brasão" />
                </div>
            </header>
            <div v-if="parametros?.endereco" class="cab-endereco">{{ parametros.endereco }}</div>

            <div class="titulo-termo">Termo de Renovação de Matrícula</div>

            <div class="quadro">
                <div class="linha">
                    <div class="celula full"><span class="rot">Unidade Escolar:</span> {{ escola.nome ?? '' }}</div>
                </div>
                <div class="linha">
                    <div class="celula g"><span class="rot">Endereço:</span> {{ escola.endereco ?? '' }}</div>
                    <div class="celula p"><span class="rot">Zona:</span> {{ escola.zona ?? '' }}</div>
                </div>
                <div class="linha">
                    <div class="celula g"><span class="rot">Município:</span> {{ escola.municipio ?? '' }}</div>
                    <div class="celula p"><span class="rot">Código:</span> {{ escola.codigo ?? '' }}</div>
                </div>
                <div class="linha">
                    <div class="celula full alto"><span class="rot">Nome do Aluno (a):</span> {{ d.nome ?? '' }}</div>
                </div>
            </div>

            <div class="corpo">
                Renovo a matrícula nesta Unidade Escolar para cursar o (a) <span class="preencher">{{ d.serie_nome ?? '' }}</span>
                do Ensino <span class="preencher">{{ d.segmento_nome ?? '' }}</span> no ano letivo de <b>{{ filtros.anl_ano_prox }}</b>.
            </div>

            <div class="local-data">
                {{ municipioTermo() }} ________ de ____________________ de {{ filtros.anl_ano_prox }}
            </div>

            <div class="autorizacao">{{ AUTORIZACAO }}</div>

            <div class="assinatura-box"></div>
            <div class="assinatura-legenda">Assinatura do Aluno (a) ou Responsável pela Matrícula</div>

            <div class="corte">┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈┈</div>
        </div>
    </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Termos de Renovação</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/termo-renovacao">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!total" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm">
                <div><strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Ano letivo das matrículas:</strong> {{ filtros.anl_ano }} · <strong>Renovação para:</strong> {{ filtros.anl_ano_prox }}</div>
                <div><strong>Total de termos:</strong> {{ total }}</div>
            </div>

            <div v-if="!total" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum aluno ativo encontrado para os filtros.
            </div>
            <div v-else class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">
                    {{ total }} termo(s) prontos. Clique em "Imprimir / PDF" para visualizar.
                </p>
                <ul class="mt-3 max-h-64 overflow-y-auto text-sm">
                    <li v-for="(d, i) in declaracoes" :key="d.aln_id ?? i" class="border-b py-1.5 last:border-0">
                        <strong>{{ i + 1 }}.</strong> {{ d.nome }}
                        <span class="text-xs text-muted-foreground">— {{ d.serie_nome }} {{ d.turma_nome }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 portrait; margin: 10mm 12mm; }
    body > *:not(#print-area) { display: none !important; }
    #print-area {
        display: block !important;
        position: static;
        width: 100%;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
        background: white;
        font-size: 10pt;
    }

    .folha-termo {
        page-break-after: always;
        break-after: page;
        page-break-inside: avoid;
    }
    .folha-termo:last-child { page-break-after: auto; break-after: auto; }

    .cabecalho-impressao {
        display: grid;
        grid-template-columns: 130px 1fr 90px;
        align-items: center;
        gap: 10px;
        padding-bottom: 4px;
        border-bottom: 2px solid #000;
    }
    .brasao img, .logomarca img { max-width: 125px; max-height: 90px; object-fit: contain; }
    .cab-textos { text-align: center; line-height: 1.3; }
    .cab-linha { font-size: 13pt; font-weight: 700; }
    .cab-linha:nth-child(2) { font-size: 11pt; }
    .cab-entidade { font-size: 10pt; font-weight: 700; margin-top: 2px; }
    .cab-endereco { text-align: center; font-size: 9pt; margin: 3px 0 10px; }

    .titulo-termo {
        border: 1px solid #000;
        text-align: center;
        font-size: 13pt;
        font-weight: 700;
        padding: 6px;
        margin-bottom: 0;
    }

    .quadro { border: 1px solid #000; border-top: none; }
    .quadro .linha { display: flex; border-top: 1px solid #000; }
    .quadro .linha:first-child { border-top: none; }
    .quadro .celula { padding: 5px 8px; font-size: 10.5pt; }
    .quadro .celula.full { flex: 1 1 100%; }
    .quadro .celula.g { flex: 1 1 70%; border-right: 1px solid #000; }
    .quadro .celula.p { flex: 1 1 30%; }
    .quadro .celula.alto { padding-top: 8px; padding-bottom: 20px; }
    .quadro .rot { font-size: 8.5pt; color: #000; }

    .corpo {
        text-align: justify;
        line-height: 2.1;
        font-size: 11pt;
        margin: 20px 0 10px;
    }
    .corpo .preencher {
        display: inline-block;
        min-width: 150px;
        border-bottom: 1px solid #000;
        text-align: center;
        font-weight: 600;
    }

    .local-data {
        text-align: right;
        font-weight: 700;
        font-size: 11pt;
        margin: 18px 0 16px;
    }

    .autorizacao {
        text-align: justify;
        font-size: 7.8pt;
        line-height: 1.35;
        margin-bottom: 26px;
    }

    .assinatura-box {
        border: 1px solid #000;
        height: 46px;
    }
    .assinatura-legenda {
        text-align: center;
        font-size: 9pt;
        margin-top: 3px;
    }

    .corte {
        margin-top: 12px;
        text-align: center;
        font-size: 9pt;
        letter-spacing: 1px;
        color: #000;
    }
}
</style>
