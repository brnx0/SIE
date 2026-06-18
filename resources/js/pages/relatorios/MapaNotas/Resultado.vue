<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface Cell { texto: string; tipo: string | null }
interface Aluno { aln_id: number; aln_nome: string; aln_nr_matricula: string | null; celulas: Cell[] }
interface Parametros { nome_entidade: string; logomarca_url: string | null; brasao_url: string | null }

defineProps<{
    parametros: Parametros | null;
    filtros: { anl_ano: number; esc_nome: string; turma: string; periodo: string | null };
    disciplinas: string[];
    alunos: Aluno[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mapa de Notas', href: '/relatorios/mapa-notas' }];

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});
const imprimir = () => window.print();
</script>

<template>
    <Head title="Resultado — Mapa de Notas" />

    <div id="print-area">
        <header class="cab">
            <div class="logo"><img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="" /></div>
            <div class="cab-textos">
                <div class="cab-entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
                <div class="cab-tit">Mapa de Notas</div>
            </div>
            <div class="logo"><img v-if="parametros?.brasao_url" :src="parametros.brasao_url" alt="" /></div>
        </header>
        <div class="ident">
            <span><b>Ano:</b> {{ filtros.anl_ano }}</span>
            <span><b>Escola:</b> {{ filtros.esc_nome }}</span>
            <span><b>Turma:</b> {{ filtros.turma }}</span>
            <span><b>Período:</b> {{ filtros.periodo }}</span>
        </div>
        <table class="tbl">
            <thead>
                <tr>
                    <th class="esq">#</th>
                    <th class="esq">Aluno</th>
                    <th v-for="(d, i) in disciplinas" :key="i">{{ d }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(a, i) in alunos" :key="a.aln_id">
                    <td>{{ i + 1 }}</td>
                    <td class="esq">{{ a.aln_nome }}</td>
                    <td v-for="(c, j) in a.celulas" :key="j">{{ c.texto }}</td>
                </tr>
            </tbody>
        </table>
        <footer class="rodape"><span>SIE Matrícula</span><span>Emitido em {{ dataEmissao }}</span></footer>
    </div>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6 print:py-2">
            <div class="mb-4 flex items-center justify-between print:hidden">
                <h1 class="text-xl font-semibold">Resultado: Mapa de Notas</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/mapa-notas"><Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button></Link>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 rounded-xl border bg-card p-4 text-sm shadow-sm">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }} · <strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Turma:</strong> {{ filtros.turma }} · <strong>Período:</strong> {{ filtros.periodo }}</div>
            </div>

            <div v-if="!alunos.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhum aluno ativo nesta turma.</div>

            <div v-else class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 text-left">#</th>
                            <th class="sticky left-0 bg-muted/40 px-3 py-2 text-left">Aluno</th>
                            <th v-for="(d, i) in disciplinas" :key="i" class="px-2 py-2 text-center">{{ d }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(a, i) in alunos" :key="a.aln_id" class="hover:bg-muted/20">
                            <td class="px-3 py-1.5 text-muted-foreground">{{ i + 1 }}</td>
                            <td class="sticky left-0 bg-card px-3 py-1.5 font-medium">{{ a.aln_nome }}</td>
                            <td v-for="(c, j) in a.celulas" :key="j" class="px-2 py-1.5 text-center tabular-nums">{{ c.texto }}</td>
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
    @page { size: A4 landscape; margin: 8mm 10mm; }
    body * { visibility: hidden !important; }
    #print-area, #print-area * { visibility: visible !important; }
    #print-area { display: block !important; position: absolute; left: 0; top: 0; width: 100%; font-family: 'Times New Roman', Times, serif; color: #000; }
    .cab { display: grid; grid-template-columns: 80px 1fr 80px; align-items: center; border-bottom: 2px solid #000; padding-bottom: 4px; }
    .logo img { max-width: 75px; max-height: 75px; object-fit: contain; }
    .cab-textos { text-align: center; }
    .cab-entidade { font-size: 12pt; font-weight: 700; text-transform: uppercase; }
    .cab-tit { font-size: 11pt; font-weight: 700; }
    .ident { display: flex; flex-wrap: wrap; gap: 16px; font-size: 9pt; margin: 8px 0; }
    .tbl { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    .tbl th, .tbl td { border: 1px solid #000; padding: 3px 4px; text-align: center; }
    .tbl th.esq, .tbl td.esq { text-align: left; }
    .tbl thead th { background: #eee; }
    .rodape { margin-top: 14px; padding-top: 6px; border-top: 1px solid #000; font-size: 8pt; display: flex; justify-content: space-between; font-style: italic; }
}
</style>
