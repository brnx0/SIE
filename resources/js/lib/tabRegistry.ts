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

export function prettifyPath(path: string): string {
    const seg = path.split('/').filter(Boolean);
    if (!seg.length) return 'Início';
    const first = seg[0];
    return first.charAt(0).toUpperCase() + first.slice(1);
}
