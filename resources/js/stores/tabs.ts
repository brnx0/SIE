import { defineStore } from 'pinia';
import { markRaw, type Component } from 'vue';
import { pathOf, prettifyPath } from '@/lib/tabRegistry';
import type { BreadcrumbItemType } from '@/types';

export interface Tab {
    id: string;
    path: string; // identidade (pathname sem query)
    url: string; // url completa atual
    title: string;
    component: Component;
    props: Record<string, unknown>;
    breadcrumbs: BreadcrumbItemType[];
}

let seq = 0;

interface OpenPayload {
    url: string;
    component: Component;
    props: Record<string, unknown>;
}

export const useTabStore = defineStore('tabs', {
    state: () => ({
        tabs: [] as Tab[],
        activeId: '' as string,
    }),

    getters: {
        activeTab: (s): Tab | null => s.tabs.find((t) => t.id === s.activeId) ?? null,
    },

    actions: {
        // Chamado pelo TabRegistrar a cada navegação real do Inertia.
        openTab(payload: OpenPayload): string {
            const path = pathOf(payload.url);
            const existing = this.tabs.find((t) => t.path === path);

            if (existing) {
                // Mesma tela (ex.: paginação/filtro) → atualiza dados sem remontar.
                existing.url = payload.url;
                existing.props = payload.props;
                existing.component = markRaw(payload.component);
                this.activeId = existing.id;
                return existing.id;
            }

            const id = `tab-${++seq}`;
            this.tabs.push({
                id,
                path,
                url: payload.url,
                title: prettifyPath(path),
                component: markRaw(payload.component),
                props: payload.props,
                breadcrumbs: [],
            });
            this.activeId = id;
            return id;
        },

        setActive(id: string): void {
            if (this.tabs.some((t) => t.id === id)) this.activeId = id;
        },

        findByPath(path: string): Tab | null {
            return this.tabs.find((t) => t.path === path) ?? null;
        },

        setMeta(id: string, meta: { breadcrumbs?: BreadcrumbItemType[]; title?: string }): void {
            const tab = this.tabs.find((t) => t.id === id);
            if (!tab) return;
            if (meta.breadcrumbs) tab.breadcrumbs = meta.breadcrumbs;
            if (meta.title) tab.title = meta.title;
        },

        closeTab(id: string): string | null {
            const i = this.tabs.findIndex((t) => t.id === id);
            if (i < 0) return this.activeTab?.url ?? null;

            const wasActive = this.activeId === id;
            this.tabs.splice(i, 1);

            if (wasActive) {
                const next = this.tabs[i] ?? this.tabs[i - 1] ?? null;
                this.activeId = next ? next.id : '';
                return next ? next.url : null;
            }
            return this.activeTab?.url ?? null;
        },

        clear(): void {
            this.tabs = [];
            this.activeId = '';
        },
    },
});
