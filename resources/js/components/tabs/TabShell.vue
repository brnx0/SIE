<script setup lang="ts">
import { computed, onBeforeUnmount, provide, watch, type Component } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppContent from '@/components/layout/AppContent.vue';
import AppShell from '@/components/layout/AppShell.vue';
import AppSidebar from '@/components/layout/AppSidebar.vue';
import AppSidebarHeader from '@/components/layout/AppSidebarHeader.vue';
import FlashToaster from '@/components/common/FlashToaster.vue';
import TabBar from './TabBar.vue';
import TabPanel from './TabPanel.vue';
import { useTabStore } from '@/stores/tabs';
import { TAB_CONTEXT } from '@/lib/tabRegistry';

// App + props vêm do createInertiaApp.setup (motor de roteamento do Inertia).
defineProps<{ inertiaApp: Component; inertiaProps: Record<string, unknown> }>();

provide(TAB_CONTEXT, true);

const page = usePage();
const isAuthenticated = computed(() => !!(page.props as { auth?: { user?: unknown } })?.auth?.user);

const store = useTabStore();
const activeBreadcrumbs = computed(() => store.activeTab?.breadcrumbs ?? []);

// Sincroniza URL do browser com aba ativa (cosmético; replaceState não
// dispara nova requisição nem cria entry no histórico).
const syncUrl = () => {
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

    <AppShell v-if="isAuthenticated" variant="sidebar">
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

    <!-- Deslogado: sem chrome de app. Página (Login/Register) usa AuthLayout próprio. -->
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
</template>
