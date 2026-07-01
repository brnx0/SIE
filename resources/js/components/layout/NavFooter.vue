<script setup lang="ts">
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { useToast } from '@/composables/useToast';

interface Props {
    items: NavItem[];
    class?: string;
}

defineProps<Props>();

const { push } = useToast();

// Item sem destino real (href '#') ainda não tem tela — avisa em vez de abrir aba vazia.
const isPlaceholder = (href?: string) => !href || href === '#';
const avisar = () => push('success', 'Funcionalidade ainda em desenvolvimento.');
</script>

<template>
    <SidebarGroup :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100" as-child>
                        <button v-if="isPlaceholder(item.href)" type="button" class="w-full" @click="avisar">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </button>
                        <a v-else :href="item.href" target="_blank" rel="noopener noreferrer">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
