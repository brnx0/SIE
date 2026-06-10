<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface Linha {
    escola: string | null;
    matricula: number | null;
    nome: string | null;
    dt_nascimento: string | null;
    ano_serie: string | null;
    turma: string | null;
    turno: string | null;
    pcd: string;
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
    { title: 'Alunos com Deficiência', href: '/relatorios/alunos-deficiencia' },
];

const imprimir = () => window.print();

const grupos = computed(() => {
    const map = new Map<string, Linha[]>();
    for (const l of props.linhas) {
        const k = l.escola ?? '—';
        if (!map.has(k)) map.set(k, []);
        map.get(k)!.push(l);
    }
    return [...map.entries()];
});

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR');
});
</script>

<template>
    <Head title="Alunos com Deficiência" />

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
            <h1>Alunos com Deficiência Matriculados</h1>
        </div>

        <div class="resumo-filtros">
            <div><b>Ano:</b> {{ filtros.anl_ano }}</div>
            <div><b>Escola:</b> {{ filtros.esc_nome }}</div>
            <div><b>Total:</b> {{ total }} aluno(s)</div>
        </div>

        <div v-if="!linhas.length" class="vazio">Nenhum aluno encontrado.</div>

        <section v-for="[esc, alunos] in grupos" :key="`p-${esc}`" class="grupo-escola">
            <div class="grupo-escola-header">
                <span>{{ esc }}</span>
                <span>{{ alunos.length }} aluno(s)</span>
            </div>
            <table class="tabela-impressao">
                <thead>
                    <tr>
                        <th>Matrícula</th>
                        <th>Nome do Aluno</th>
                        <th>Data Nascimento</th>
                        <th>Ano Escolar</th>
                        <th>Turma</th>
                        <th>Turno</th>
                        <th>PCD</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(a, i) in alunos" :key="`p-${esc}-${i}`">
                        <td class="centro">{{ a.matricula ?? '—' }}</td>
                        <td>{{ a.nome ?? '—' }}</td>
                        <td class="centro">{{ a.dt_nascimento ?? '—' }}</td>
                        <td>{{ a.ano_serie ?? '—' }}</td>
                        <td class="centro">{{ a.turma ?? '—' }}</td>
                        <td>{{ a.turno ?? '—' }}</td>
                        <td class="centro">{{ a.pcd }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <footer class="rodape-impressao">
            <span>Impresso: {{ dataEmissao }}</span>
        </footer>
    </div>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Alunos com Deficiência</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/alunos-deficiencia">
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
                <div><strong>Total alunos:</strong> {{ total }}</div>
            </div>

            <div v-if="!total" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum aluno encontrado.
            </div>

            <div v-for="[esc, alunos] in grupos" :key="esc" class="mb-6 rounded-xl border bg-card shadow-sm overflow-hidden">
                <div class="border-b bg-indigo-50 px-4 py-2 font-semibold text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-200">
                    {{ esc }} <span class="text-xs font-normal text-muted-foreground">({{ alunos.length }} aluno{{ alunos.length !== 1 ? 's' : '' }})</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-2 py-2 text-center">Matrícula</th>
                                <th class="px-2 py-2 text-left">Nome do Aluno</th>
                                <th class="px-2 py-2 text-center">Data Nascimento</th>
                                <th class="px-2 py-2 text-left">Ano Escolar</th>
                                <th class="px-2 py-2 text-center">Turma</th>
                                <th class="px-2 py-2 text-left">Turno</th>
                                <th class="px-2 py-2 text-center">PCD</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(a, i) in alunos" :key="`s-${esc}-${i}`">
                                <td class="px-2 py-1.5 text-center tabular-nums">{{ a.matricula ?? '—' }}</td>
                                <td class="px-2 py-1.5 font-medium">{{ a.nome ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-center">{{ a.dt_nascimento ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ a.ano_serie ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-center">{{ a.turma ?? '—' }}</td>
                                <td class="px-2 py-1.5">{{ a.turno ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-center">{{ a.pcd }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 portrait; margin: 8mm 10mm; }
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
        margin: 8px 0 6px;
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

    .vazio {
        text-align: center;
        padding: 40px;
        font-style: italic;
    }

    .grupo-escola { margin: 10px 0 6px; page-break-inside: avoid; }
    .grupo-escola-header {
        border: 1px solid #000;
        border-bottom: none;
        padding: 3px 8px;
        font-size: 10pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        justify-content: space-between;
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
        font-size: 8pt;
        letter-spacing: 0.3px;
    }
    .tabela-impressao td {
        padding: 3px 5px;
        border: 1px solid #000;
        vertical-align: top;
    }
    .tabela-impressao .centro { text-align: center; }

    .rodape-impressao {
        margin-top: 16px;
        padding-top: 4px;
        border-top: 1px solid #000;
        font-size: 8.5pt;
        font-style: italic;
    }

    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>
