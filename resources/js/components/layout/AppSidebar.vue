<script setup lang="ts">
import NavFooter from '@/components/layout/NavFooter.vue';
import NavMain from '@/components/layout/NavMain.vue';
import NavUser from '@/components/layout/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import { LayoutDashboard, LifeBuoy, UserPlus, Cog} from 'lucide-vue-next';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const overview = [
    { title: 'Painel', href: '/dashboard', icon: LayoutDashboard },
];

const cadastros = [
    {
        title: 'Cadastros',
        icon: UserPlus,
        children: [
            { title: 'Alunos', href: '/alunos' },
            { title: 'Funcionários', href: '/funcionarios' },
            {
                title: 'Rede de Ensino',
                children: [
                    { title: 'Segmentos', href: '/segmentos' },
                    { title: 'Séries', href: '/series' },
                    { title: 'Disciplinas', href: '/disciplinas' },
                    { title: 'Escolas', href: '/escolas' },
                    { title: 'Turmas', href: '/turmas' },
                ],
            },
        ],
    },
];
const administracao = computed<any[]>(() => [
    {
        title: 'Sistema',
        icon: Cog,
        children: [
            { title: 'Usuários', href: '/users' },
            ...(isAdmin.value ? [{ title: 'Parâmetros', href: '/parametros' }] : []),
        ],
    },
]);

const footerNavItems = [
    { title: 'Suporte', href: '#', icon: LifeBuoy },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="Visão geral" :items="overview" />
            <NavMain label="Gestão" :items="cadastros" />
            <NavMain label="Administração" :items="administracao" />
        </SidebarContent>
        

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
