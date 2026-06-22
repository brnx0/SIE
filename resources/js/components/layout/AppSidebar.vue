<script setup lang="ts">
import NavFooter from '@/components/layout/NavFooter.vue';
import NavMain from '@/components/layout/NavMain.vue';
import NavSearch, { type FlatNavLeaf } from '@/components/layout/NavSearch.vue';
import NavUser from '@/components/layout/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import { LayoutDashboard, LifeBuoy, UserPlus, Cog, ClipboardList, FileBarChart, BookOpen, ClipboardCheck, KeyRound, Archive } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const userRoles = computed(() => page.props.auth?.user?.roles ?? []);

// Estilo do menu: 'moderno' (novo) ou 'classico' (anterior). Persistido — reversível.
const menuModerno = ref(true);
onMounted(() => {
    const v = localStorage.getItem('sie_menu_estilo');
    if (v) menuModerno.value = v === 'moderno';
});
const setEstilo = (moderno: boolean) => {
    menuModerno.value = moderno;
    localStorage.setItem('sie_menu_estilo', moderno ? 'moderno' : 'classico');
};
const isAdmin = computed(() => userRoles.value.includes('admin'));
const isCoordenador = computed(() => userRoles.value.includes('coordenador') || isAdmin.value);
const isCoordenadorInterno = computed(() => userRoles.value.includes('coordenador_interno') || isAdmin.value);
const isProfessor = computed(() => userRoles.value.includes('professor') || isAdmin.value);
const isProfessorAee = computed(() => userRoles.value.includes('professor_aee') || isAdmin.value);
const isSecretariaEscolar = computed(() => userRoles.value.includes('secretaria_escola') || isAdmin.value);
// Secretaria (sem outro perfil de diário): no Diário Online só enxerga Relatórios.
const apenasSecretaria = computed(() =>
    userRoles.value.includes('secretaria_escola')
    && !isAdmin.value
    && !userRoles.value.includes('professor')
    && !userRoles.value.includes('coordenador')
    && !userRoles.value.includes('coordenador_interno'),
);

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
const diarioMenu = computed<any[]>(() => [
    {
        title: 'Diário Online',
        icon: BookOpen,
        children: [
            ...(isProfessor.value ? [{ title: 'Diário de Classe', href: '/diario' }] : []),
            ...(isProfessorAee.value ? [{ title: 'Diário AEE', href: '/diario-aee' }] : []),
            ...(isProfessor.value ? [{ title: 'Diário Atividade', href: '/diario-atividade' }] : []),
            ...(!apenasSecretaria.value
                ? [
                      {
                          title: 'Cadastro',
                          children: [
                              { title: 'Instrumentos Avaliativos', href: '/diario/instrumentos-avaliativos' },
                              ...(isAdmin.value ? [{ title: 'Sábados Letivos', href: '/sabados-letivos' }] : []),
                          ],
                      },
                      { title: 'Planos de Aula', href: '/diario/planos' },
                      { title: 'Planos de Aula AEE', href: '/diario/planos-aee' },
                  ]
                : []),
            { title: 'Relatórios', href: '/relatorios-diario' },
        ],
    },
]);
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

    const children: any[] = [];
    if (isCoordenador.value) children.push({ title: 'Validação de Planos', href: '/coordenador/planos' });
    if (isCoordenadorInterno.value) children.push({ title: 'Validação de Planos AEE', href: '/coordenador-interno/planos-aee' });
    if (isCoordenador.value) children.push({ title: 'Relatórios', href: '/relatorios-pedagogico' });

    return [{ title: 'Pedagógico', icon: ClipboardCheck, children }];
});

const secretariaMenu = computed<any[]>(() => {
    if (!isSecretariaEscolar.value) return [];

    return [{
        title: 'Secretaria Escolar',
        icon: KeyRound,
        children: [
            { title: 'Acessos de Professores', href: '/secretaria/acessos-professores' },
            {
                title: 'Lançamentos',
                children: [
                    { title: 'Notas e Faltas', href: '/secretaria/lancamento-manual' },
                    { title: 'Justificativa de Falta', href: '/secretaria/justificativas-falta' },
                ],
            },
            { title: 'Motivo Baixa Frequência', href: '/secretaria/motivos-baixa-frequencia' },
        ],
    }];
});

const encerramentoMenu = computed<any[]>(() => {
    if (!isSecretariaEscolar.value) return [];

    return [{
        title: 'Ano Letivo',
        icon: Archive,
        children: [
            { title: 'Encerramento de Turmas', href: '/encerramento-turmas' },
        ],
    }];
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
    ...flattenItems(diarioMenu.value),
    ...flattenItems(coordenadorMenu.value),
    ...flattenItems(secretariaMenu.value),
    ...flattenItems(encerramentoMenu.value),
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

        <SidebarContent :class="menuModerno ? 'menu-moderno' : ''">
            <!-- Alternador de estilo do menu -->
            <div class="px-2 pt-1 group-data-[collapsible=icon]:hidden">
                <div class="flex rounded-lg border bg-muted/50 p-0.5 text-[11px] font-medium">
                    <button
                        type="button"
                        :class="['flex-1 rounded-md px-2 py-1 transition', menuModerno ? 'bg-gradient-to-r from-indigo-600 to-indigo-800 text-white shadow-sm' : 'text-muted-foreground hover:text-foreground']"
                        @click="setEstilo(true)"
                    >Moderno</button>
                    <button
                        type="button"
                        :class="['flex-1 rounded-md px-2 py-1 transition', !menuModerno ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground']"
                        @click="setEstilo(false)"
                    >Clássico</button>
                </div>
            </div>

            <NavSearch v-model="search" :items="flatLeaves" />

            <template v-if="!searching">
                <NavMain label="Visão geral" :items="overview" />
                <NavMain label="Gestão" :items="cadastros" />
                <NavMain label="Matrículas" :items="matriculasMenu" />
                <NavMain label="Diário" :items="diarioMenu" />
                <NavMain v-if="isCoordenador || isCoordenadorInterno" label="Pedagógico" :items="coordenadorMenu" />
                <NavMain v-if="isSecretariaEscolar" label="Secretaria Escolar" :items="secretariaMenu" />
                <NavMain v-if="isSecretariaEscolar" label="Encerramento" :items="encerramentoMenu" />
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

<style>
/* ============================================================
   Menu moderno (alternável) — "slate rail"
   Paleta neutra (slate/grafite). Tudo escopado em .menu-moderno.
   ============================================================ */

/* Brilho ambiente sutil no topo */
.menu-moderno {
    position: relative;
}
.menu-moderno::before {
    content: '';
    position: absolute;
    inset: 0 0 auto 0;
    height: 220px;
    pointer-events: none;
    background:
        radial-gradient(120% 60% at 15% 0%, rgba(100, 116, 139, 0.12), transparent 60%),
        radial-gradient(120% 60% at 90% 0%, rgba(71, 85, 105, 0.1), transparent 60%);
    z-index: 0;
}
.menu-moderno > * {
    position: relative;
    z-index: 1;
}

.menu-moderno [data-sidebar="group"] {
    padding-top: 0.1rem;
    padding-bottom: 0.1rem;
}

/* Rótulos de seção — neutro com linha esmaecida */
.menu-moderno [data-sidebar="group-label"] {
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-size: 10px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgb(100 116 139);
}
.menu-moderno [data-sidebar="group-label"]::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, rgba(100, 116, 139, 0.3), transparent);
}

/* Botão de item */
.menu-moderno [data-sidebar="menu-button"] {
    position: relative;
    min-height: 2.6rem;
    gap: 0.7rem;
    margin: 1px 0;
    border-radius: 0.8rem;
    font-weight: 500;
    overflow: hidden;
    transition: color 0.2s ease, background 0.2s ease, box-shadow 0.25s ease,
        transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

/* Rail lateral animado (entra no hover) */
.menu-moderno [data-sidebar="menu-button"]::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    height: 0;
    width: 3px;
    border-radius: 0 4px 4px 0;
    background: linear-gradient(180deg, #4f46e5, #818cf8);
    transform: translateY(-50%);
    transition: height 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.menu-moderno [data-sidebar="menu-button"]:hover::before {
    height: 45%;
}

/* Ícone em chip com anel */
.menu-moderno [data-sidebar="menu-button"] > svg:first-child {
    box-sizing: border-box;
    width: 1.7rem;
    height: 1.7rem;
    padding: 0.34rem;
    border-radius: 0.6rem;
    background: rgba(100, 116, 139, 0.12);
    color: #475569;
    box-shadow: inset 0 0 0 1px rgba(100, 116, 139, 0.2);
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
}
.dark .menu-moderno [data-sidebar="menu-button"] > svg:first-child {
    background: rgba(148, 163, 184, 0.16);
    color: #cbd5e1;
    box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.22);
}

/* Hover */
.menu-moderno [data-sidebar="menu-button"]:hover {
    background: rgba(79, 70, 229, 0.08);
    transform: translateX(3px);
}
.menu-moderno [data-sidebar="menu-button"]:hover > svg:first-child {
    transform: scale(1.08) rotate(-3deg);
    background: rgba(79, 70, 229, 0.16);
    color: #4f46e5;
}
.dark .menu-moderno [data-sidebar="menu-button"]:hover > svg:first-child {
    color: #a5b4fc;
}

/* Item ATIVO — pílula azul (indigo principal) com shimmer + glow */
.menu-moderno [data-sidebar="menu-button"][data-active="true"],
.menu-moderno [data-sidebar="menu-button"][data-active="true"]:hover {
    color: #fff;
    transform: none;
    background-image: linear-gradient(110deg, #4f46e5 0%, #4338ca 55%, #3730a3 100%);
    background-size: 200% 100%;
    box-shadow: 0 12px 26px -10px rgba(79, 70, 229, 0.65), inset 0 1px 0 rgba(255, 255, 255, 0.18);
    animation: auroraShift 6s ease infinite;
}
.menu-moderno [data-sidebar="menu-button"][data-active="true"]::before {
    height: 0;
}
/* brilho passando */
.menu-moderno [data-sidebar="menu-button"][data-active="true"]::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(100deg, transparent 30%, rgba(255, 255, 255, 0.16) 50%, transparent 70%);
    transform: translateX(-120%);
    animation: shimmer 3.6s ease-in-out infinite;
}
.menu-moderno [data-sidebar="menu-button"][data-active="true"] > svg:first-child {
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.28);
}
.menu-moderno [data-sidebar="menu-button"][data-active="true"] > svg:last-child {
    color: rgba(255, 255, 255, 0.85);
}

@keyframes auroraShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}
@keyframes shimmer {
    0% { transform: translateX(-120%); }
    60%, 100% { transform: translateX(120%); }
}

/* Submenu — guia vertical neutra */
.menu-moderno [data-sidebar="menu-sub"] {
    border: 0;
    margin-left: 0.95rem;
    padding-left: 0.8rem;
    background:
        linear-gradient(180deg, #4f46e5, #818cf8) left / 2px 100% no-repeat;
    border-radius: 2px;
}

/* Subitens */
.menu-moderno [data-sidebar="menu-sub-button"] {
    position: relative;
    border-radius: 0.55rem;
    transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
}
.menu-moderno [data-sidebar="menu-sub-button"]:hover {
    background: rgba(79, 70, 229, 0.08);
    transform: translateX(2px);
}
.menu-moderno [data-sidebar="menu-sub-button"][data-active="true"] {
    background-image: linear-gradient(90deg, rgba(79, 70, 229, 0.16), transparent);
    color: #4338ca;
    font-weight: 600;
}
.menu-moderno [data-sidebar="menu-sub-button"][data-active="true"]::before {
    content: '';
    position: absolute;
    left: -0.82rem;
    top: 50%;
    transform: translateY(-50%);
    width: 7px;
    height: 7px;
    border-radius: 9999px;
    background: linear-gradient(135deg, #4f46e5, #818cf8);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.16);
    animation: dotPulse 2.6s ease-in-out infinite;
}
.dark .menu-moderno [data-sidebar="menu-sub-button"][data-active="true"] {
    color: #c7d2fe;
}
@keyframes dotPulse {
    0%, 100% { box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.16); }
    50% { box-shadow: 0 0 0 5px rgba(79, 70, 229, 0.06); }
}

@media (prefers-reduced-motion: reduce) {
    .menu-moderno [data-sidebar="menu-button"][data-active="true"],
    .menu-moderno [data-sidebar="menu-button"][data-active="true"]::after,
    .menu-moderno [data-sidebar="menu-sub-button"][data-active="true"]::before {
        animation: none;
    }
}
</style>
