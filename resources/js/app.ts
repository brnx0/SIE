import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { focarPrimeiroErroAposRender } from './lib/focoErro';
import { triggerSessionExpired } from './lib/session';
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

// Nome do app vem do servidor (meta app-name = config('app.name')), não do
// valor embutido no build do Vite — evita ficar preso ao fallback antigo do bundle.
const appName =
    document.querySelector('meta[name="app-name"]')?.getAttribute('content') ||
    import.meta.env.VITE_APP_NAME ||
    'SIE';

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

        // Sessão/CSRF expirada em visitas Inertia (form posts): 419 devolve uma
        // resposta não-Inertia. Em vez do modal padrão, mostra nosso aviso e
        // desloga de fato. 401 = não autenticado → mesmo tratamento.
        router.on('invalid', (event) => {
            const status = (event as any).detail?.response?.status;
            if (status === 419 || status === 401) {
                event.preventDefault();
                triggerSessionExpired();
            }
        });

        // Erro de validação: leva o usuário até o primeiro campo inválido. Fica aqui,
        // e não em cada formulário, porque o Inertia emite este evento em toda visita
        // que volta com erros — vale para as telas de formulário sem tocar em nenhuma.
        router.on('error', () => {
            focarPrimeiroErroAposRender();
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
