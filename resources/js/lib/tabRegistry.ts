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
// Usa 2 segmentos quando 2º segmento é uma rota nomeada (ex.: "segunda-via").
// Ex.:
//   /alunos                  → /alunos
//   /alunos/create           → /alunos
//   /alunos/123/edit         → /alunos
//   /matriculas              → /matriculas
//   /matriculas/segunda-via  → /matriculas/segunda-via  ← aba separada
export function pathOf(url: string): string {
    let pathname: string;
    try {
        pathname = new URL(url, 'http://internal').pathname;
    } catch {
        pathname = url.split('?')[0];
    }
    const segs = pathname.split('/').filter(Boolean);
    if (!segs.length) return '/';
    const first  = segs[0];
    const second = segs[1];

    // Segundo segmento existe, não é numérico e não é "create"/"edit" → rota nomeada → aba própria
    if (second && !/^\d+$/.test(second) && second !== 'create' && second !== 'edit') {
        return `/${first}/${second}`;
    }

    return `/${first}`;
}

const PATH_LABELS: Record<string, string> = {
    '/matriculas/segunda-via': '2ª Via Comprovante',
    '/matriculas-aee': 'Matrículas AEE',
    '/diario/planos':          'Plano de Aula',
    '/diario/planos-aee':      'Plano de Aula AEE',
    '/grade-disciplinar':      'Grade Disciplinar',
    '/turmas-aee':             'Turmas AEE',
    '/movimentacoes':          'Movimentações',
    '/relatorios':             'Relatórios',
    '/relatorios-escola':      'Relatórios da Escola',
    '/relatorios/alunos-por-turma': 'Alunos por Turma',
    '/relatorios/declaracao-matricula': 'Declaração de Matrícula',
    '/relatorios/formacao-classes': 'Formação de Classes',
    '/relatorios/formacao-classes-aee': 'Formação de Classes — AEE',
    '/relatorios/alunos-deficiencia': 'Alunos com Deficiência',
    '/relatorios/alunos-transtorno': 'Alunos com Transtorno',
    '/relatorios/dados-alunos-turma': 'Dados de Alunos por Turma',
    '/relatorios/relacao-turmas-aee': 'Relação de Turmas AEE',
    '/relatorios/relacao-turmas-atividade': 'Relação de Turmas de Atividade',
    '/relatorios/ficha-matricula': 'Ficha de Matrícula',
    '/relatorios/sumario-matriculas': 'Sumário de Matrículas',
};

export function prettifyPath(path: string): string {
    if (PATH_LABELS[path]) return PATH_LABELS[path];
    const seg = path.split('/').filter(Boolean);
    if (!seg.length) return 'Início';
    const last = seg[seg.length - 1];
    return last.charAt(0).toUpperCase() + last.slice(1).replace(/-/g, ' ');
}
