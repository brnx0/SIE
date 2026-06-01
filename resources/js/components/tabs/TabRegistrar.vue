<script setup lang="ts">
// Renderizado (invisível) dentro do App do Inertia. Não renderiza a página —
// só espelha a navegação atual do Inertia para a tab store. A renderização real
// das páginas acontece nos painéis do TabShell (v-show), preservando estado.
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useTabStore } from '@/stores/tabs';
import { pageCache } from '@/lib/tabRegistry';

const page = usePage();
const store = useTabStore();

const sync = () => {
    const name = page.component;
    const component = pageCache.get(name);
    if (!component) return;
    store.openTab({
        url: page.url,
        component,
        props: { ...page.props },
    });
};

watch(() => [page.component, page.url], sync, { immediate: true });
</script>

<template></template>
