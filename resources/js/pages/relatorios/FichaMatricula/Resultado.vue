<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';

interface Ficha {
    nome: string | null;
    dt_nascimento: string | null;
    sexo: string | null;
    naturalidade: string | null;
    nacionalidade: string | null;
    pais_origem: string | null;
    cor_raca: string | null;
    foto_url: string | null;
    contato_emergencia: string | null;
    telefone_emergencia: string | null;
    cpf: string | null;
    cd_inep: string | null;
    nis: string | null;
    nova_certidao: string | null;
    cartao_sus: string | null;
    filiacao_1: string | null;
    filiacao_1_tipo: string | null;
    filiacao_2: string | null;
    filiacao_2_tipo: string | null;
    cep: string | null;
    logradouro: string | null;
    numero: string | null;
    complemento: string | null;
    bairro: string | null;
    cidade: string | null;
    uf: string | null;
    telefone: string | null;
    celular: string | null;
    email: string | null;
    usa_transporte: string;
    tipo_sanguineo: string | null;
    plano_saude: string | null;
    alergia: string | null;
    restricoes_alimentares: string | null;
    remedio_febre: string | null;
    remedio_cefaleia: string | null;
    patologias: string[];
    outra_doenca: string | null;
    patologias_infancia: string[];
    outra_doenca_infancia: string | null;
    clinicas: string[];
    recursos_inep: string[];
    pcd: string;
    altas_habilidades: string;
    deficiencias: string[];
    deficiencia_outro: string | null;
    cid: string | null;
    saude_obs: string | null;
    nr_matricula: number | null;
    dt_matricula: string | null;
    ano_letivo: number;
    serie: string | null;
    segmento: string | null;
    turma_nome: string | null;
    turno: string | null;
}

interface Parametros {
    nome_entidade: string;
    msg_cab_secretaria: string | null;
    msg_cab_estado: string | null;
    endereco: string | null;
    logomarca_url: string | null;
    brasao_url: string | null;
}

interface Escola {
    nome: string;
    cd_inep: string | null;
    logradouro: string | null;
    numero: string | null;
    bairro: string | null;
    cidade: string | null;
    uf: string | null;
    cep: string | null;
    telefone: string | null;
    email: string | null;
}

const props = defineProps<{
    parametros: Parametros | null;
    escola: Escola;
    fichas: Ficha[];
    total: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ficha de Matrícula', href: '/relatorios/ficha-matricula' },
];

const imprimir = () => window.print();

const formatCpf = (cpf: string | null) => {
    if (!cpf) return '—';
    const d = cpf.replace(/\D/g, '');
    return d.length === 11 ? `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9)}` : cpf;
};

const formatCep = (cep: string | null) => {
    if (!cep) return '—';
    const d = cep.replace(/\D/g, '');
    return d.length === 8 ? `${d.slice(0,5)}-${d.slice(5)}` : cep;
};

const enderecoEscola = (e: Escola) => {
    const partes = [e.logradouro, e.numero ? ', ' + e.numero : ''].filter(Boolean);
    const cidUf = [e.cidade, e.uf].filter(Boolean).join(' - ');
    return [partes.join(''), cidUf, e.cep ? 'CEP: ' + formatCep(e.cep) : ''].filter(Boolean).join(' - ');
};

const enderecoAluno = (f: Ficha) => {
    const linha = [f.logradouro, f.numero ? ', ' + f.numero : '', f.complemento ? ' - ' + f.complemento : ''].join('');
    return linha || '—';
};

const sexoLabel = (s: string | null) => {
    if (!s) return '—';
    if (s.toUpperCase() === 'M') return 'Masculino';
    if (s.toUpperCase() === 'F') return 'Feminino';
    return s;
};
</script>

<template>
    <Head title="Ficha de Matrícula" />

    <!-- Bloco impressão (teleportado p/ body — page-break funciona corretamente) -->
    <Teleport to="body">
    <div id="print-area">
        <div v-for="(f, idx) in fichas" :key="idx" class="ficha-aluno">
        <div class="folha pagina-1">
            <!-- Cabeçalho institucional + escola -->
            <header class="cabecalho-ficha">
                <div class="logomarca">
                    <img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="Logomarca" />
                </div>
                <div class="cab-textos">
                    <div v-if="parametros?.msg_cab_estado" class="cab-linha">{{ parametros.msg_cab_estado }}</div>
                    <div v-if="parametros?.msg_cab_secretaria" class="cab-linha">{{ parametros.msg_cab_secretaria }}</div>
                    <div class="cab-entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
                    <div class="cab-escola-nome">{{ escola.nome }}</div>
                    <div class="cab-end">{{ enderecoEscola(escola) }}</div>
                    <div class="cab-end">
                        <span v-if="escola.email">E-mail: {{ escola.email }}</span>
                        <span v-if="escola.cd_inep" class="ml-3">INEP: {{ escola.cd_inep }}</span>
                    </div>
                </div>
                <div class="brasao">
                    <img v-if="parametros?.brasao_url" :src="parametros.brasao_url" alt="Brasão" />
                </div>
            </header>

            <div class="titulo-ficha">FICHA DE MATRÍCULA</div>

            <!-- Dados do Aluno -->
            <section class="bloco">
                <div class="bloco-titulo">DADOS DO ALUNO</div>
                <div class="bloco-corpo dados-aluno-grid">
                    <div class="dados-campos">
                        <div class="campo full"><b>NOME:</b> {{ f.nome ?? '—' }}</div>
                        <div class="campo"><b>DATA DE NASCIMENTO:</b> {{ f.dt_nascimento ?? '—' }}</div>
                        <div class="campo"><b>SEXO:</b> {{ sexoLabel(f.sexo) }}</div>
                        <div class="campo"><b>NACIONALIDADE:</b> {{ f.nacionalidade ?? '—' }}</div>
                        <div class="campo"><b>NATURALIDADE:</b> {{ f.naturalidade ?? '—' }}</div>
                        <div class="campo"><b>PAÍS DE ORIGEM:</b> {{ f.pais_origem ?? '—' }}</div>
                        <div class="campo"><b>COR/RAÇA:</b> {{ f.cor_raca ?? '—' }}</div>
                        <div class="campo"><b>ALERGIA/RESTRIÇÃO:</b> {{ f.alergia ?? '—' }}</div>
                    </div>
                    <div class="foto-box">
                        <img v-if="f.foto_url" :src="f.foto_url" alt="Foto" />
                        <div v-else class="foto-vazia">FOTO</div>
                    </div>
                </div>
            </section>

            <!-- Documentos -->
            <section class="bloco">
                <div class="bloco-titulo">DOCUMENTOS DO ALUNO</div>
                <div class="bloco-corpo dois-cols">
                    <div class="campo"><b>CPF:</b> {{ formatCpf(f.cpf) }}</div>
                    <div class="campo"><b>CÓDIGO SUS:</b> {{ f.cartao_sus ?? '—' }}</div>
                    <div class="campo"><b>CÓDIGO INEP:</b> {{ f.cd_inep ?? '—' }}</div>
                    <div class="campo"><b>NIS (PIS/PASEP):</b> {{ f.nis ?? '—' }}</div>
                    <div class="campo full"><b>NOVA CERTIDÃO:</b> {{ f.nova_certidao ?? '—' }}</div>
                </div>
            </section>

            <!-- Filiação -->
            <section class="bloco">
                <div class="bloco-titulo">FILIAÇÃO</div>
                <div class="bloco-corpo">
                    <div class="sub-titulo">Filiação 1 ({{ f.filiacao_1_tipo ?? '—' }})</div>
                    <div class="campo full"><b>NOME:</b> {{ f.filiacao_1 ?? '—' }}</div>
                    <div class="sub-titulo">Filiação 2 ({{ f.filiacao_2_tipo ?? '—' }})</div>
                    <div class="campo full"><b>NOME:</b> {{ f.filiacao_2 ?? '—' }}</div>
                </div>
            </section>

            <!-- Endereço -->
            <section class="bloco">
                <div class="bloco-titulo">ENDEREÇO</div>
                <div class="bloco-corpo dois-cols">
                    <div class="campo full"><b>ENDEREÇO:</b> {{ enderecoAluno(f) }}</div>
                    <div class="campo"><b>BAIRRO:</b> {{ f.bairro ?? '—' }}</div>
                    <div class="campo"><b>CIDADE:</b> {{ f.cidade ?? '—' }}</div>
                    <div class="campo"><b>ESTADO:</b> {{ f.uf ?? '—' }}</div>
                    <div class="campo"><b>CEP:</b> {{ formatCep(f.cep) }}</div>
                </div>
            </section>

            <!-- Contato -->
            <section class="bloco">
                <div class="bloco-titulo">CONTATO E OUTRAS INFORMAÇÕES</div>
                <div class="bloco-corpo dois-cols">
                    <div class="campo"><b>TELEFONE:</b> {{ f.telefone ?? '—' }}</div>
                    <div class="campo"><b>CELULAR:</b> {{ f.celular ?? '—' }}</div>
                    <div class="campo full"><b>E-MAIL:</b> {{ f.email ?? '—' }}</div>
                    <div class="campo"><b>UTILIZA TRANSPORTE:</b> {{ f.usa_transporte }}</div>
                    <div class="campo"><b>CONTATO DE EMERGÊNCIA:</b> {{ f.contato_emergencia ?? '—' }}</div>
                    <div class="campo"><b>TEL. EMERGÊNCIA:</b> {{ f.telefone_emergencia ?? '—' }}</div>
                </div>
            </section>

            <!-- Saúde -->
            <section class="bloco">
                <div class="bloco-titulo">SAÚDE</div>
                <div class="bloco-corpo dois-cols">
                    <div class="campo"><b>TIPO SANGUÍNEO:</b> {{ f.tipo_sanguineo ?? '—' }}</div>
                    <div class="campo"><b>PLANO DE SAÚDE:</b> {{ f.plano_saude ?? '—' }}</div>
                    <div class="campo full"><b>ALERGIA A:</b> {{ f.alergia ?? '—' }}</div>
                    <div class="campo full"><b>RESTRIÇÕES ALIMENTARES:</b> {{ f.restricoes_alimentares ?? '—' }}</div>
                    <div class="campo"><b>REMÉDIO P/ FEBRE:</b> {{ f.remedio_febre ?? '—' }}</div>
                    <div class="campo"><b>REMÉDIO P/ CEFALÉIA:</b> {{ f.remedio_cefaleia ?? '—' }}</div>
                    <div class="campo full"><b>PATOLOGIAS:</b> {{ f.patologias.length ? f.patologias.join(', ') : '—' }}</div>
                    <div class="campo full"><b>OUTRA DOENÇA:</b> {{ f.outra_doenca ?? '—' }}</div>
                    <div class="campo full"><b>PATOLOGIAS DA INFÂNCIA:</b> {{ f.patologias_infancia.length ? f.patologias_infancia.join(', ') : '—' }}</div>
                    <div class="campo full"><b>OUTRA DOENÇA (INFÂNCIA):</b> {{ f.outra_doenca_infancia ?? '—' }}</div>
                    <div class="campo full"><b>CLÍNICAS QUE FREQUENTA:</b> {{ f.clinicas.length ? f.clinicas.join(', ') : '—' }}</div>
                    <div class="campo full"><b>RECURSOS INEP:</b> {{ f.recursos_inep.length ? f.recursos_inep.join(', ') : '—' }}</div>
                </div>
            </section>

            <!-- Deficiência -->
            <section class="bloco">
                <div class="bloco-titulo">DEFICIÊNCIA / TGD / ALTAS HABILIDADES</div>
                <div class="bloco-corpo dois-cols">
                    <div class="campo"><b>PCD:</b> {{ f.pcd }}</div>
                    <div class="campo"><b>ALTAS HABILIDADES:</b> {{ f.altas_habilidades }}</div>
                    <div class="campo full"><b>DEFICIÊNCIAS / TRANSTORNOS:</b> {{ f.deficiencias.length ? f.deficiencias.join(', ') : '—' }}</div>
                    <div class="campo full"><b>OUTRA DEFICIÊNCIA:</b> {{ f.deficiencia_outro ?? '—' }}</div>
                    <div class="campo full"><b>CID:</b> {{ f.cid ?? '—' }}</div>
                    <div class="campo full"><b>OBSERVAÇÃO:</b> {{ f.saude_obs ?? '—' }}</div>
                </div>
            </section>

            <!-- Matrícula -->
            <section class="bloco destaque">
                <div class="bloco-titulo">MATRÍCULA ATUAL</div>
                <div class="bloco-corpo dois-cols">
                    <div class="campo"><b>MATRÍCULA Nº:</b> {{ f.nr_matricula ?? '—' }}</div>
                    <div class="campo"><b>DATA MATRÍCULA:</b> {{ f.dt_matricula ?? '—' }}</div>
                    <div class="campo full"><b>ESCOLA:</b> {{ escola.nome }}</div>
                    <div class="campo"><b>ANO:</b> {{ f.ano_letivo }}</div>
                    <div class="campo"><b>SÉRIE:</b> {{ f.serie ?? '—' }}</div>
                    <div class="campo full"><b>CURSO:</b> {{ f.segmento ?? '—' }}</div>
                    <div class="campo"><b>TURMA:</b> {{ f.turma_nome ?? '—' }}</div>
                    <div class="campo"><b>TURNO:</b> {{ f.turno ?? '—' }}</div>
                </div>
            </section>

            <div class="declaracao">Declaro acatar as normas dessa unidade de ensino.</div>

            <div class="assinaturas">
                <div class="ass-box">
                    <div class="linha-ass"></div>
                    <div class="ass-label">Assinatura do Responsável</div>
                </div>
                <div class="ass-box">
                    <div class="linha-ass"></div>
                    <div class="ass-label">Assinatura do Diretor(a)</div>
                </div>
            </div>
        </div>

        <!-- ============ PÁGINA 2 ============ -->
        <div class="folha pagina-2">
            <header class="cabecalho-ficha">
                <div class="logomarca">
                    <img v-if="parametros?.logomarca_url" :src="parametros.logomarca_url" alt="Logomarca" />
                </div>
                <div class="cab-textos">
                    <div v-if="parametros?.msg_cab_estado" class="cab-linha">{{ parametros.msg_cab_estado }}</div>
                    <div v-if="parametros?.msg_cab_secretaria" class="cab-linha">{{ parametros.msg_cab_secretaria }}</div>
                    <div class="cab-entidade">{{ parametros?.nome_entidade ?? '—' }}</div>
                    <div class="cab-escola-nome">{{ escola.nome }}</div>
                    <div class="cab-end">{{ enderecoEscola(escola) }}</div>
                    <div class="cab-end">
                        <span v-if="escola.email">E-mail: {{ escola.email }}</span>
                        <span v-if="escola.cd_inep" class="ml-3">INEP: {{ escola.cd_inep }}</span>
                    </div>
                </div>
                <div class="brasao">
                    <img v-if="parametros?.brasao_url" :src="parametros.brasao_url" alt="Brasão" />
                </div>
            </header>

            <div class="titulo-ficha">RENOVAÇÃO DE MATRÍCULA</div>

            <table class="tabela-renovacao">
                <thead>
                    <tr>
                        <th>DATA</th>
                        <th>SEGMENTO</th>
                        <th>ANO</th>
                        <th>ASSINATURA DO RESPONSÁVEL</th>
                        <th>OBSERVAÇÃO</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="i in 14" :key="`ren-${idx}-${i}`">
                        <td></td><td></td><td></td><td></td><td></td>
                    </tr>
                </tbody>
            </table>

            <section class="bloco autoriza">
                <div class="bloco-titulo">Autorização - Eventos x Transporte</div>
                <div class="bloco-corpo texto-autorizacao">
                    Autorizo meu filho(a) a participar de eventos escolares no decorrer do ano letivo dentro e fora do município utilizando o transporte escolar.
                </div>
            </section>

            <section class="bloco autoriza">
                <div class="bloco-titulo">Licença de Imagem</div>
                <div class="bloco-corpo texto-autorizacao">
                    Pela assinatura desta Declaração de Dados Cadastrais igualmente AUTORIZO o uso da imagem do(a) aluno(a) <b>{{ (f.nome ?? '').toUpperCase() }}</b> o(a) qual está sob minha responsabilidade, em todo e qualquer material entre fotos, documentos e outros meios de comunicação, para ser utilizada em campanhas promocionais e institucionais da Escola, Secretaria e/ou Prefeitura Municipal, bem como fundos, fundações e autarquias dela derivados, sejam essas destinadas à divulgação ao público em geral e/ou apenas para uso interno desta instituição, desde que não haja desvirtuamento da sua finalidade. A presente autorização é concedida a título gratuito, abrangendo o uso da imagem acima mencionada em todo território nacional e no exterior, em todas as suas modalidades e, em destaque, das seguintes formas: (I) out-door; (II) busdoor; (III) folhetos em geral (encartes, mala direta, catálogo, etc.); (IV) Folder de apresentação; (V) anúncios em revistas e jornais em geral (VI) home page; (VII) cartazes; (VIII) back-light; (IX) mídia eletrônica (painéis, vídeo-tapes, televisão, cinema, programa para rádio, entre outros).
                </div>
            </section>

            <div class="assinaturas">
                <div class="ass-box">
                    <div class="linha-ass"></div>
                    <div class="ass-label">Assinatura do Responsável</div>
                </div>
                <div class="ass-box">
                    <div class="linha-ass"></div>
                    <div class="ass-label">Assinatura do Diretor(a)</div>
                </div>
            </div>
        </div>
        </div>
    </div>
    </Teleport>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Fichas Geradas</h1>
                <div class="flex gap-2">
                    <Link href="/relatorios/ficha-matricula">
                        <Button variant="outline"><ArrowLeft class="mr-1 size-4" /> Voltar</Button>
                    </Link>
                    <Button :disabled="!total" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="imprimir">
                        <Printer class="mr-1 size-4" /> Imprimir / PDF
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm mb-4 text-sm">
                <div><strong>Escola:</strong> {{ escola.nome }}</div>
                <div><strong>Total fichas:</strong> {{ total }}</div>
            </div>

            <div v-if="!total" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                Nenhum aluno encontrado.
            </div>
            <div v-else class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">
                    {{ total }} ficha(s) prontas. Clique em "Imprimir / PDF" para visualizar.
                </p>
                <ul class="mt-3 max-h-64 overflow-y-auto text-sm">
                    <li v-for="(f, i) in fichas" :key="i" class="border-b py-1.5 last:border-0">
                        <strong>{{ i + 1 }}.</strong> {{ f.nome }} <span class="text-xs text-muted-foreground">— {{ f.turma_nome }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

<style>
#print-area { display: none; }

@media print {
    @page { size: A4 portrait; margin: 4mm 10mm 8mm 10mm; }
    body > *:not(#print-area) { display: none !important; }
    #print-area {
        display: block !important;
        position: static;
        width: 100%;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
        background: white;
        font-size: 8.5pt;
    }

    .ficha-aluno { }
    .folha {
        padding: 0 4mm;
    }
    .folha.pagina-1 {
        page-break-after: always;
        break-after: page;
    }
    .folha.pagina-2 {
        page-break-after: always;
        break-after: page;
        page-break-before: always;
        break-before: page;
    }
    .ficha-aluno:last-child .folha.pagina-2 {
        page-break-after: auto;
        break-after: auto;
    }

    .tabela-renovacao {
        width: 100%;
        border-collapse: collapse;
        margin: 8px 0 10px;
        border: 1px solid #000;
        font-size: 8.5pt;
    }
    .tabela-renovacao th {
        border: 1px solid #000;
        padding: 4px 6px;
        font-size: 7.5pt;
        font-weight: 700;
        text-transform: uppercase;
        text-align: center;
    }
    .tabela-renovacao td {
        border: 1px solid #000;
        height: 22px;
    }

    .autoriza { margin-bottom: 6px; }
    .texto-autorizacao {
        font-size: 8pt;
        text-align: justify;
        line-height: 1.4;
    }

    .cabecalho-ficha {
        display: grid;
        grid-template-columns: 80px 1fr 80px;
        align-items: center;
        gap: 8px;
        padding: 0 0 3px;
        margin-top: 0;
        border-bottom: 1.5px solid #000;
        margin-bottom: 4px;
    }
    .brasao img, .logomarca img { max-width: 80px; max-height: 80px; object-fit: contain; }
    .cab-textos { text-align: center; line-height: 1.25; }
    .cab-linha { font-size: 8pt; font-weight: 700; }
    .cab-entidade { font-size: 9pt; font-weight: 700; }
    .cab-escola-nome { font-size: 10pt; font-weight: 700; margin-top: 2px; }
    .cab-end { font-size: 7.5pt; }
    .ml-3 { margin-left: 12px; }

    .titulo-ficha {
        text-align: center;
        font-size: 11pt;
        font-weight: 700;
        margin: 2px 0 4px;
    }

    .bloco {
        border: 1px solid #000;
        margin-bottom: 4px;
        page-break-inside: avoid;
    }
    .bloco-titulo {
        background: transparent;
        border-bottom: 1px solid #000;
        padding: 2px 6px;
        font-size: 8.5pt;
        font-weight: 700;
        text-transform: uppercase;
    }
    .bloco-corpo {
        padding: 4px 6px;
    }
    .bloco.destaque .bloco-titulo {
        border-bottom: 2px solid #000;
    }

    .sub-titulo {
        font-size: 8pt;
        font-weight: 700;
        text-transform: uppercase;
        margin: 3px 0 1px;
        border-bottom: 0.5px dotted #000;
    }

    .dados-aluno-grid {
        display: grid;
        grid-template-columns: 1fr 90px;
        gap: 6px;
    }
    .dados-campos {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2px 12px;
    }
    .foto-box {
        border: 1px solid #000;
        width: 85px;
        height: 110px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .foto-box img { max-width: 100%; max-height: 100%; object-fit: cover; }
    .foto-vazia { font-size: 7pt; color: #888; }

    .dois-cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2px 12px;
    }
    .campo { font-size: 8.5pt; line-height: 1.35; }
    .campo.full { grid-column: 1 / -1; }
    .campo b { text-transform: uppercase; }

    .declaracao {
        margin-top: 10px;
        font-size: 8pt;
    }

    .assinaturas {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 22px;
    }
    .ass-box { text-align: center; }
    .linha-ass {
        border-top: 1px solid #000;
        margin-bottom: 2px;
    }
    .ass-label {
        font-size: 8pt;
    }
}
</style>
