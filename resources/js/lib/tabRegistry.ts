import type { Component, InjectionKey } from 'vue';

// Cache de componentes de página resolvidos pelo Inertia (preenchido em app.ts no resolve).
// Chave = nome do componente Inertia (ex.: "alunos/Index").
export const pageCache = new Map<string, Component>();

// Sinaliza que estamos dentro do shell de abas (AppLayout vira pass-through).
export const TAB_CONTEXT: InjectionKey<boolean> = Symbol('tab-context');

// ID da aba dona do painel atual (provido por TabPanel).
export const TAB_ID: InjectionKey<string> = Symbol('tab-id');

// Identidade da aba = primeiro segmento do path. Cobre CRUD inteiro do recurso
// numa única aba: /alunos, /alunos/create, /alunos/123/edit → mesma aba "alunos".
// Pagination/filtros (?page=2) também reusam (mesma raiz).
export function pathOf(url: string): string {
    let pathname: string;
    try {
        pathname = new URL(url, 'http://internal').pathname;
    } catch {
        pathname = url.split('?')[0];
    }
    const first = pathname.split('/').filter(Boolean)[0];
    return first ? `/${first}` : '/';
}

export function prettifyPath(path: string): string {
    const seg = path.split('/').filter(Boolean);
    if (!seg.length) return 'Início';
    const first = seg[0];
    return first.charAt(0).toUpperCase() + first.slice(1);
}
