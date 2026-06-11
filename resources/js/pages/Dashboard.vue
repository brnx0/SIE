<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookMarked, ClipboardList, GraduationCap, School, UserPlus, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface DashStats {
    alunos_matriculados: number;
    funcionarios_ativos: number;
    turmas_andamento: number;
    escolas_ativas: number;
}

const props = defineProps<{ stats?: DashStats }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Painel', href: '/dashboard' }];

const page = usePage<SharedData>();
const userName = computed(() => page.props.auth.user?.name ?? 'Educador');

const stats = computed(() => [
    { label: 'Alunos matriculados', value: props.stats?.alunos_matriculados ?? '—', icon: GraduationCap, tone: 'sky' },
    { label: 'Funcionários ativos', value: props.stats?.funcionarios_ativos ?? '—', icon: School, tone: 'teal' },
    { label: 'Turmas em andamento', value: props.stats?.turmas_andamento ?? '—', icon: BookMarked, tone: 'amber' },
    { label: 'Escolas ativas', value: props.stats?.escolas_ativas ?? '—', icon: Users, tone: 'rose' },
]);

const toneClass: Record<string, string> = {
    sky: 'bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-300',
    teal: 'bg-teal-50 text-teal-600 dark:bg-teal-900/30 dark:text-teal-300',
    amber: 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-300',
    rose: 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300',
};

const shortcuts = [
    { title: 'Cadastrar aluno', desc: 'Registre um novo aluno', href: '/alunos/create', icon: GraduationCap },
    { title: 'Cadastrar funcionário', desc: 'Professores e equipe pedagógica', href: '/funcionarios/create', icon: UserPlus },
    { title: 'Criar turma', desc: 'Defina disciplinas, horários e professores', href: '/turmas/create', icon: BookMarked },
    { title: 'Realizar matrícula', desc: 'Matricule alunos nas turmas', href: '/matriculas', icon: ClipboardList },
];
</script>

<template>
    <Head title="Painel" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Olá, bem-vindo(a) de volta</p>
                        <h1 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-50">{{ userName }}</h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Aqui está um panorama da sua instituição.</p>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-lg bg-sky-50 px-3 py-1.5 text-sm text-sky-700 ring-1 ring-sky-100 dark:bg-sky-900/30 dark:text-sky-200 dark:ring-sky-900/40">
                        <Users class="size-4" />
                        Visão geral
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="s in stats"
                    :key="s.label"
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <div :class="['grid size-10 place-items-center rounded-lg', toneClass[s.tone]]">
                            <component :is="s.icon" class="size-5" />
                        </div>
                        <span class="text-xs text-slate-400">Atual</span>
                    </div>
                    <p class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ s.value }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ s.label }}</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-50">Atalhos rápidos</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Acesse as principais áreas do sistema.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <Link
                            v-for="s in shortcuts"
                            :key="s.title"
                            :href="s.href"
                            class="group flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50/50 p-4 transition hover:border-sky-300 hover:bg-sky-50/60 dark:border-slate-800 dark:bg-slate-900/40 dark:hover:border-sky-700 dark:hover:bg-sky-950/30"
                        >
                            <div class="grid size-10 place-items-center rounded-lg bg-white text-sky-600 ring-1 ring-slate-200 transition group-hover:bg-sky-600 group-hover:text-white group-hover:ring-sky-600 dark:bg-slate-800 dark:text-sky-300 dark:ring-slate-700">
                                <component :is="s.icon" class="size-5" />
                            </div>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-50">{{ s.title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ s.desc }}</p>
                            </div>
                        </Link>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-50">Próximos passos</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Configure sua instituição em poucos cliques.</p>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 grid size-6 place-items-center rounded-full bg-sky-100 text-xs font-semibold text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">1</span>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-50">Cadastre os funcionários</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Adicione professores e administradores.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 grid size-6 place-items-center rounded-full bg-teal-100 text-xs font-semibold text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">2</span>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-50">Cadastre os alunos</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Registre matrículas e dados dos estudantes.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 grid size-6 place-items-center rounded-full bg-amber-100 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">3</span>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-50">Crie as turmas</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Defina disciplinas e horários.</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
