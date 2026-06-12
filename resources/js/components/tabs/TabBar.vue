<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { useTabStore } from '@/stores/tabs';

const store = useTabStore();

const select = (id: string) => {
    store.markUserVisit();
    store.setActive(id);
    // Sincroniza estado interno do Inertia com a URL da aba ativa. Sem isso,
    // submits/validações erradas restauram a URL para a "página atual" interna
    // do Inertia, que pode ser de outra aba → flipa de aba após erro.
    const tab = store.tabs.find((t) => t.id === id);
    const here = window.location.pathname + window.location.search;
    if (tab && tab.url !== here) {
        router.visit(tab.url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }
};

const close = (id: string) => {
    const fallbackUrl = store.closeTab(id);
    if (store.tabs.length === 0) {
        router.visit('/dashboard');
    }
    void fallbackUrl;
};
</script>

<template>
    <div
        v-if="store.tabs.length"
        class="flex items-center gap-1 overflow-x-auto border-b border-sidebar-border/70 bg-sidebar/30 px-2 py-1"
    >
        <div
            v-for="tab in store.tabs"
            :key="tab.id"
            class="group flex shrink-0 cursor-pointer items-center gap-2 rounded-t-md border-b-2 px-3 py-1.5 text-sm transition-colors"
            :class="
                tab.id === store.activeId
                    ? 'border-fuchsia-500 bg-background font-medium text-foreground'
                    : 'border-transparent text-muted-foreground hover:bg-background/60 hover:text-foreground'
            "
            @click="select(tab.id)"
        >
            <span class="max-w-[180px] truncate">{{ tab.title }}</span>
            <button
                type="button"
                class="rounded p-0.5 opacity-60 transition hover:bg-muted hover:opacity-100"
                aria-label="Fechar aba"
                @click.stop="close(tab.id)"
            >
                <X class="size-3.5" />
            </button>
        </div>
    </div>
</template>
