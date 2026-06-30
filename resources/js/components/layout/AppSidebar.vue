<script setup lang="ts">
import NavFooter from '@/components/layout/NavFooter.vue';
import NavMain from '@/components/layout/NavMain.vue';
import NavSearch, { type FlatNavLeaf } from '@/components/layout/NavSearch.vue';
import NavUser from '@/components/layout/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import { LayoutDashboard, LifeBuoy, UserPlus, Cog, ClipboardList, FileBarChart, BookOpen, ClipboardCheck, KeyRound, Archive, ArrowRightLeft } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { usePermissao } from '@/composables/usePermissao';
import AppLogo from './AppLogo.vue';

const { podeVer } = usePermissao();

// Filtra recursivamente a árvore de menu pela matriz de permissão:
// folha (com href) aparece se podeVer(href); pai aparece se sobrar ≥1 filho.
function filtrarMenu(items: any[]): any[] {
    const out: any[] = [];
    for (const it of items ?? []) {
        if (it.children?.length) {
            const kids = filtrarMenu(it.children);
            if (kids.length) out.push({ ...it, children: kids });
        } else if (it.href) {
            if (podeVer(it.href)) out.push(it);
        } else {
            out.push(it);
        }
    }
    return out;
}

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
            { title: '2ª Via Comprovante', href: '/matriculas/segunda-via' },
        ],
    },
    {
        title: 'Movimentações',
        icon: ArrowRightLeft,
        children: [
            { title: 'Movimentar Aluno', href: '/movimentacoes' },
            { title: 'Remanejar Turmas', href: '/remanejamento-turmas' },
        ],
    },
];
const diarioMenu = [
    {
        title: 'Diário Online',
        icon: BookOpen,
        children: [
            { title: 'Diário de Classe', href: '/diario' },
            { title: 'Diário AEE', href: '/diario-aee' },
            { title: 'Diário Atividade', href: '/diario-atividade' },
            {
                title: 'Cadastro',
                children: [
                    { title: 'Instrumentos Avaliativos', href: '/diario/instrumentos-avaliativos' },
                    { title: 'Sábados Letivos', href: '/sabados-letivos' },
                ],
            },
            { title: 'Planos de Aula', href: '/diario/planos' },
            { title: 'Planos de Aula AEE', href: '/diario/planos-aee' },
            { title: 'Relatórios', href: '/relatorios-diario' },
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
const coordenadorMenu = [
    {
        title: 'Pedagógico',
        icon: ClipboardCheck,
        children: [
            { title: 'Validação de Planos', href: '/coordenador/planos' },
            { title: 'Validação de Planos AEE', href: '/coordenador-interno/planos-aee' },
            { title: 'Relatórios', href: '/relatorios-pedagogico' },
        ],
    },
];
const secretariaMenu = [
    {
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
            { title: 'Renovação de Matrícula', href: '/secretaria/renovacao-matricula' },
            { title: 'Motivo Baixa Frequência', href: '/secretaria/motivos-baixa-frequencia' },
        ],
    },
];
const encerramentoMenu = [
    {
        title: 'Ano Letivo',
        icon: Archive,
        children: [
            { title: 'Encerramento de Turmas', href: '/encerramento-turmas' },
            { title: 'Duplicar Turmas', href: '/duplicar-turmas' },
            { title: 'Confirmação de Renovação', href: '/confirmacao-renovacao' },
            { title: 'Relatórios', href: '/relatorios-encerramento' },
        ],
    },
];
const administracao = [
    {
        title: 'Sistema',
        icon: Cog,
        children: [
            { title: 'Usuários', href: '/users' },
            { title: 'Parâmetros', href: '/parametros' },
        ],
    },
];

// Versões filtradas pela matriz de permissão (esconde nível 0).
const overviewF = computed(() => filtrarMenu(overview));
const cadastrosF = computed(() => filtrarMenu(cadastros));
const matriculasF = computed(() => filtrarMenu(matriculasMenu));
const diarioF = computed(() => filtrarMenu(diarioMenu));
const coordenadorF = computed(() => filtrarMenu(coordenadorMenu));
const secretariaF = computed(() => filtrarMenu(secretariaMenu));
const encerramentoF = computed(() => filtrarMenu(encerramentoMenu));
const relatoriosF = computed(() => filtrarMenu(relatoriosMenu));
const administracaoF = computed(() => filtrarMenu(administracao));

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
    ...flattenItems(overviewF.value),
    ...flattenItems(cadastrosF.value),
    ...flattenItems(matriculasF.value),
    ...flattenItems(diarioF.value),
    ...flattenItems(coordenadorF.value),
    ...flattenItems(secretariaF.value),
    ...flattenItems(encerramentoF.value),
    ...flattenItems(relatoriosF.value),
    ...flattenItems(administracaoF.value),
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

        <SidebarContent class="menu-moderno">
            <NavSearch v-model="search" :items="flatLeaves" />

            <template v-if="!searching">
                <NavMain v-if="overviewF.length" label="Visão geral" :items="overviewF" />
                <NavMain v-if="cadastrosF.length" label="Gestão" :items="cadastrosF" />
                <NavMain v-if="matriculasF.length" label="Matrículas" :items="matriculasF" />
                <NavMain v-if="diarioF.length" label="Diário" :items="diarioF" />
                <NavMain v-if="coordenadorF.length" label="Pedagógico" :items="coordenadorF" />
                <NavMain v-if="secretariaF.length" label="Secretaria Escolar" :items="secretariaF" />
                <NavMain v-if="encerramentoF.length" label="Encerramento" :items="encerramentoF" />
                <NavMain v-if="relatoriosF.length" label="Relatórios" :items="relatoriosF" />
                <NavMain v-if="administracaoF.length" label="Administração" :items="administracaoF" />
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

/* ===== Modo retraído (ícone): rail compacto — ignora estilos do expandido ===== */
/* Rótulos de seção não aparecem no rail de ícones */
[data-collapsible="icon"] .menu-moderno [data-sidebar="group-label"] {
    display: none;
}
[data-collapsible="icon"] .menu-moderno [data-sidebar="menu-button"] {
    min-height: 0;
    gap: 0;
    margin: 2px auto;
    border-radius: 0.6rem;
    justify-content: center;
}
/* Texto do item some no rail de ícones (só o ícone) */
[data-collapsible="icon"] .menu-moderno [data-sidebar="menu-button"] > span {
    display: none;
}
[data-collapsible="icon"] .menu-moderno [data-sidebar="menu-button"] > svg:first-child {
    width: 1rem;
    height: 1rem;
    padding: 0;
    background: transparent;
    box-shadow: none;
    border-radius: 0;
    color: inherit;
}
[data-collapsible="icon"] .menu-moderno [data-sidebar="menu-button"][data-active="true"] > svg:first-child {
    background: transparent;
    box-shadow: none;
}
/* Esconde o rail lateral (::before) no modo ícone */
[data-collapsible="icon"] .menu-moderno [data-sidebar="menu-button"]::before {
    display: none;
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
