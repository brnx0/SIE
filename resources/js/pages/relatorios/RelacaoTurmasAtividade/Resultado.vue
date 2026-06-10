<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';

interface Linha {
    cd_inep: string | null;
    escola: string | null;
    turma: string | null;
    turno: string | null;
    atividades: string | null;
    dias: string | null;
    qtd_alunos: number;
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
    total_turmas: number;
    total_alunos: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Relação de Turmas de Atividade', href: '/relatorios/relacao-turmas-atividade' },
];

const turnoLabel = (t: string | null) => {
    if (!t) return '—';
    const map: Record<string, string> = { MATUTINO: 'Matutino', VESPERTINO: 'Vespertino', NOTURNO: 'Noturno', INTEGRAL: 'Integral' };
    return map[t.toUpperCase()] ?? t;
};

const imprimir = () => window.print();
</script>

<template>
    <Head title="Relação de Turmas de Atividade" />

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

            <h1 class="titulo">RELAÇÃO DE TURMAS DE ATIVIDADE — {{ anoLetivo.anl_ano }}</h1>
            <p v-if="escola" class="subtitulo">Escola: {{ escola.esc_nome }}</p>

            <table class="tab">
                <thead>
                    <tr>
                        <th>INEP</th>
                        <th>Escola</th>
                        <th>Turma</th>
                        <th>Turno</th>
                        <th>Atividades</th>
                        <th>Dias</th>
                        <th>Qtd. Alunos</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(l, i) in linhas" :key="i">
                        <td>{{ l.cd_inep ?? '—' }}</td>
                        <td>{{ l.escola ?? '—' }}</td>
                        <td>{{ l.turma ?? '—' }}</td>
                        <td>{{ turnoLabel(l.turno) }}</td>
                        <td>{{ l.atividades ?? '—' }}</td>
                        <td>{{ l.dias ?? '—' }}</td>
                        <td class="num">{{ l.qtd_alunos }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6"><b>Total turmas: {{ total_turmas }}</b></td>
                        <td class="num"><b>{{ total_alunos }}</b></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Relação de Turmas de Atividade — {{ anoLetivo.anl_ano }}</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/relacao-turmas-atividade">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!total_turmas" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-3 mb-4">
                <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                    <div class="text-xs text-muted-foreground">Total turmas</div>
                    <div class="text-2xl font-bold">{{ total_turmas }}</div>
                </div>
                <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                    <div class="text-xs text-muted-foreground">Total alunos</div>
                    <div class="text-2xl font-bold text-indigo-600">{{ total_alunos }}</div>
                </div>
                <div v-if="escola" class="rounded-xl border bg-card p-3 text-center shadow-sm">
                    <div class="text-xs text-muted-foreground">Escola</div>
                    <div class="text-sm font-medium truncate">{{ escola.esc_nome }}</div>
                </div>
            </div>

            <div v-if="!total_turmas" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhuma turma de atividade encontrada.
            </div>

            <div v-else class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-teal-600 text-white">
                        <tr>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">INEP</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Escola</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Turma</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Turno</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Atividades</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Dias</th>
                            <th class="px-3 py-2.5 text-center font-semibold whitespace-nowrap">Qtd. Alunos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(l, i) in linhas" :key="i" :class="i % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                            <td class="px-3 py-2 text-xs tabular-nums">{{ l.cd_inep ?? '—' }}</td>
                            <td class="px-3 py-2 font-medium">{{ l.escola ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ l.turma ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ turnoLabel(l.turno) }}</td>
                            <td class="px-3 py-2 text-xs">{{ l.atividades ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ l.dias ?? '—' }}</td>
                            <td class="px-3 py-2 text-center font-semibold tabular-nums">{{ l.qtd_alunos }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-muted/40 font-semibold">
                        <tr>
                            <td colspan="6" class="px-3 py-2 text-right">Total</td>
                            <td class="px-3 py-2 text-center tabular-nums">{{ total_alunos }}</td>
                        </tr>
                    </tfoot>
                </table>
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
        font-size: 8.5pt;
        color: #000;
    }
    .cab { display: grid; grid-template-columns: 70px 1fr 70px; align-items: center; gap: 8px; border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
    .cab img { max-width: 70px; max-height: 70px; object-fit: contain; }
    .cab-textos { text-align: center; }
    .cab-textos div { font-size: 8pt; }
    .entidade { font-weight: 700; font-size: 9pt; }
    .titulo { text-align: center; font-size: 11pt; font-weight: 700; margin: 4px 0 2px; }
    .subtitulo { text-align: center; font-size: 9pt; margin-bottom: 6px; }
    .tab { width: 100%; border-collapse: collapse; }
    .tab th, .tab td { border: 1px solid #444; padding: 3px 5px; font-size: 8pt; }
    .tab th { background: #e5e7eb; font-weight: 700; text-transform: uppercase; }
    .tab .num { text-align: center; }
    .tab tfoot td { background: #f3f4f6; font-weight: 700; }
}
</style>
