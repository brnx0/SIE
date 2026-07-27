<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { useIsActiveTab } from '@/composables/useIsActiveTab';

interface Linha {
    fun_id: number;
    nome: string;
    matricula: string | null;
    cargo: string | null;
    escolaridade: string | null;
    unidade: string | null;
    vinculo: string | null;
    ativo: boolean;
    situacao: string;
    sem_lotacao: boolean;
}

interface Filtros {
    situacao: 'todos' | 'ativo' | 'inativo';
    esc_id: number | null;
    esc_nome: string | null;
    crg_id: number | null;
    crg_nome: string | null;
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
    filtros: Filtros;
    linhas: Linha[];
    resumo: {
        funcionarios: number;
        vinculos: number;
        ativos: number;
        inativos: number;
        sem_lotacao: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Relação de Funcionários', href: '/relatorios/relacao-funcionarios' },
];

const SITUACAO_LABEL: Record<string, string> = {
    todos: 'Todos',
    ativo: 'Somente ativos',
    inativo: 'Somente inativos',
};

const filtrosAtivos = computed(() => {
    const f = [`Situação: ${SITUACAO_LABEL[props.filtros.situacao] ?? 'Todos'}`];
    f.push(props.filtros.esc_nome ? `Unidade: ${props.filtros.esc_nome}` : 'Unidade: Todas');
    if (props.filtros.crg_nome) f.push(`Cargo: ${props.filtros.crg_nome}`);
    return f;
});

/** Larguras do print (%) — soma 100. */
const larguras = ['24%', '10%', '18%', '16%', '22%', '10%'];

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});

const isActiveTab = useIsActiveTab();
const imprimir = () => window.print();

const queryFiltros = () => {
    const p = new URLSearchParams();
    if (props.filtros.esc_id) p.set('esc_id', String(props.filtros.esc_id));
    if (props.filtros.crg_id) p.set('crg_id', String(props.filtros.crg_id));
    p.set('situacao', props.filtros.situacao);
    return p;
};

const exportarCsv = () => {
    const sep = ';';
    const aspas = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;

    const headers = ['Nome', 'Matrícula', 'Cargo/Função', 'Escolaridade', 'Unidade de Lotação', 'Vínculo', 'Situação'];

    const corpo = props.linhas.map((l) => [
        l.nome, l.matricula ?? '', l.cargo ?? '', l.escolaridade ?? '', l.unidade ?? '', l.vinculo ?? '', l.situacao,
    ]);

    const csv = '﻿' + [headers.join(sep), ...corpo.map((r) => r.map(aspas).join(sep))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'relacao-funcionarios.csv';
    a.click();
    URL.revokeObjectURL(url);
};

const exportarExcel = () => window.open(`/relatorios/relacao-funcionarios/xlsx?${queryFiltros().toString()}`, '_blank');
</script>

<template>
    <Head title="Resultado — Relação de Funcionários" />

    <!-- Bloco exclusivo p/ impressão -->
    <Teleport to="body" :disabled="!isActiveTab">
    <div id="print-area">
        <header class="cabecalho-impressao">
            <div class="logomarca">
                <img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="Logomarca do Município" />
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
            <h1>Relação de Funcionários</h1>
            <div class="subtitulo-impressao">
                <span v-for="(f, i) in filtrosAtivos" :key="`f-${i}`">{{ f }}</span>
                <span><b>Total:</b> {{ resumo.funcionarios }} funcionário(s)</span>
            </div>
        </div>

        <table class="tabela-impressao">
            <colgroup>
                <col v-for="(w, i) in larguras" :key="`c-${i}`" :style="{ width: w }" />
            </colgroup>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Matrícula</th>
                    <th>Cargo/Função</th>
                    <th>Escolaridade</th>
                    <th>Unidade de Lotação</th>
                    <th class="centro">Situação</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(l, i) in linhas" :key="`p-${i}`">
                    <td class="celula-nome">{{ l.nome }}</td>
                    <td>{{ l.matricula ?? '—' }}</td>
                    <td>{{ l.cargo ?? '—' }}</td>
                    <td>{{ l.escolaridade ?? '—' }}</td>
                    <td>{{ l.unidade ?? '—' }}</td>
                    <td class="centro">{{ l.situacao }}</td>
                </tr>
            </tbody>
        </table>

        <div class="resumo-impressao">
            <span><b>Funcionários:</b> {{ resumo.funcionarios }}</span>
            <span><b>Vínculos:</b> {{ resumo.vinculos }}</span>
            <span><b>Ativos:</b> {{ resumo.ativos }}</span>
            <span><b>Inativos:</b> {{ resumo.inativos }}</span>
            <span><b>Sem lotação:</b> {{ resumo.sem_lotacao }}</span>
        </div>

        <footer class="rodape-impressao">
            <span>Documento gerado pelo sistema SIE Matrícula</span>
            <span>Emitido em {{ dataEmissao }}</span>
        </footer>
    </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6 print:py-2">
            <div class="flex items-center justify-between mb-4 print:hidden">
                <h1 class="text-xl font-semibold">Resultado: Relação de Funcionários</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/relacao-funcionarios">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button variant="outline" @click="exportarCsv">CSV</Button>
                    <Button variant="outline" @click="exportarExcel">Excel</Button>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 flex flex-wrap gap-2">
                <span v-for="(f, i) in filtrosAtivos" :key="`fa-${i}`" class="rounded-full bg-muted px-3 py-0.5 text-xs">{{ f }}</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 mb-4 print:hidden">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Funcionários</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ resumo.funcionarios }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Vínculos de lotação</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ resumo.vinculos }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Ativos</div>
                    <div class="text-2xl font-semibold tabular-nums text-emerald-600">{{ resumo.ativos }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Inativos</div>
                    <div class="text-2xl font-semibold tabular-nums text-muted-foreground">{{ resumo.inativos }}</div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="text-xs text-muted-foreground">Sem lotação</div>
                    <div class="text-2xl font-semibold tabular-nums" :class="resumo.sem_lotacao ? 'text-amber-600' : ''">{{ resumo.sem_lotacao }}</div>
                </div>
            </div>

            <div v-if="!linhas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum funcionário encontrado para os filtros selecionados.
            </div>

            <div v-else class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="min-width: 1100px">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Nome</th>
                                <th class="px-3 py-2 text-left">Matrícula</th>
                                <th class="px-3 py-2 text-left">Cargo/Função</th>
                                <th class="px-3 py-2 text-left">Escolaridade</th>
                                <th class="px-3 py-2 text-left">Unidade de Lotação</th>
                                <th class="px-3 py-2 text-left">Vínculo</th>
                                <th class="px-3 py-2 text-center">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(l, i) in linhas" :key="`${l.fun_id}-${i}`" :class="!l.ativo ? 'bg-muted/20' : ''">
                                <td class="px-3 py-1.5 align-top text-muted-foreground tabular-nums">{{ i + 1 }}</td>
                                <td class="px-3 py-1.5 align-top font-medium break-words">{{ l.nome }}</td>
                                <td class="px-3 py-1.5 align-top tabular-nums">{{ l.matricula ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top break-words">{{ l.cargo ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top break-words text-xs">{{ l.escolaridade ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top break-words">
                                    <span v-if="l.sem_lotacao" class="text-amber-600 text-xs">Sem lotação vigente</span>
                                    <span v-else>{{ l.unidade }}</span>
                                </td>
                                <td class="px-3 py-1.5 align-top text-xs">{{ l.vinculo ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top text-center">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="l.ativo
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                                            : 'bg-muted text-muted-foreground'"
                                    >{{ l.situacao }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t bg-muted/30 px-4 py-2 text-sm font-medium">
                    {{ resumo.funcionarios }} funcionário(s) · {{ resumo.vinculos }} vínculo(s) · {{ resumo.ativos }} ativo(s) · {{ resumo.inativos }} inativo(s)
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 landscape; margin: 3mm 8mm 8mm 8mm; }
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
        grid-template-columns: 110px 1fr 110px;
        align-items: center;
        gap: 10px;
        padding-bottom: 4px;
        margin-top: 0;
        border-bottom: 2px solid #000;
    }
    .brasao img, .logomarca img { max-width: 105px; max-height: 105px; object-fit: contain; }
    .cab-textos { text-align: center; line-height: 1.35; }
    .cab-linha { font-size: 10pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .cab-entidade { font-size: 12pt; font-weight: 700; text-transform: uppercase; margin: 1px 0; }
    .cab-end { font-size: 9pt; font-style: italic; }

    .titulo-impressao {
        text-align: center;
        margin: 12px 0 8px;
        border-bottom: 1px solid #000;
        padding-bottom: 6px;
    }
    .titulo-impressao h1 {
        font-size: 13pt;
        font-weight: 700;
        margin: 0 0 4px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }
    .subtitulo-impressao {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 14px;
        font-size: 9pt;
    }

    .tabela-impressao {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 8pt;
        border: 1px solid #000;
        margin-top: 8px;
    }
    .tabela-impressao th {
        background: transparent;
        color: #000;
        padding: 4px 5px;
        border: 1px solid #000;
        text-align: left;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 7.5pt;
        letter-spacing: 0.3px;
        overflow-wrap: break-word;
    }
    .tabela-impressao td {
        padding: 3px 4px;
        border: 1px solid #000;
        vertical-align: top;
        overflow-wrap: break-word;
        word-break: break-word;
    }
    .tabela-impressao .centro { text-align: center; }
    .tabela-impressao .celula-nome { font-weight: 600; }

    .resumo-impressao {
        border: 1px solid #000;
        border-top: none;
        padding: 3px 8px;
        font-size: 9pt;
        font-weight: 700;
        display: flex;
        gap: 18px;
        flex-wrap: wrap;
    }

    .rodape-impressao {
        margin-top: 24px;
        padding-top: 6px;
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
