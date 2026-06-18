<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface Cell { texto: string; tipo: string | null }
interface DisciplinaLinha { dis_nome: string; valores: Record<number, Cell>; final: Cell | null }
interface Aluno { aln_id: number; aln_nome: string; aln_nr_matricula: string | null; disciplinas: DisciplinaLinha[] }
interface Unidade { uni_id: number; label: string }
interface Parametros { nome_entidade: string; logomarca_url: string | null; brasao_url: string | null }

defineProps<{
    parametros: Parametros | null;
    filtros: { anl_ano: number; esc_nome: string; turma: string; periodo: string | null };
    unidades: Unidade[];
    consolidado: boolean;
    alunos: Aluno[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Boletim do Aluno', href: '/relatorios/boletim' }];

const dataEmissao = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});
const imprimir = () => window.print();
</script>

<template>
    <Head title="Resultado — Boletim" />

    <div id="print-area">
        <section v-for="aluno in alunos" :key="`pb-${aluno.aln_id}`" class="boletim-pagina">
            <header class="cab">
                <div class="logo"><img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="" /></div>
                <div class="cab-textos">
                    <div class="cab-entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
                    <div class="cab-tit">Boletim Escolar — {{ filtros.anl_ano }}</div>
                </div>
                <div class="logo"><img v-if="parametros?.brasao_url" :src="parametros.brasao_url" alt="" /></div>
            </header>
            <div class="ident">
                <span><b>Aluno:</b> {{ aluno.aln_nome }}</span>
                <span v-if="aluno.aln_nr_matricula"><b>Matrícula:</b> {{ aluno.aln_nr_matricula }}</span>
                <span><b>Escola:</b> {{ filtros.esc_nome }}</span>
                <span><b>Turma:</b> {{ filtros.turma }}</span>
                <span><b>Período:</b> {{ filtros.periodo }}</span>
            </div>
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="esq">Disciplina</th>
                        <th v-for="u in unidades" :key="u.uni_id">{{ u.label }}</th>
                        <th v-if="consolidado">Final</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(d, i) in aluno.disciplinas" :key="i">
                        <td class="esq">{{ d.dis_nome }}</td>
                        <td v-for="u in unidades" :key="u.uni_id">{{ d.valores[u.uni_id]?.texto ?? '—' }}</td>
                        <td v-if="consolidado" class="final">{{ d.final?.texto ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <footer class="rodape"><span>SIE Matrícula</span><span>Emitido em {{ dataEmissao }}</span></footer>
        </section>
    </div>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6 print:py-2">
            <div class="mb-4 flex items-center justify-between print:hidden">
                <h1 class="text-xl font-semibold">Resultado: Boletim</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/boletim"><Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button></Link>
                    <Button variant="outline" @click="imprimir"><Printer class="mr-1 size-4" /> Imprimir / PDF</Button>
                </div>
            </div>

            <div class="mb-4 rounded-xl border bg-card p-4 text-sm shadow-sm">
                <div><strong>Ano Letivo:</strong> {{ filtros.anl_ano }}</div>
                <div><strong>Escola:</strong> {{ filtros.esc_nome }}</div>
                <div><strong>Turma:</strong> {{ filtros.turma }}</div>
                <div><strong>Período:</strong> {{ filtros.periodo }}</div>
            </div>

            <div v-if="!alunos.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhum aluno ativo nesta turma.</div>

            <div class="flex flex-col gap-4">
                <div v-for="(aluno, i) in alunos" :key="aluno.aln_id" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="border-b bg-muted/40 px-4 py-2 text-sm font-semibold">
                        {{ i + 1 }}. {{ aluno.aln_nome }}
                        <span v-if="aluno.aln_nr_matricula" class="text-xs font-normal text-muted-foreground">· Mat. {{ aluno.aln_nr_matricula }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/30 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2 text-left">Disciplina</th>
                                    <th v-for="u in unidades" :key="u.uni_id" class="px-3 py-2 text-center">{{ u.label }}</th>
                                    <th v-if="consolidado" class="px-3 py-2 text-center">Final</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(d, j) in aluno.disciplinas" :key="j">
                                    <td class="px-3 py-1.5">{{ d.dis_nome }}</td>
                                    <td v-for="u in unidades" :key="u.uni_id" class="px-3 py-1.5 text-center tabular-nums">{{ d.valores[u.uni_id]?.texto ?? '—' }}</td>
                                    <td v-if="consolidado" class="px-3 py-1.5 text-center font-semibold tabular-nums text-indigo-700 dark:text-indigo-300">{{ d.final?.texto ?? '—' }}</td>
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
    @page { size: A4 portrait; margin: 10mm 12mm; }
    body * { visibility: hidden !important; }
    #print-area, #print-area * { visibility: visible !important; }
    #print-area { display: block !important; position: absolute; left: 0; top: 0; width: 100%; font-family: 'Times New Roman', Times, serif; color: #000; }
    .boletim-pagina { page-break-after: always; }
    .boletim-pagina:last-child { page-break-after: auto; }
    .cab { display: grid; grid-template-columns: 90px 1fr 90px; align-items: center; border-bottom: 2px solid #000; padding-bottom: 4px; }
    .logo img { max-width: 85px; max-height: 85px; object-fit: contain; }
    .cab-textos { text-align: center; }
    .cab-entidade { font-size: 12pt; font-weight: 700; text-transform: uppercase; }
    .cab-tit { font-size: 11pt; font-weight: 700; margin-top: 2px; }
    .ident { display: flex; flex-wrap: wrap; gap: 16px; font-size: 9.5pt; margin: 10px 0; }
    .tbl { width: 100%; border-collapse: collapse; font-size: 10pt; }
    .tbl th, .tbl td { border: 1px solid #000; padding: 4px 6px; text-align: center; }
    .tbl th.esq, .tbl td.esq { text-align: left; }
    .tbl thead th { background: #eee; text-transform: uppercase; font-size: 8.5pt; }
    .tbl td.final { font-weight: 700; }
    .rodape { margin-top: 16px; padding-top: 6px; border-top: 1px solid #000; font-size: 8.5pt; display: flex; justify-content: space-between; font-style: italic; }
}
</style>
