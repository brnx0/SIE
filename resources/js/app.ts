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
        createApp({ render: () => h(TabShell, { inertiaApp: App, inertiaProps }) })
            .use(plugin)
            .use(createPinia())
            .use(ZiggyVue)
            .directive('maska', vMaska)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
