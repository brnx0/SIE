import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { vMaska } from 'maska/vue';
import { createPinia } from 'pinia';
import { markRaw, type Component, type DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import TabShell from './components/tabs/TabShell.vue';
import TabRegistrar from './components/tabs/TabRegistrar.vue';
import { pageCache } from './lib/tabRegistry';
import { useTabStore } from './stores/tabs';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const pageModules = import.meta.glob<DefineComponent>('./pages/**/*.vue');

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    // Resolve a página real, guarda no cache (consumido pelos painéis de aba) e
    // devolve o TabRegistrar como "página" do Inertia — ele só espelha a
    // navegação para a store; a renderização real ocorre nos painéis do TabShell.
    resolve: async (name): Promise<DefineComponent> => {
        const importer = pageModules[`./pages/${name}.vue`];
        const module = (await importer()) as unknown as { default: Component };
        pageCache.set(name, markRaw(module.default));
        return TabRegistrar as unknown as DefineComponent;
    },
    setup({ el, App, props, plugin }) {
        const inertiaProps = props as unknown as Record<string, unknown>;
        const pinia = createPinia();
        createApp({ render: () => h(TabShell, { inertiaApp: App, inertiaProps }) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .directive('maska', vMaska)
            .mount(el);

        // Marca timestamp em interações reais do usuário (click/submit/Enter).
        // Visitas Inertia disparadas <500ms depois entram como user-initiated
        // → openTab troca de aba. Reloads automáticos de aba oculta (watcher,
        // timer) não têm interação recente → openTab só atualiza dados sem
        // trocar a aba ativa.
        const store = useTabStore(pinia);
        const mark = () => store.markUserVisit();
        document.addEventListener('click', mark, { capture: true });
        document.addEventListener('submit', mark, { capture: true });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') mark();
        }, { capture: true });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
