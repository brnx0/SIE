<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer, AlertTriangle, XCircle, CheckCircle2, ExternalLink } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useIsActiveTab } from '@/composables/useIsActiveTab';

type Severidade = 'erro' | 'alerta';
type Tipo = 'escola' | 'turma' | 'aluno' | 'funcionario';

interface Critica {
    registro: string;
    tipo: Tipo;
    entidade: string;
    entidade_id: number;
    campo: string;
    mensagem: string;
    severidade: Severidade;
}

const props = defineProps<{
    filtros: { anl_id: number; anl_ano: number; esc_id: number; esc_nome: string };
    criticas: Critica[];
    resumo: Record<string, { erro: number; alerta: number }>;
    pendencias: string[];
    total: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Críticas do Censo', href: '/relatorios/censo-criticas' },
];

const REGISTRO_LABEL: Record<string, string> = {
    '00': 'Reg 00 — Identificação da Escola',
    '10': 'Reg 10 — Caracterização e Infraestrutura',
    '20': 'Reg 20 — Turmas',
    '30': 'Reg 30 — Pessoas (aluno/profissional)',
    '40': 'Reg 40 — Profissional em sala / Gestor',
    '60': 'Reg 60 — Matrícula',
};

const filtro = ref<'todos' | 'erro' | 'alerta'>('todos');

const totalErros = computed(() => props.criticas.filter((c) => c.severidade === 'erro').length);
const totalAlertas = computed(() => props.criticas.filter((c) => c.severidade === 'alerta').length);

const criticasFiltradas = computed(() =>
    filtro.value === 'todos' ? props.criticas : props.criticas.filter((c) => c.severidade === filtro.value),
);

interface EntidadeGrupo {
    entidade: string;
    tipo: Tipo;
    entidade_id: number;
    itens: Critica[];
    erros: number;
    alertas: number;
}

// Agrupa por registro → entidade (cadastro), para que cada funcionário/aluno mostre
// de uma vez TODOS os campos que precisa atualizar (não um por vez).
const grupos = computed(() => {
    const porReg = new Map<string, Map<string, EntidadeGrupo>>();
    for (const c of criticasFiltradas.value) {
        if (!porReg.has(c.registro)) porReg.set(c.registro, new Map());
        const ents = porReg.get(c.registro)!;
        const key = `${c.tipo}:${c.entidade_id}`;
        if (!ents.has(key)) {
            ents.set(key, { entidade: c.entidade, tipo: c.tipo, entidade_id: c.entidade_id, itens: [], erros: 0, alertas: 0 });
        }
        const g = ents.get(key)!;
        g.itens.push(c);
        if (c.severidade === 'erro') g.erros++; else g.alertas++;
    }
    return [...porReg.entries()]
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([registro, ents]) => ({
            registro,
            label: REGISTRO_LABEL[registro] ?? `Reg ${registro}`,
            // Mais erros primeiro; empata por alertas; depois ordem alfabética.
            entidades: [...ents.values()].sort((x, y) =>
                y.erros - x.erros || y.alertas - x.alertas || x.entidade.localeCompare(y.entidade),
            ),
        }));
});

const linkEdicao = (e: { tipo: Tipo; entidade_id: number }): string | null => {
    if (e.tipo === 'aluno') return `/alunos/${e.entidade_id}/edit`;
    if (e.tipo === 'funcionario') return `/funcionarios/${e.entidade_id}/edit`;
    if (e.tipo === 'escola') return `/escolas/${e.entidade_id}/edit`;
    return null;
};

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});

const isActiveTab = useIsActiveTab();
const imprimir = () => window.print();

const exportarCsv = () => {
    const headers = ['Registro', 'Severidade', 'Tipo', 'Entidade', 'Campo', 'Mensagem'];
    const sep = ';';
    const linhas = props.criticas.map((c) => [
        c.registro, c.severidade, c.tipo, c.entidade, c.campo, c.mensagem,
    ].map((v) => `"${String(v ?? '').replace(/"/g, '""')}"`).join(sep));
    const csv = '﻿' + [headers.join(sep), ...linhas].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `criticas-censo-${props.filtros.anl_ano}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <Head title="Resultado — Críticas do Censo" />

    <!-- Bloco exclusivo de impressão (teleportado p/ body; shell de abas esconde o resto) -->
    <Teleport to="body" :disabled="!isActiveTab">
    <div id="print-area">
        <div class="titulo-impressao">
            <h1>Relatório de Críticas do Censo</h1>
            <div class="subtitulo-impressao">
                <span><b>Ano Letivo:</b> {{ filtros.anl_ano }}</span>
                <span><b>Escola:</b> {{ filtros.esc_nome }}</span>
                <span><b>Erros:</b> {{ totalErros }}</span>
                <span><b>Alertas:</b> {{ totalAlertas }}</span>
            </div>
        </div>

        <p v-if="!total" class="vazio-impressao">Nenhuma crítica encontrada para esta escola.</p>

        <template v-else>
            <div v-for="g in grupos" :key="`p-${g.registro}`" class="grupo-impressao">
                <h2>{{ g.label }}</h2>
                <div v-for="e in g.entidades" :key="`p-${e.tipo}-${e.entidade_id}`" class="entidade-impressao">
                    <div class="ent-nome">{{ e.entidade }} <span class="ent-tipo">({{ e.tipo }})</span></div>
                    <ul>
                        <li v-for="(c, i) in e.itens" :key="`p-${c.campo}-${i}`">
                            <b>[{{ c.severidade === 'erro' ? 'ERRO' : 'ALERTA' }}]</b> {{ c.campo }} — {{ c.mensagem }}
                        </li>
                    </ul>
                </div>
            </div>

            <div v-if="pendencias.length" class="pendencias-impressao">
                <h2>Ainda não validado (campos sem coluna no sistema)</h2>
                <ul>
                    <li v-for="(p, i) in pendencias" :key="`pp-${i}`">{{ p }}</li>
                </ul>
            </div>
        </template>

        <div class="rodape-impressao">Emitido em {{ dataEmissao }}</div>
    </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6 print:py-2">
            <div class="flex items-center justify-between mb-4 print:hidden">
                <h1 class="text-xl font-semibold">Críticas do Censo</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/censo-criticas">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button variant="outline" @click="exportarCsv">CSV</Button>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <!-- Cabeçalho -->
            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm flex flex-wrap gap-x-8 gap-y-1">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }}</div>
                <div><strong>Escola:</strong> {{ filtros.esc_nome }}</div>
            </div>

            <!-- Placar -->
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="rounded-xl border bg-card p-4 shadow-sm flex items-center gap-3">
                    <XCircle class="size-7 text-rose-600" />
                    <div>
                        <div class="text-2xl font-bold tabular-nums">{{ totalErros }}</div>
                        <div class="text-xs text-muted-foreground">Erros (barram o envio)</div>
                    </div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm flex items-center gap-3">
                    <AlertTriangle class="size-7 text-amber-500" />
                    <div>
                        <div class="text-2xl font-bold tabular-nums">{{ totalAlertas }}</div>
                        <div class="text-xs text-muted-foreground">Alertas (revisar)</div>
                    </div>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm flex items-center gap-3">
                    <CheckCircle2 class="size-7 text-emerald-600" />
                    <div>
                        <div class="text-2xl font-bold tabular-nums">{{ total }}</div>
                        <div class="text-xs text-muted-foreground">Total de apontamentos</div>
                    </div>
                </div>
            </div>

            <!-- Filtro de severidade -->
            <div class="flex gap-2 mb-4 print:hidden">
                <button
                    v-for="opt in (['todos', 'erro', 'alerta'] as const)"
                    :key="opt"
                    class="rounded-full border px-3 py-1 text-sm capitalize transition"
                    :class="filtro === opt ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-background hover:bg-muted'"
                    @click="filtro = opt"
                >
                    {{ opt === 'todos' ? 'Todos' : opt === 'erro' ? 'Erros' : 'Alertas' }}
                </button>
            </div>

            <!-- Tudo certo -->
            <div v-if="!total" class="rounded-xl border bg-card py-12 text-center">
                <CheckCircle2 class="mx-auto size-10 text-emerald-600 mb-2" />
                <p class="font-medium">Nenhuma crítica encontrada para esta escola.</p>
                <p class="text-sm text-muted-foreground">Os campos validados estão preenchidos.</p>
            </div>

            <!-- Grupos por registro → cadastro (cada entidade mostra TODOS os campos a atualizar) -->
            <div v-else class="space-y-5">
                <div v-for="g in grupos" :key="g.registro" class="rounded-xl border bg-card shadow-sm overflow-hidden">
                    <div class="border-b bg-muted/40 px-4 py-2 font-semibold text-sm flex items-center justify-between">
                        <span>{{ g.label }}</span>
                        <span class="text-xs font-normal text-muted-foreground">{{ g.entidades.length }} cadastro(s)</span>
                    </div>
                    <div class="divide-y">
                        <div v-for="e in g.entidades" :key="`${e.tipo}-${e.entidade_id}`" class="px-4 py-3">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2 min-w-0 flex-wrap">
                                    <span class="font-medium break-words">{{ e.entidade }}</span>
                                    <span class="text-xs uppercase tracking-wide text-muted-foreground">{{ e.tipo }}</span>
                                    <Link
                                        v-if="linkEdicao(e)"
                                        :href="linkEdicao(e)!"
                                        class="text-indigo-600 hover:text-indigo-800 print:hidden inline-flex items-center gap-1 text-xs"
                                        title="Abrir cadastro"
                                    >
                                        <ExternalLink class="size-3.5" /> abrir
                                    </Link>
                                </div>
                                <div class="flex items-center gap-2 shrink-0 text-xs">
                                    <span v-if="e.erros" class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                        <XCircle class="size-3" /> {{ e.erros }}
                                    </span>
                                    <span v-if="e.alertas" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                        <AlertTriangle class="size-3" /> {{ e.alertas }}
                                    </span>
                                </div>
                            </div>
                            <ul class="space-y-1">
                                <li v-for="(c, i) in e.itens" :key="`${c.campo}-${i}`" class="flex items-start gap-2 text-sm">
                                    <XCircle v-if="c.severidade === 'erro'" class="mt-0.5 size-4 shrink-0 text-rose-600" />
                                    <AlertTriangle v-else class="mt-0.5 size-4 shrink-0 text-amber-500" />
                                    <span class="shrink-0 font-medium">{{ c.campo }}:</span>
                                    <span class="break-words text-muted-foreground">{{ c.mensagem }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pendências não validadas -->
            <div v-if="pendencias.length" class="mt-6 rounded-xl border border-dashed bg-muted/20 p-4 print:hidden">
                <p class="text-sm font-semibold mb-2 flex items-center gap-2">
                    <AlertTriangle class="size-4 text-amber-500" /> Ainda não validado (campos sem coluna no sistema)
                </p>
                <ul class="list-disc pl-6 text-sm text-muted-foreground space-y-1">
                    <li v-for="(p, i) in pendencias" :key="i">{{ p }}</li>
                </ul>
            </div>

            <p class="mt-4 text-xs text-muted-foreground print:hidden">Emitido em {{ dataEmissao }}</p>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 portrait; margin: 10mm; }
    body > *:not(#print-area) { display: none !important; }
    #print-area {
        display: block !important;
        position: static;
        width: 100%;
        font-family: 'Times New Roman', Times, serif;
        color: #000;
        background: #fff;
        font-size: 10pt;
    }

    #print-area .titulo-impressao {
        text-align: center;
        margin-bottom: 10px;
        border-bottom: 2px solid #000;
        padding-bottom: 6px;
    }
    #print-area .titulo-impressao h1 {
        font-size: 13pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 0 0 4px;
    }
    #print-area .subtitulo-impressao {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 14px;
        font-size: 9pt;
    }

    #print-area .grupo-impressao { margin-top: 12px; }
    #print-area .grupo-impressao h2,
    #print-area .pendencias-impressao h2 {
        font-size: 10.5pt;
        font-weight: 700;
        text-transform: uppercase;
        border-bottom: 1px solid #000;
        padding-bottom: 2px;
        margin: 10px 0 6px;
    }
    #print-area .entidade-impressao {
        margin: 0 0 6px;
        padding-left: 4px;
        page-break-inside: avoid;
    }
    #print-area .ent-nome { font-weight: 700; }
    #print-area .ent-tipo { font-weight: 400; font-style: italic; text-transform: uppercase; font-size: 8pt; }
    #print-area .entidade-impressao ul,
    #print-area .pendencias-impressao ul { margin: 2px 0 0; padding-left: 18px; }
    #print-area .entidade-impressao li,
    #print-area .pendencias-impressao li { margin: 1px 0; }

    #print-area .vazio-impressao { margin-top: 20px; text-align: center; }
    #print-area .rodape-impressao {
        margin-top: 20px;
        padding-top: 6px;
        border-top: 1px solid #000;
        font-size: 8.5pt;
        font-style: italic;
    }
}
</style>
