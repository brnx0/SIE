<script setup lang="ts">
import { computed, onBeforeUnmount, provide, watch, type Component } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppContent from '@/components/layout/AppContent.vue';
import AppShell from '@/components/layout/AppShell.vue';
import AppSidebar from '@/components/layout/AppSidebar.vue';
import AppSidebarHeader from '@/components/layout/AppSidebarHeader.vue';
import FlashToaster from '@/components/common/FlashToaster.vue';
import SessionExpiredDialog from '@/components/common/SessionExpiredDialog.vue';
import TabBar from './TabBar.vue';
import TabPanel from './TabPanel.vue';
import { useTabStore } from '@/stores/tabs';
import { TAB_CONTEXT, pageCache } from '@/lib/tabRegistry';

// App + props vêm do createInertiaApp.setup (motor de roteamento do Inertia).
defineProps<{ inertiaApp: Component; inertiaProps: Record<string, unknown> }>();

provide(TAB_CONTEXT, true);

const page = usePage();
const isAuthenticated = computed(() => !!(page.props as { auth?: { user?: unknown } })?.auth?.user);

// Páginas públicas de tela cheia (ex.: Central de Ajuda) renderizam SEM o chrome
// de app (sidebar/abas), mesmo com usuário logado — elas têm layout próprio.
const STANDALONE_COMPONENTS = ['ajuda/Index'];
// Telas de autenticação (Login, ForgotPassword…) também são SEMPRE tela cheia:
// quando a sessão expira no meio de uma navegação, o redirect para /login não
// pode virar uma aba dentro do sistema (com sidebar) ao lado das abas abertas.
// page.component ainda é undefined no 1º render (o motor Inertia monta no mesmo ciclo).
const isAuthPage = computed(() => (page.component ?? '').startsWith('auth/'));
const isStandalone = computed(() => isAuthPage.value || STANDALONE_COMPONENTS.includes(page.component ?? ''));
// Renderiza a página standalone DIRETO do cache — fora do sistema de abas/store.
// Assim não depende de openTab nem sofre com o store.clear() disparado na
// hidratação autenticada (auth null→user), que era o que zerava a aba e deixava
// a tela branca quando logado.
const standalonePage = computed<Component | null>(() =>
    isStandalone.value ? (pageCache.get(page.component) ?? null) : null,
);

const store = useTabStore();
const activeBreadcrumbs = computed(() => store.activeTab?.breadcrumbs ?? []);

// Sincroniza URL do browser com aba ativa (cosmético; replaceState não
// dispara nova requisição nem cria entry no histórico).
const syncUrl = () => {
    // Página standalone (Login, Ajuda…) fica fora das abas e é dona da URL. Sem
    // isto, indo de "/" (Welcome, aba ativa) para /login, a URL voltava para "/"
    // e o back() do erro de credencial levava à página inicial, sem a mensagem.
    if (isStandalone.value) return;
    const url = store.activeTab?.url;
    if (url && url !== window.location.pathname + window.location.search) {
        window.history.replaceState(window.history.state, '', url);
    }
};
watch(() => store.activeTab?.url, syncUrl);

// Inertia faz pushState para a URL respondida em QUALQUER visita, inclusive
// reloads de abas ocultas. Se isso colocar o browser em URL diferente da
// aba ativa, força volta para a URL da aba ativa.
watch(() => page.url, syncUrl);

// Defesa adicional: alguns push/replaceState do Inertia rodam fora do ciclo
// reativo (queue interna). Listener nos eventos do router cobre esses casos.
const offFinish = router.on('finish', () => syncUrl());
const offNavigate = router.on('navigate', () => syncUrl());
onBeforeUnmount(() => {
    offFinish();
    offNavigate();
});

// Reset de abas em transições de auth (login/logout) → evita aba "Login"
// persistir após autenticar, e abas autenticadas após logout.
watch(isAuthenticated, () => store.clear());
</script>

<template>
    <!-- Motor Inertia (invisível): mantém usePage/flash/headManager vivos e
         dispara TabRegistrar a cada navegação. Renderiza primeiro para que
         `h` (usePage) esteja populado antes da sidebar montar. -->
    <div style="display: none">
        <component :is="inertiaApp" v-bind="inertiaProps" />
    </div>

    <!-- Página pública standalone (Ajuda): layout próprio, sem chrome de app nem
         abas, mesmo logado. Renderizada direto do cache do Inertia. -->
    <template v-if="isStandalone && standalonePage">
        <component :is="standalonePage" v-bind="page.props" />
        <!-- Login recebe flash (ex.: "Sua sessão expirou") → precisa do toaster. -->
        <FlashToaster v-if="isAuthPage" />
    </template>

    <AppShell v-else-if="isAuthenticated" variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="activeBreadcrumbs" />
            <TabBar />
            <div class="relative flex-1 overflow-auto">
                <TabPanel
                    v-for="tab in store.tabs"
                    v-show="tab.id === store.activeId"
                    :key="tab.id"
                    :tab-id="tab.id"
                >
                    <component :is="tab.component" v-bind="tab.props" />
                </TabPanel>
            </div>
        </AppContent>
        <FlashToaster />
    </AppShell>

    <!-- Sem chrome de app: deslogado (Login/Register com AuthLayout próprio) ou
         página pública standalone (Ajuda) com layout próprio, mesmo logado. -->
    <template v-else>
        <TabPanel
            v-for="tab in store.tabs"
            v-show="tab.id === store.activeId"
            :key="tab.id"
            :tab-id="tab.id"
        >
            <component :is="tab.component" v-bind="tab.props" />
        </TabPanel>
        <FlashToaster />
    </template>

    <!-- Aviso global de sessão expirada (419/401) -->
    <SessionExpiredDialog />
</template>
