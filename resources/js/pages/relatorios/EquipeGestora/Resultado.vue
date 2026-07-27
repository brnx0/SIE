<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { useIsActiveTab } from '@/composables/useIsActiveTab';

interface Gestor {
    fun_id: number;
    nome: string | null;
    cargo: string | null;
    telefone: string | null;
    email: string | null;
    vinculo: string | null;
    desde: string | null;
}

interface Linha {
    esc_id: number;
    esc_nome: string;
    esc_codigo: string | null;
    esc_inep: string | null;
    telefone: string | null;
    email: string | null;
    gestores: Record<string, Gestor[]>;
    total_gestores: number;
}

interface Filtros {
    esc_id: number | null;
    esc_nome: string | null;
    tipos: string[];
    rotulos: string[];
    formato: 'consolidado' | 'detalhado';
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
    resumo: { escolas: number; gestores: number; sem_diretor: number | null; sem_nenhum: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Equipe Gestora', href: '/relatorios/equipe-gestora' },
];

const detalhado = computed(() => props.filtros.formato === 'detalhado');
const escopo = computed(() => props.filtros.esc_nome ?? 'Todas as escolas da rede');

const grupos = computed(() =>
    props.filtros.tipos.map((tipo, i) => ({ tipo, rotulo: props.filtros.rotulos[i] })),
);

/** Modo detalhado: achata em uma linha por gestor, preservando a ordem das funções. */
const linhasDetalhe = computed(() =>
    props.linhas.flatMap((l) =>
        grupos.value.flatMap((g) =>
            (l.gestores[g.tipo] ?? []).map((gestor) => ({ escola: l, funcao: g.rotulo, gestor })),
        ),
    ),
);

const semGestor = (l: Linha, tipo: string) => !(l.gestores[tipo] ?? []).length;

/** Larguras em % — o nº de colunas varia com os tipos selecionados. */
const larguras = computed(() => {
    if (detalhado.value) return ['16%', '6%', '12%', '15%', '18%', '10%', '15%', '8%'];
    const n = Math.max(grupos.value.length, 1);
    const restante = 100 - 20 - 7 - 11 - 15;
    return ['20%', '7%', ...Array(n).fill(`${(restante / n).toFixed(2)}%`), '11%', '15%'];
});

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});

const isActiveTab = useIsActiveTab();
const imprimir = () => window.print();

const queryFiltros = () => {
    const p = new URLSearchParams();
    if (props.filtros.esc_id) p.set('esc_id', String(props.filtros.esc_id));
    props.filtros.tipos.forEach((t) => p.append('tipos[]', t));
    p.set('formato', props.filtros.formato);
    return p;
};

const exportarCsv = () => {
    const sep = ';';
    const aspas = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;

    let headers: string[];
    let corpo: string[][];

    if (detalhado.value) {
        headers = ['Escola', 'Código', 'Função', 'Cargo', 'Nome', 'Telefone', 'E-mail', 'Vínculo', 'Desde', 'Telefone da Escola', 'E-mail da Escola'];
        corpo = linhasDetalhe.value.map(({ escola, funcao, gestor }) => [
            escola.esc_nome, escola.esc_codigo ?? '', funcao,
            gestor.cargo ?? '', gestor.nome ?? '', gestor.telefone ?? '', gestor.email ?? '',
            gestor.vinculo ?? '', gestor.desde ?? '',
            escola.telefone ?? '', escola.email ?? '',
        ]);
    } else {
        headers = ['Escola', 'Código', ...grupos.value.map((g) => g.rotulo), 'Telefone', 'E-mail'];
        corpo = props.linhas.map((l) => [
            l.esc_nome, l.esc_codigo ?? '',
            ...grupos.value.map((g) => (l.gestores[g.tipo] ?? []).map((x) => x.nome).join(' / ')),
            l.telefone ?? '', l.email ?? '',
        ]);
    }

    const csv = '﻿' + [headers.join(sep), ...corpo.map((r) => r.map(aspas).join(sep))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `equipe-gestora-${props.filtros.formato}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};

const exportarExcel = () => window.open(`/relatorios/equipe-gestora/xlsx?${queryFiltros().toString()}`, '_blank');
</script>

<template>
    <Head title="Resultado — Equipe Gestora" />

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
            <h1>Relatório de Equipe Gestora das Escolas</h1>
            <div class="subtitulo-impressao">
                <span><b>Escola:</b> {{ escopo }}</span>
                <span><b>Escolas:</b> {{ resumo.escolas }}</span>
                <span><b>Gestores:</b> {{ resumo.gestores }}</span>
            </div>
        </div>

        <table class="tabela-impressao">
            <colgroup>
                <col v-for="(w, i) in larguras" :key="`c-${i}`" :style="{ width: w }" />
            </colgroup>

            <thead v-if="detalhado">
                <tr>
                    <th>Escola</th>
                    <th>Código</th>
                    <th>Função</th>
                    <th>Cargo</th>
                    <th>Nome</th>
                    <th>Telefone</th>
                    <th>E-mail</th>
                    <th>Vínculo</th>
                </tr>
            </thead>
            <thead v-else>
                <tr>
                    <th>Escola</th>
                    <th>Código</th>
                    <th v-for="g in grupos" :key="`ph-${g.tipo}`">{{ g.rotulo }}</th>
                    <th>Telefone</th>
                    <th>E-mail</th>
                </tr>
            </thead>

            <tbody v-if="detalhado">
                <tr v-for="(d, i) in linhasDetalhe" :key="`pd-${i}`">
                    <td class="celula-nome">{{ d.escola.esc_nome }}</td>
                    <td>{{ d.escola.esc_codigo ?? '—' }}</td>
                    <td>{{ d.funcao }}</td>
                    <td>{{ d.gestor.cargo ?? '—' }}</td>
                    <td class="celula-nome">{{ d.gestor.nome ?? '—' }}</td>
                    <td>{{ d.gestor.telefone ?? '—' }}</td>
                    <td class="celula-email">{{ d.gestor.email ?? '—' }}</td>
                    <td>{{ d.gestor.vinculo ?? '—' }}</td>
                </tr>
            </tbody>
            <tbody v-else>
                <tr v-for="l in linhas" :key="`pc-${l.esc_id}`">
                    <td class="celula-nome">{{ l.esc_nome }}</td>
                    <td>{{ l.esc_codigo ?? '—' }}</td>
                    <td v-for="g in grupos" :key="`pc-${l.esc_id}-${g.tipo}`">
                        <template v-if="semGestor(l, g.tipo)">—</template>
                        <div v-for="x in l.gestores[g.tipo]" :key="x.fun_id" class="gestor-item">{{ x.nome }}</div>
                    </td>
                    <td>{{ l.telefone ?? '—' }}</td>
                    <td class="celula-email">{{ l.email ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="resumo-impressao">
            <span><b>Escolas:</b> {{ resumo.escolas }}</span>
            <span><b>Gestores:</b> {{ resumo.gestores }}</span>
            <span v-if="resumo.sem_diretor !== null"><b>Sem diretor(a):</b> {{ resumo.sem_diretor }}</span>
            <span><b>Sem nenhum gestor:</b> {{ resumo.sem_nenhum }}</span>
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
                <h1 class="text-xl font-semibold">Resultado: Equipe Gestora</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/equipe-gestora">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button variant="outline" @click="exportarCsv">CSV</Button>
                    <Button variant="outline" @click="exportarExcel">Excel</Button>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm grid gap-1 sm:grid-cols-2">
                <div><strong>Escola:</strong> {{ escopo }}</div>
                <div><strong>Funções:</strong> {{ filtros.rotulos.join(', ') }}</div>
                <div><strong>Escolas listadas:</strong> {{ resumo.escolas }}</div>
                <div><strong>Gestores encontrados:</strong> {{ resumo.gestores }}</div>
            </div>

            <div
                v-if="resumo.sem_diretor || resumo.sem_nenhum"
                class="rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm mb-4 text-sm text-amber-900 print:hidden dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <strong>Lacunas na rede:</strong>
                <span v-if="resumo.sem_diretor"> {{ resumo.sem_diretor }} escola(s) sem diretor(a) lotado(a).</span>
                <span v-if="resumo.sem_nenhum"> {{ resumo.sem_nenhum }} escola(s) sem nenhum gestor das funções filtradas.</span>
            </div>

            <div v-if="!linhas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhuma escola encontrada.
            </div>

            <div v-else-if="detalhado" class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div v-if="!linhasDetalhe.length" class="py-12 text-center text-muted-foreground">
                    Nenhum gestor lotado nas escolas e funções selecionadas.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm" style="min-width: 1180px">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Escola</th>
                                <th class="px-3 py-2 text-left">Código</th>
                                <th class="px-3 py-2 text-left">Função</th>
                                <th class="px-3 py-2 text-left">Cargo</th>
                                <th class="px-3 py-2 text-left">Nome</th>
                                <th class="px-3 py-2 text-left">Telefone</th>
                                <th class="px-3 py-2 text-left">E-mail</th>
                                <th class="px-3 py-2 text-left">Vínculo</th>
                                <th class="px-3 py-2 text-center">Desde</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(d, i) in linhasDetalhe" :key="`d-${i}`">
                                <td class="px-3 py-1.5 text-muted-foreground tabular-nums">{{ i + 1 }}</td>
                                <td class="px-3 py-1.5 break-words">{{ d.escola.esc_nome }}</td>
                                <td class="px-3 py-1.5 tabular-nums">{{ d.escola.esc_codigo ?? '—' }}</td>
                                <td class="px-3 py-1.5">{{ d.funcao }}</td>
                                <td class="px-3 py-1.5 break-words">{{ d.gestor.cargo ?? '—' }}</td>
                                <td class="px-3 py-1.5 font-medium break-words">{{ d.gestor.nome ?? '—' }}</td>
                                <td class="px-3 py-1.5 tabular-nums">{{ d.gestor.telefone ?? '—' }}</td>
                                <td class="px-3 py-1.5 break-all">{{ d.gestor.email ?? '—' }}</td>
                                <td class="px-3 py-1.5 break-words">{{ d.gestor.vinculo ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-center tabular-nums">{{ d.gestor.desde ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="min-width: 1180px">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Escola</th>
                                <th class="px-3 py-2 text-left">Código</th>
                                <th v-for="g in grupos" :key="`h-${g.tipo}`" class="px-3 py-2 text-left">{{ g.rotulo }}</th>
                                <th class="px-3 py-2 text-left">Telefone</th>
                                <th class="px-3 py-2 text-left">E-mail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(l, i) in linhas" :key="l.esc_id">
                                <td class="px-3 py-1.5 align-top text-muted-foreground tabular-nums">{{ i + 1 }}</td>
                                <td class="px-3 py-1.5 align-top font-medium break-words">{{ l.esc_nome }}</td>
                                <td class="px-3 py-1.5 align-top tabular-nums">{{ l.esc_codigo ?? '—' }}</td>
                                <td v-for="g in grupos" :key="`c-${l.esc_id}-${g.tipo}`" class="px-3 py-1.5 align-top break-words">
                                    <span v-if="semGestor(l, g.tipo)" class="text-muted-foreground">—</span>
                                    <div v-for="x in l.gestores[g.tipo]" :key="x.fun_id" class="mb-1 last:mb-0">
                                        <div>{{ x.nome }}</div>
                                        <div v-if="x.cargo && x.cargo !== g.rotulo" class="text-xs text-muted-foreground">{{ x.cargo }}</div>
                                        <div v-if="x.telefone || x.email" class="text-xs text-muted-foreground break-all">
                                            {{ [x.telefone, x.email].filter(Boolean).join(' · ') }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-1.5 align-top tabular-nums">{{ l.telefone ?? '—' }}</td>
                                <td class="px-3 py-1.5 align-top break-all">{{ l.email ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t bg-muted/30 px-4 py-2 text-sm font-medium">
                    Total de escolas: {{ resumo.escolas }} · Gestores: {{ resumo.gestores }}
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
        /* Larguras vêm do <colgroup> em %, pois o nº de colunas varia com o filtro. */
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
        /* Rótulos como "Coordenador(a) Pedagógico(a)" precisam quebrar. */
        overflow-wrap: break-word;
    }
    .tabela-impressao td {
        padding: 3px 4px;
        border: 1px solid #000;
        vertical-align: top;
        overflow-wrap: break-word;
        word-break: break-word;
    }
    .tabela-impressao .celula-nome { font-weight: 600; }
    .tabela-impressao .celula-email { word-break: break-all; }
    .tabela-impressao .gestor-item + .gestor-item { margin-top: 2px; }

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
