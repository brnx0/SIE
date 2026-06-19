<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

interface Cell { texto: string; tipo: string | null }
interface DisciplinaLinha {
    dis_nome: string;
    valores: Record<number, { media: Cell; faltas: number }>;
    media_anual: Cell;
    recuperacao: string | null;
    media_total: Cell;
    total_faltas: number;
}
interface Aluno {
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: string | null;
    aln_nascimento: string | null;
    disciplinas: DisciplinaLinha[];
    totais_faltas: Record<number, number>;
    total_faltas_geral: number;
}
interface Unidade { uni_id: number; label: string }
interface Parametros { estado: string | null; entidade: string | null; secretaria: string | null; logomarca_url: string | null; brasao_url: string | null }
interface Escola { nome: string | null; endereco: string | null; telefone: string | null; email: string | null }

defineProps<{
    parametros: Parametros | null;
    escola: Escola;
    cabecalho: { anl_ano: number; tipo_ensino: string | null; turma: string | null; turno: string | null; serie: string | null };
    unidades: Unidade[];
    alunos: Aluno[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Boletim do Aluno', href: '/relatorios/boletim' }];

const hoje = computed(() => new Date().toLocaleDateString('pt-BR'));
const imprimir = () => window.print();
</script>

<template>
    <Head title="Resultado — Boletim" />

    <Teleport to="body">
    <div id="print-area">
        <section v-for="aluno in alunos" :key="`pb-${aluno.aln_id}`" class="bol-pagina">
            <!-- Cabeçalho institucional -->
            <div class="bol-cab">
                <div class="bol-logo"><img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="" /></div>
                <div class="bol-cab-txt">
                    <div v-if="parametros?.estado">{{ parametros.estado }}</div>
                    <div v-if="parametros?.entidade" class="b">{{ parametros.entidade }}</div>
                    <div v-if="parametros?.secretaria" class="b">{{ parametros.secretaria }}</div>
                    <div v-if="escola.nome" class="b">{{ escola.nome }}</div>
                    <div v-if="escola.endereco">{{ escola.endereco }}</div>
                    <div v-if="escola.telefone || escola.email">
                        <span v-if="escola.telefone">Telefone: {{ escola.telefone }}</span>
                        <span v-if="escola.telefone && escola.email">&nbsp;&nbsp;&nbsp;</span>
                        <span v-if="escola.email">Email: {{ escola.email }}</span>
                    </div>
                </div>
                <div class="bol-cab-data">
                    <div><b>Data:</b> {{ hoje }}</div>
                    <div><b>Ano Letivo:</b> {{ cabecalho.anl_ano }}</div>
                </div>
            </div>

            <div class="bol-titulo">Boletim Escolar</div>

            <!-- Identificação do aluno -->
            <div class="bol-ident">
                <div class="bol-ident-row">
                    <span class="campo nome"><span class="lbl">Nome:</span> {{ aluno.aln_nome }}</span>
                    <span class="campo"><span class="lbl">Matrícula:</span> {{ aluno.aln_nr_matricula ?? '—' }}</span>
                </div>
                <div class="bol-ident-row">
                    <span class="campo"><span class="lbl">Nascimento:</span> {{ aluno.aln_nascimento ?? '—' }}</span>
                    <span class="campo"><span class="lbl">Tipo de ensino:</span> {{ cabecalho.tipo_ensino ?? '—' }}</span>
                    <span class="campo"><span class="lbl">Série:</span> {{ cabecalho.serie ?? '—' }}</span>
                    <span class="campo"><span class="lbl">Turma:</span> {{ cabecalho.turma ?? '—' }}</span>
                    <span class="campo"><span class="lbl">Turno:</span> {{ cabecalho.turno ?? '—' }}</span>
                </div>
            </div>

            <!-- Notas / faltas -->
            <table class="bol-tbl">
                <thead>
                    <tr>
                        <th rowspan="2" class="esq">Componentes curriculares</th>
                        <th v-for="u in unidades" :key="u.uni_id" colspan="2">{{ u.label }}</th>
                        <th rowspan="2">Média anual</th>
                        <th rowspan="2">Recuperação final</th>
                        <th rowspan="2">Média total</th>
                        <th rowspan="2">Total de faltas</th>
                    </tr>
                    <tr>
                        <template v-for="u in unidades" :key="`s-${u.uni_id}`">
                            <th>Média</th>
                            <th>Faltas</th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(d, i) in aluno.disciplinas" :key="i">
                        <td class="esq">{{ d.dis_nome }}</td>
                        <template v-for="u in unidades" :key="`c-${u.uni_id}`">
                            <td>{{ d.valores[u.uni_id]?.media?.texto ?? '—' }}</td>
                            <td>{{ d.valores[u.uni_id]?.faltas ?? 0 }}</td>
                        </template>
                        <td>{{ d.media_anual?.texto ?? '—' }}</td>
                        <td>{{ d.recuperacao ?? '-' }}</td>
                        <td>{{ d.media_total?.texto ?? '—' }}</td>
                        <td>{{ d.total_faltas }}</td>
                    </tr>
                    <tr class="bol-total">
                        <td class="esq">Total de faltas</td>
                        <template v-for="u in unidades" :key="`tf-${u.uni_id}`">
                            <td></td>
                            <td>{{ aluno.totais_faltas[u.uni_id] ?? 0 }}</td>
                        </template>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td>{{ aluno.total_faltas_geral }}</td>
                    </tr>
                    <tr>
                        <td class="esq">Frequência %</td>
                        <template v-for="u in unidades" :key="`fr-${u.uni_id}`">
                            <td></td>
                            <td></td>
                        </template>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <div class="bol-situacao">Situação do aluno:</div>
        </section>
    </div>
    </Teleport>

    <div class="tela-impressao">
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
                <div><strong>Escola:</strong> {{ escola.nome }}</div>
                <div><strong>Turma:</strong> {{ cabecalho.serie }} <span v-if="cabecalho.turno" class="text-muted-foreground">· {{ cabecalho.turno }}</span></div>
                <div><strong>Ano Letivo:</strong> {{ cabecalho.anl_ano }}</div>
            </div>

            <div v-if="!alunos.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">Nenhum aluno ativo nesta turma.</div>

            <!-- Pré-visualização (mesma estrutura, estilo de tela) -->
            <div class="flex flex-col gap-4">
                <div v-for="(aluno, i) in alunos" :key="aluno.aln_id" class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="border-b bg-muted/40 px-4 py-2 text-sm font-semibold">
                        {{ i + 1 }}. {{ aluno.aln_nome }}
                        <span v-if="aluno.aln_nr_matricula" class="text-xs font-normal text-muted-foreground">· Mat. {{ aluno.aln_nr_matricula }}</span>
                    </div>
                    <div class="overflow-x-auto p-2">
                        <table class="w-full border-collapse text-center text-xs">
                            <thead class="bg-muted/30">
                                <tr>
                                    <th rowspan="2" class="border px-2 py-1 text-left">Componentes</th>
                                    <th v-for="u in unidades" :key="u.uni_id" colspan="2" class="border px-2 py-1">{{ u.label }}</th>
                                    <th rowspan="2" class="border px-2 py-1">M. anual</th>
                                    <th rowspan="2" class="border px-2 py-1">Rec.</th>
                                    <th rowspan="2" class="border px-2 py-1">M. total</th>
                                    <th rowspan="2" class="border px-2 py-1">Faltas</th>
                                </tr>
                                <tr>
                                    <template v-for="u in unidades" :key="`hs-${u.uni_id}`">
                                        <th class="border px-2 py-0.5 font-normal">Méd.</th>
                                        <th class="border px-2 py-0.5 font-normal">Flt.</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(d, j) in aluno.disciplinas" :key="j">
                                    <td class="border px-2 py-1 text-left">{{ d.dis_nome }}</td>
                                    <template v-for="u in unidades" :key="`cc-${u.uni_id}`">
                                        <td class="border px-2 py-1 tabular-nums">{{ d.valores[u.uni_id]?.media?.texto ?? '—' }}</td>
                                        <td class="border px-2 py-1 tabular-nums">{{ d.valores[u.uni_id]?.faltas ?? 0 }}</td>
                                    </template>
                                    <td class="border px-2 py-1 font-semibold tabular-nums">{{ d.media_anual?.texto ?? '—' }}</td>
                                    <td class="border px-2 py-1">{{ d.recuperacao ?? '-' }}</td>
                                    <td class="border px-2 py-1 font-semibold tabular-nums">{{ d.media_total?.texto ?? '—' }}</td>
                                    <td class="border px-2 py-1 tabular-nums">{{ d.total_faltas }}</td>
                                </tr>
                                <tr class="bg-muted/20 font-semibold">
                                    <td class="border px-2 py-1 text-left">Total de faltas</td>
                                    <template v-for="u in unidades" :key="`tt-${u.uni_id}`">
                                        <td class="border px-2 py-1"></td>
                                        <td class="border px-2 py-1 tabular-nums">{{ aluno.totais_faltas[u.uni_id] ?? 0 }}</td>
                                    </template>
                                    <td class="border px-2 py-1">-</td>
                                    <td class="border px-2 py-1">-</td>
                                    <td class="border px-2 py-1">-</td>
                                    <td class="border px-2 py-1 tabular-nums">{{ aluno.total_faltas_geral }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
    </div>
</template>

<style>
#print-area { display: none; }
@media print {
    @page { size: A4 portrait; margin: 4mm 8mm 8mm 8mm; }
    body > *:not(#print-area) { display: none !important; }
    #print-area { display: block !important; width: 100%; font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 9pt; }
    .bol-pagina { page-break-after: always; break-after: page; }
    .bol-pagina:last-child { page-break-after: auto; break-after: auto; }

    .bol-cab { display: grid; grid-template-columns: 90px 1fr 150px; align-items: center; gap: 8px; border: 1px solid #000; padding: 6px 8px; }
    .bol-logo img { max-width: 85px; max-height: 70px; object-fit: contain; }
    .bol-cab-txt { line-height: 1.3; font-size: 8.5pt; }
    .bol-cab-txt .b { font-weight: 700; }
    .bol-cab-data { font-size: 8.5pt; line-height: 1.5; }

    .bol-titulo { border: 1px solid #000; border-top: none; text-align: center; font-weight: 700; font-size: 11pt; padding: 8px; }

    .bol-ident { border: 1px solid #000; border-top: none; padding: 6px 10px; font-size: 9pt; }
    .bol-ident-row { display: flex; flex-wrap: wrap; gap: 2px 18px; }
    .bol-ident-row + .bol-ident-row { margin-top: 4px; }
    .bol-ident .nome { flex: 1 1 60%; }
    .bol-ident .lbl { font-weight: 700; }

    .bol-tbl { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 8pt; }
    .bol-tbl th, .bol-tbl td { border: 1px solid #000; padding: 3px 4px; text-align: center; }
    .bol-tbl th { background: #f0f0f0; font-weight: 700; }
    .bol-tbl .esq { text-align: left; }
    .bol-tbl .bol-total td { font-weight: 700; }

    .bol-situacao { border: 1px solid #000; border-top: none; padding: 10px; font-size: 9pt; font-weight: 700; min-height: 32px; }
}
</style>
