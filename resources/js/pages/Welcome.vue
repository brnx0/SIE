<script setup lang="ts">
import AppLogoIcon from '@/components/layout/AppLogoIcon.vue';
import AppearanceTabs from '@/components/common/AppearanceTabs.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BookMarked, GraduationCap, NotebookPen, ShieldCheck, Users } from 'lucide-vue-next';
</script>

<template>
    <Head title="SIE Matrícula" />
    <div class="min-h-svh bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <header class="mx-auto flex max-w-6xl items-center justify-between p-6">
            <Link :href="route('home')" class="flex items-center gap-2">
                <div class="grid size-9 place-items-center rounded-lg bg-sky-600 text-white">
                    <AppLogoIcon class="size-5" />
                </div>
                <div class="leading-tight">
                    <span class="block text-sm font-semibold">SIE Matrícula</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">Gestão Educacional</span>
                </div>
            </Link>

            <div class="flex items-center gap-3">
                <AppearanceTabs class="hidden sm:inline-flex" />
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="rounded-md bg-slate-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                >
                    Painel
                </Link>
                <template v-else>
                    <Link :href="route('login')" class="text-sm font-medium hover:text-sky-700 dark:hover:text-sky-300">Entrar</Link>
                    <Link
                        :href="route('register')"
                        class="rounded-md bg-sky-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
                    >
                        Criar conta
                    </Link>
                </template>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-10 lg:py-16">
            <section class="grid gap-12 lg:grid-cols-2 lg:items-center">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-300">
                        Educação simples e conectada
                    </span>
                    <h1 class="mt-4 text-4xl font-semibold tracking-tight sm:text-5xl">
                        Toda a sua instituição em um <span class="text-sky-600 dark:text-sky-400">único lugar</span>.
                    </h1>
                    <p class="mt-4 text-lg text-slate-600 dark:text-slate-300">
                        Matrículas, turmas, notas e funcionários integrados em uma plataforma leve e clara,
                        feita para escolas que valorizam o aprendizado.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            :href="$page.props.auth.user ? route('dashboard') : route('register')"
                            class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
                        >
                            Começar agora <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Já tenho conta
                        </Link>
                    </div>

                    <div class="mt-8 flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                        <div class="flex items-center gap-1.5"><ShieldCheck class="size-4 text-emerald-600" /> Dados protegidos</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="grid size-10 place-items-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-300"><GraduationCap class="size-5" /></div>
                        <p class="mt-3 font-semibold">Alunos</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Matrícula e histórico em um só lugar.</p>
                    </div>
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="grid size-10 place-items-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-900/30 dark:text-teal-300"><Users class="size-5" /></div>
                        <p class="mt-3 font-semibold">Funcionários</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Professores e equipe pedagógica.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="grid size-10 place-items-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-300"><BookMarked class="size-5" /></div>
                        <p class="mt-3 font-semibold">Turmas</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Disciplinas, horários e calendário.</p>
                    </div>
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="grid size-10 place-items-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300"><NotebookPen class="size-5" /></div>
                        <p class="mt-3 font-semibold">Notas</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Avaliações, médias e boletins.</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="mx-auto max-w-6xl px-6 pb-8 text-center text-xs text-slate-500 dark:text-slate-400">
            © {{ new Date().getFullYear() }} SIE Matrícula — Sistema Integrado de Educação.
        </footer>
    </div>
</template>
