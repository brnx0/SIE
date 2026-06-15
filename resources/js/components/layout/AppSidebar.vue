<script setup lang="ts">
import NavFooter from '@/components/layout/NavFooter.vue';
import NavMain from '@/components/layout/NavMain.vue';
import NavSearch, { type FlatNavLeaf } from '@/components/layout/NavSearch.vue';
import NavUser from '@/components/layout/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import { LayoutDashboard, LifeBuoy, UserPlus, Cog, ClipboardList, FileBarChart, BookOpen, ClipboardCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const userRoles = computed(() => page.props.auth?.user?.roles ?? []);
const isAdmin = computed(() => userRoles.value.includes('admin'));
const isCoordenador = computed(() => userRoles.value.includes('coordenador') || isAdmin.value);
const isCoordenadorInterno = computed(() => userRoles.value.includes('coordenador_interno') || isAdmin.value);

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
                    { title: 'Grade Disciplinar', href: '/grade-disciplinar' },
                    { title: 'Escolas', href: '/escolas' },
                    { title: 'Turmas', href: '/turmas' },
                    { title: 'Turmas AEE', href: '/turmas-aee' },
                    { title: 'Turmas Atividades', href: '/turmas-atividade' },
                    { title: 'Atendimentos AEE', href: '/atendimentos-aee' },
                    { title: 'Atividades', href: '/atividades' },
                ],
            },
        ],
    },
];
const matriculasMenu = [
    {
        title: 'Matrículas',
        icon: ClipboardList,
        children: [
            { title: 'Matrículas', href: '/matriculas' },
            { title: 'Matrículas AEE', href: '/matriculas-aee' },
            { title: 'Movimentações', href: '/movimentacoes' },
            { title: '2ª Via Comprovante', href: '/matriculas/segunda-via' },
        ],
    },
];
const diarioMenu = [
    {
        title: 'Diário Online',
        icon: BookOpen,
        children: [
            {
                title: 'Cadastro',
                children: [
                    { title: 'Instrumentos Avaliativos', href: '/diario/instrumentos-avaliativos' },
                ],
            },
            { title: 'Planos de Aula', href: '/diario/planos' },
            { title: 'Planos de Aula AEE', href: '/diario/planos-aee' },
        ],
    },
];
const relatoriosMenu = [
    {
        title: 'Relatórios',
        icon: FileBarChart,
        children: [
            { title: 'Central de Relatórios', href: '/relatorios' },
            { title: 'Relatórios Gerais', href: '/relatorios-escola' },
        ],
    },
];
const coordenadorMenu = computed<any[]>(() => {
    if (!isCoordenador.value && !isCoordenadorInterno.value) return [];

    const children: { title: string; href: string }[] = [];
    if (isCoordenador.value) children.push({ title: 'Validação de Planos', href: '/coordenador/planos' });
    if (isCoordenadorInterno.value) children.push({ title: 'Validação de Planos AEE', href: '/coordenador-interno/planos-aee' });

    return [{ title: 'Pedagógico', icon: ClipboardCheck, children }];
});

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

// Busca no menu
const search = ref('');

// Flatten recursivo: extrai todos os leafs com seu path (breadcrumb de pais)
const flattenItems = (items: any[], parents: string[] = []): FlatNavLeaf[] => {
    const out: FlatNavLeaf[] = [];
    for (const it of items ?? []) {
        if (it.children?.length) {
            out.push(...flattenItems(it.children, [...parents, it.title]));
        } else if (it.href) {
            out.push({ title: it.title, href: it.href, path: parents });
        }
    }
    return out;
};

const flatLeaves = computed<FlatNavLeaf[]>(() => [
    ...flattenItems(overview),
    ...flattenItems(cadastros),
    ...flattenItems(matriculasMenu),
    ...flattenItems(diarioMenu),
    ...flattenItems(coordenadorMenu.value),
    ...flattenItems(relatoriosMenu),
    ...flattenItems(administracao.value),
]);

const searching = computed(() => search.value.trim().length > 0);
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
            <NavSearch v-model="search" :items="flatLeaves" />

            <template v-if="!searching">
                <NavMain label="Visão geral" :items="overview" />
                <NavMain label="Gestão" :items="cadastros" />
                <NavMain label="Matrículas" :items="matriculasMenu" />
                <NavMain label="Diário" :items="diarioMenu" />
                <NavMain v-if="isCoordenador || isCoordenadorInterno" label="Pedagógico" :items="coordenadorMenu" />
                <NavMain label="Relatórios" :items="relatoriosMenu" />
                <NavMain label="Administração" :items="administracao" />
            </template>
        </SidebarContent>


        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
