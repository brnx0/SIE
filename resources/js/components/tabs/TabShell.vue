<script setup lang="ts">
import { computed, provide, watch, type Component } from 'vue';
import { usePage } from '@inertiajs/vue3';
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
watch(
    () => store.activeTab?.url,
    (url) => {
        if (url && url !== window.location.pathname + window.location.search) {
            window.history.replaceState(window.history.state, '', url);
        }
    },
);

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
