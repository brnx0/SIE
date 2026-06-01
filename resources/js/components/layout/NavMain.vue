<script setup lang="ts">
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { router } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { useTabStore } from '@/stores/tabs';
import { pathOf } from '@/lib/tabRegistry';

interface NavLeaf {
    title: string;
    href: string;
}

interface NavGroup {
    title: string;
    children: NavLeaf[];
}

type NavChild = NavLeaf | NavGroup;

interface NavItem {
    title: string;
    href?: string;
    icon: Component;
    children?: NavChild[];
}

const isGroup = (child: NavChild): child is NavGroup => 'children' in child;

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const store = useTabStore();

const activePath = computed(() => store.activeTab?.path ?? '');

const isActive = (href?: string) => !!href && activePath.value === pathOf(href);

// Se a tela já está aberta numa aba, só ativa (preserva estado). Senão, navega
// (TabRegistrar cria a nova aba a partir da resposta do Inertia).
const open = (href?: string) => {
    if (!href) return;
    const existing = store.findByPath(pathOf(href));
    if (existing) store.setActive(existing.id);
    else router.visit(href);
};

const isChildActive = (child: NavChild): boolean =>
    isGroup(child)
        ? child.children.some((c) => isActive(c.href))
        : isActive(child.href);

const isGroupActive = (item: NavItem) =>
    item.children?.some((c) => isChildActive(c)) ?? false;
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel v-if="label">{{ label }}</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <!-- Item simples -->
                <SidebarMenuItem v-if="!item.children">
                    <SidebarMenuButton as-child :is-active="isActive(item.href)">
                        <a :href="item.href!" @click.prevent="open(item.href)">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>

                <!-- Item com submenu -->
                <Collapsible
                    v-else
                    :default-open="isGroupActive(item)"
                    as-child
                    class="group/collapsible"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton :is-active="isGroupActive(item)">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronRight class="ml-auto size-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <template v-for="child in item.children" :key="child.title">
                                    <!-- Leaf -->
                                    <SidebarMenuSubItem v-if="!isGroup(child)">
                                        <SidebarMenuSubButton as-child :is-active="isActive(child.href)">
                                            <a :href="child.href" @click.prevent="open(child.href)">
                                                <span>{{ child.title }}</span>
                                            </a>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>

                                    <!-- Group (2nd level) -->
                                    <Collapsible
                                        v-else
                                        :default-open="isChildActive(child)"
                                        as-child
                                        class="group/subcollapsible"
                                    >
                                        <SidebarMenuSubItem>
                                            <CollapsibleTrigger as-child>
                                                <SidebarMenuSubButton :is-active="isChildActive(child)" class="font-medium">
                                                    <span>{{ child.title }}</span>
                                                    <ChevronRight class="ml-auto size-3.5 transition-transform duration-200 group-data-[state=open]/subcollapsible:rotate-90" />
                                                </SidebarMenuSubButton>
                                            </CollapsibleTrigger>
                                            <CollapsibleContent>
                                                <SidebarMenuSub class="ml-2 border-l pl-2">
                                                    <SidebarMenuSubItem v-for="leaf in child.children" :key="leaf.title">
                                                        <SidebarMenuSubButton as-child :is-active="isActive(leaf.href)">
                                                            <a :href="leaf.href" @click.prevent="open(leaf.href)">
                                                                <span>{{ leaf.title }}</span>
                                                            </a>
                                                        </SidebarMenuSubButton>
                                                    </SidebarMenuSubItem>
                                                </SidebarMenuSub>
                                            </CollapsibleContent>
                                        </SidebarMenuSubItem>
                                    </Collapsible>
                                </template>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
