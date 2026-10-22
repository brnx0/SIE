import type { Component, InjectionKey } from 'vue';

// Cache de componentes de página resolvidos pelo Inertia (preenchido em app.ts no resolve).
// Chave = nome do componente Inertia (ex.: "alunos/Index").
export const pageCache = new Map<string, Component>();

// Sinaliza que estamos dentro do shell de abas (AppLayout vira pass-through).
export const TAB_CONTEXT: InjectionKey<boolean> = Symbol('tab-context');

// ID da aba dona do painel atual (provido por TabPanel).
export const TAB_ID: InjectionKey<string> = Symbol('tab-id');

// Identidade da aba.
// Regra: usa 1º segmento para CRUD (2º segmento numérico ou "create"/"edit").
// Estende para o 2º/3º segmento enquanto forem rotas nomeadas (nem numérico,
// nem "create"/"edit"), permitindo abas distintas sob um mesmo grupo.
// Ex.:
//   /alunos                          → /alunos
//   /alunos/create                   → /alunos
//   /alunos/123/edit                 → /alunos
//   /matriculas/segunda-via          → /matriculas/segunda-via   ← aba separada
//   /transporte/veiculos/create      → /transporte/veiculos
//   /transporte/relatorios/frota     → /transporte/relatorios/frota       ← aba separada
//   /transporte/relatorios/ocorrencias → /transporte/relatorios/ocorrencias ← aba separada
export function pathOf(url: string): string {
    let pathname: string;
    try {
        pathname = new URL(url, 'http://internal').pathname;
    } catch {
        pathname = url.split('?')[0];
    }
    const segs = pathname.split('/').filter(Boolean);
    if (!segs.length) return '/';

    // Rota nomeada = segmento que não é numérico nem ação CRUD.
    const isNamed = (s: string | undefined): s is string =>
        !!s && !/^\d+$/.test(s) && s !== 'create' && s !== 'edit';

    const [first, second, third] = segs;

    if (isNamed(second)) {
        // 3º segmento também nomeado (ex.: relatórios/frota) → aba própria mais específica.
        if (isNamed(third)) return `/${first}/${second}/${third}`;
        return `/${first}/${second}`;
    }

    return `/${first}`;
}

const PATH_LABELS: Record<string, string> = {
    '/matriculas/segunda-via': '2ª Via Comprovante',
    '/matriculas-aee': 'Matrículas AEE',
    '/auditoria': 'Auditoria',
    '/diario': 'Diário de Classe',
    '/remanejamento-turmas': 'Troca entre Turmas',
    '/encerramento-turmas': 'Encerramento de Turmas',
    '/diario-atividade': 'Diário Atividade',
    '/diario-aee': 'Diário AEE',
    '/diario/planos': 'Plano de Aula',
    '/diario/planos-aee': 'Plano de Aula AEE',
    '/diario/quadro-horario': 'Quadro de Horário',
    '/diario/instrumentos-avaliativos': 'Instrumentos Avaliativos',
    '/diario/termos-diagnostico': 'Termos da Avaliação Diagnóstica',
    '/diario/parametrizacao-avaliacoes': 'Parametrização de Avaliações',
    '/coordenador/planos': 'Validação de Planos',
    '/coordenador-interno/planos-aee': 'Validação de Planos AEE',
    '/secretaria/acessos-professores': 'Acessos de Professores',
    '/secretaria/lancamento-manual': 'Notas e Faltas',
    '/secretaria/motivos-baixa-frequencia': 'Motivo Baixa Frequência',
    '/secretaria/justificativas-falta': 'Justificativa de Falta',
    '/secretaria/historico-escolar': 'Histórico Escolar',
    '/secretaria/conteudo-ministrado': 'Conteúdo Ministrado',
    '/grade-disciplinar':  'Grade Disciplinar',
    '/segmentos': 'Etapas de Ensino',
    '/sabados-letivos':   'Sábados Letivos',
    '/turmas-aee': 'Turmas AEE',
    '/movimentacoes': 'Movimentações',
    '/relatorios': 'Relatórios',
    '/relatorios-escola': 'Relatórios Gerais',
    '/relatorios-pedagogico': 'Relatórios Pedagógicos',
    '/relatorios-diario': 'Relatórios do Diário',
    '/relatorios-secretaria': 'Relatórios da Secretaria',
    '/relatorios-censo': 'Relatórios do Censo',
    '/relatorios/parecer-descritivo': 'Parecer Descritivo',
    '/relatorios/boletim': 'Boletim do Aluno',
    '/relatorios/historico-escolar': 'Histórico Escolar',
    '/relatorios/termo-renovacao': 'Termo de Renovação',
    '/relatorios/notas-por-unidade': 'Notas por Unidade',
    '/relatorios/mapa-notas-disciplina': 'Mapa de Notas',
    '/relatorios/frequencia-mensal': 'Frequência Mensal',
    '/relatorios/boletim-avaliativo': 'Boletim Avaliativo',
    '/relatorios/conteudo-ministrado': 'Conteúdo Ministrado',
    '/relatorios/alunos-por-turma': 'Alunos por Turma',
    '/relatorios/declaracao-matricula': 'Declaração de Matrícula',
    '/relatorios/declaracao-conclusao': 'Declaração de Conclusão',
    '/relatorios/formacao-classes': 'Formação de Classes',
    '/relatorios/formacao-classes-aee': 'Formação de Classes — AEE',
    '/relatorios/alunos-deficiencia': 'Alunos com Deficiência',
    '/relatorios/alunos-transtorno': 'Alunos com Transtorno',
    '/relatorios/dados-alunos-turma': 'Dados de Alunos por Turma',
    '/relatorios/relacao-turmas-aee': 'Relação de Turmas AEE',
    '/relatorios/desempenho-aee': 'Desempenho AEE',
    '/relatorios/relacao-turmas-atividade': 'Relação de Turmas de Atividade',
    '/relatorios/ficha-matricula': 'Ficha de Matrícula',
    '/relatorios/sumario-matriculas': 'Sumário de Matrículas',
    '/relatorios/relacao-funcionarios': 'Relação de Funcionários',
    '/relatorios/distorcao-idade': 'Distorção Idade-Série',
    '/relatorios/indices-aprovacao': 'Índices de Aprovação',
    '/relatorios/turmas-multisseriadas': 'Turmas Multisseriadas',
    '/alimentacao/visitas': 'Visitas Técnicas',
    '/alimentacao/perguntas': 'Perguntas do Questionário',
    '/alimentacao/tipos-visita': 'Tipos de Visita Técnica',
    '/alimentacao/tipos-questionario': 'Tipos de Questionário',
    '/alimentacao/preparacoes': 'Controle Diário da Preparação',
    '/alimentacao/demandas': 'Demanda de Merenda',
    '/alimentacao/estoque': 'Consulta de Estoque',
    '/alimentacao/estoque/movimentos': 'Movimentação de Estoque',
    '/alimentacao/recebimentos': 'Recebimento de Itens',
    '/alimentacao/transferencias': 'Transferência para Escolas',
    '/alimentacao/retiradas': 'Retirada de Itens',
    '/alimentacao/cardapios': 'Cardápio Semanal',
    '/alimentacao/contratos': 'Contratos',
    '/alimentacao/empenhos': 'Empenhos',
    '/alimentacao/ordens-fornecimento': 'Ordens de Fornecimento',
    '/alimentacao/pratos': 'Pratos',
    '/alimentacao/itens': 'Itens',
    '/alimentacao/fornecedores': 'Fornecedores',
    '/alimentacao/unidades-medida': 'Unidades de Medida',
    '/alimentacao/categorias-alimento': 'Categorias de Alimento',
    '/alimentacao/tipos-item': 'Tipos de Item',
    '/alimentacao/marcas': 'Marcas',
    '/alimentacao/tipos-retirada': 'Tipos de Retirada',
    '/relatorios/censo-criticas': 'Críticas do Censo',
    '/relatorios/censo-exportacao': 'Exportação do Censo',
    '/transporte/veiculos': 'Veículos',
    '/transporte/usuarios': 'Usuários do Transporte',
    '/transporte/rotas': 'Rotas',
    '/transporte/diario-bordo': 'Diário de Bordo',
    '/transporte/simulador': 'Simulador de Rotas',
    '/transporte/relatorios/frota': 'Situação da Frota',
    '/transporte/relatorios/ocorrencias': 'Ocorrências do Transporte',
};

export function prettifyPath(path: string): string {
    if (PATH_LABELS[path]) return PATH_LABELS[path];
    const seg = path.split('/').filter(Boolean);
    if (!seg.length) return 'Início';
    const last = seg[seg.length - 1];
    return last.charAt(0).toUpperCase() + last.slice(1).replace(/-/g, ' ');
}
