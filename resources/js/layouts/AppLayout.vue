<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { inject, watch } from 'vue';
import { TAB_CONTEXT, TAB_ID } from '@/lib/tabRegistry';
import { useTabStore } from '@/stores/tabs';
import type { BreadcrumbItemType } from '@/types';

const props = withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItemType[] }>(), {
    breadcrumbs: () => [],
});

const inTab = inject(TAB_CONTEXT, false);
const tabId = inject(TAB_ID, null);

// Dentro do shell de abas: a sidebar/header são globais (TabShell). Aqui só
// repassamos o conteúdo e publicamos breadcrumbs/título na aba dona do painel.
if (inTab && tabId) {
    const store = useTabStore();
    watch(
        () => props.breadcrumbs,
        (bc) => {
            // Title = recurso (primeira breadcrumb), não a página corrente.
            // Mantém nome da aba estável durante navegação interna (list↔edit).
            store.setMeta(tabId, {
                breadcrumbs: bc,
                title: bc?.[0]?.title,
            });
        },
        { immediate: true, deep: true },
    );
}
</script>

<template>
    <slot v-if="inTab" />
    <AppSidebarLayout v-else :breadcrumbs="breadcrumbs">
        <slot />
    </AppSidebarLayout>
</template>
