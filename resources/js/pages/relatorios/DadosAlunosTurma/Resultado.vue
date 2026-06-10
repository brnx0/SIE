<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface Linha {
    escola: string | null;
    turma: string | null;
    aln_nome: string | null;
    mat_aluno: number | null;
    dt_nascimento: string | null;
    sexo: string | null;
    idade: number | null;
    etnia: string | null;
    cpf: string | null;
    cd_inep: string | null;
    nr_certidao: string | null;
    cartao_sus: string | null;
    filiacao_1: string | null;
    filiacao_2: string | null;
    endereco: string | null;
}
interface Parametros {
    nome_entidade: string;
    msg_cab_secretaria: string | null;
    msg_cab_estado: string | null;
    logomarca_url: string | null;
    brasao_url: string | null;
}

const props = defineProps<{
    parametros: Parametros | null;
    anoLetivo: { anl_id: number; anl_ano: number };
    escola: { esc_id: number; esc_nome: string } | null;
    linhas: Linha[];
    total: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dados de Alunos por Turma', href: '/relatorios/dados-alunos-turma' },
];

const sexoLabel = (s: string | null) => s === 'M' ? 'Masculino' : s === 'F' ? 'Feminino' : '—';

const imprimir = () => window.print();

const grupos = computed(() => {
    const map = new Map<string, { escola: string; turma: string; linhas: Linha[] }>();
    for (const l of props.linhas) {
        const key = `${l.escola ?? '—'} • ${l.turma ?? '—'}`;
        if (!map.has(key)) map.set(key, { escola: l.escola ?? '—', turma: l.turma ?? '—', linhas: [] });
        map.get(key)!.linhas.push(l);
    }
    return [...map.values()];
});
</script>

<template>
    <Head title="Dados de Alunos por Turma" />

    <!-- Impressão -->
    <Teleport to="body">
        <div id="print-area">
            <header class="cab">
                <div class="logo">
                    <img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" />
                </div>
                <div class="cab-textos">
                    <div v-if="parametros?.msg_cab_estado">{{ parametros.msg_cab_estado }}</div>
                    <div v-if="parametros?.msg_cab_secretaria">{{ parametros.msg_cab_secretaria }}</div>
                    <div class="entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
                </div>
                <div class="logo">
                    <img v-if="parametros?.brasao_url" :src="parametros.brasao_url" />
                </div>
            </header>

            <h1 class="titulo">DADOS DE ALUNOS POR TURMA — {{ anoLetivo.anl_ano }}</h1>
            <p v-if="escola" class="subtitulo">Escola: {{ escola.esc_nome }}</p>

            <div v-for="g in grupos" :key="g.escola + g.turma" class="grupo">
                <h2 class="grupo-titulo">{{ g.escola }} — {{ g.turma }} ({{ g.linhas.length }})</h2>
                <table class="tab">
                    <thead>
                        <tr>
                            <th>Mat.</th>
                            <th>Aluno</th>
                            <th>Nasc.</th>
                            <th>Sexo</th>
                            <th>Idade</th>
                            <th>Etnia</th>
                            <th>CPF</th>
                            <th>INEP</th>
                            <th>Certidão</th>
                            <th>SUS</th>
                            <th>Filiação</th>
                            <th>Endereço</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(l, i) in g.linhas" :key="i">
                            <td>{{ l.mat_aluno ?? '—' }}</td>
                            <td>{{ l.aln_nome ?? '—' }}</td>
                            <td>{{ l.dt_nascimento ?? '—' }}</td>
                            <td>{{ l.sexo ?? '—' }}</td>
                            <td>{{ l.idade ?? '—' }}</td>
                            <td>{{ l.etnia ?? '—' }}</td>
                            <td>{{ l.cpf ?? '—' }}</td>
                            <td>{{ l.cd_inep ?? '—' }}</td>
                            <td>{{ l.nr_certidao ?? '—' }}</td>
                            <td>{{ l.cartao_sus ?? '—' }}</td>
                            <td>
                                <div v-if="l.filiacao_1">{{ l.filiacao_1 }}</div>
                                <div v-if="l.filiacao_2">{{ l.filiacao_2 }}</div>
                                <span v-if="!l.filiacao_1 && !l.filiacao_2">—</span>
                            </td>
                            <td>{{ l.endereco ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Dados de Alunos por Turma — {{ anoLetivo.anl_ano }}</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/dados-alunos-turma">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!total" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm">
                <div v-if="escola"><strong>Escola:</strong> {{ escola.esc_nome }}</div>
                <div><strong>Total alunos:</strong> {{ total }}</div>
            </div>

            <div v-if="!total" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum aluno encontrado para os filtros.
            </div>

            <div v-else class="space-y-6">
                <div v-for="g in grupos" :key="g.escola + g.turma" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="flex items-center justify-between gap-2 border-b border-l-4 border-l-indigo-600 bg-indigo-50/60 px-4 py-2.5 dark:bg-indigo-900/20">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">
                                {{ g.escola }} — Turma {{ g.turma }}
                            </span>
                            <span class="rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-medium text-white">
                                {{ g.linhas.length }}
                            </span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-indigo-600 text-white">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Mat.</th>
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Aluno</th>
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Nasc.</th>
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Sexo</th>
                                    <th class="px-3 py-2 text-center font-semibold whitespace-nowrap">Idade</th>
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Etnia</th>
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">CPF</th>
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">INEP</th>
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Certidão</th>
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">SUS</th>
                                    <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Filiação</th>
                                    <th class="px-3 py-2 text-left font-semibold">Endereço</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(l, i) in g.linhas" :key="i" :class="i % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                                    <td class="px-3 py-2 text-xs tabular-nums">{{ l.mat_aluno ?? '—' }}</td>
                                    <td class="px-3 py-2 font-medium">{{ l.aln_nome ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs tabular-nums">{{ l.dt_nascimento ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs">{{ sexoLabel(l.sexo) }}</td>
                                    <td class="px-3 py-2 text-center text-xs tabular-nums">{{ l.idade ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs">{{ l.etnia ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs tabular-nums">{{ l.cpf ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs tabular-nums">{{ l.cd_inep ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs">{{ l.nr_certidao ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs tabular-nums">{{ l.cartao_sus ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs">
                                        <div v-if="l.filiacao_1" class="leading-tight">{{ l.filiacao_1 }}</div>
                                        <div v-if="l.filiacao_2" class="leading-tight text-muted-foreground">{{ l.filiacao_2 }}</div>
                                        <span v-if="!l.filiacao_1 && !l.filiacao_2">—</span>
                                    </td>
                                    <td class="px-3 py-2 text-xs">{{ l.endereco ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 landscape; margin: 8mm; }
    body > *:not(#print-area) { display: none !important; }
    #print-area {
        display: block !important;
        font-family: Arial, sans-serif;
        font-size: 7.5pt;
        color: #000;
    }
    .cab { display: grid; grid-template-columns: 70px 1fr 70px; align-items: center; gap: 8px; border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
    .cab img { max-width: 70px; max-height: 70px; object-fit: contain; }
    .cab-textos { text-align: center; }
    .cab-textos div { font-size: 8pt; }
    .entidade { font-weight: 700; font-size: 9pt; }
    .titulo { text-align: center; font-size: 11pt; font-weight: 700; margin: 4px 0 2px; }
    .subtitulo { text-align: center; font-size: 9pt; margin-bottom: 6px; }
    .grupo { page-break-inside: avoid; margin-bottom: 8px; }
    .grupo-titulo { font-size: 9pt; font-weight: 700; background: #e5e7eb; padding: 3px 6px; border: 1px solid #444; border-bottom: 0; }
    .tab { width: 100%; border-collapse: collapse; }
    .tab th, .tab td { border: 1px solid #444; padding: 2px 3px; font-size: 7pt; }
    .tab th { background: #f3f4f6; font-weight: 700; text-transform: uppercase; }
}
</style>
