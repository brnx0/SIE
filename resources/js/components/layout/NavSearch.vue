<script setup lang="ts">
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Search, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { useTabNav } from '@/composables/useTabNav';

export interface FlatNavLeaf {
    title: string;
    href: string;
    path: string[]; // breadcrumb dos pais, ex: ['Cadastros', 'Rede de Ensino']
}

const props = defineProps<{
    modelValue: string;
    items: FlatNavLeaf[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const query = computed({
    get: () => props.modelValue,
    set: (v: string) => emit('update:modelValue', v),
});

const norm = (s: string) =>
    s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

const results = computed(() => {
    const q = norm(query.value.trim());
    if (!q) return [];
    return props.items.filter((it) => {
        const hay = norm([...it.path, it.title].join(' '));
        return hay.includes(q);
    });
});

const clear = () => emit('update:modelValue', '');

const { open } = useTabNav();
</script>

<template>
    <SidebarGroup class="px-2 py-2">
        <!-- Input de busca -->
        <div
            class="flex h-9 items-center gap-2 rounded-md border border-sidebar-border bg-sidebar-accent/30 px-2 text-sm shadow-sm transition focus-within:ring-1 focus-within:ring-sidebar-ring"
        >
            <Search class="size-4 shrink-0 text-muted-foreground" />
            <input
                v-model="query"
                type="text"
                placeholder="Buscar no menu..."
                class="h-9 w-full bg-transparent text-sm placeholder:text-muted-foreground focus:outline-none"
            />
            <button
                v-if="query"
                type="button"
                class="shrink-0 rounded p-0.5 hover:bg-muted"
                aria-label="Limpar busca"
                @click="clear"
            >
                <X class="size-3.5" />
            </button>
        </div>

        <!-- Resultados -->
        <SidebarMenu v-if="query.trim()" class="mt-2">
            <li
                v-if="results.length === 0"
                class="px-3 py-2 text-xs text-muted-foreground"
            >
                Nenhuma tela encontrada.
            </li>
            <SidebarMenuItem v-for="r in results" :key="r.href">
                <SidebarMenuButton as-child>
                    <a :href="r.href" class="flex flex-col items-start gap-0.5 py-1.5" @click.prevent="open(r.href)">
                        <span class="text-sm">{{ r.title }}</span>
                        <span
                            v-if="r.path.length"
                            class="text-[10px] uppercase tracking-wide text-muted-foreground"
                        >
                            {{ r.path.join(' › ') }}
                        </span>
                    </a>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
