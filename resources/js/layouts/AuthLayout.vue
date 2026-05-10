<script setup lang="ts">
import AppLogoIcon from '@/components/layout/AppLogoIcon.vue';
import AppearanceTabs from '@/components/common/AppearanceTabs.vue';
import { Link } from '@inertiajs/vue3';
import { BookOpen, GraduationCap, NotebookPen, Quote, Users } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-svh bg-slate-50 dark:bg-slate-950 lg:grid-cols-2">
        <!-- Painel lateral leve -->
        <div class="relative hidden overflow-hidden bg-white text-slate-700 ring-1 ring-slate-200/60 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-800 lg:flex lg:flex-col lg:justify-between">
            <div
                class="absolute inset-0 opacity-60 dark:opacity-40 [background-image:radial-gradient(circle_at_top_left,#e0f2fe,transparent_55%),radial-gradient(circle_at_bottom_right,#ccfbf1,transparent_55%)]"
            />

            <div class="relative z-10 p-10">
                <Link :href="route('home')" class="inline-flex items-center gap-3">
                    <div class="grid size-11 place-items-center rounded-xl bg-sky-600 text-white shadow-sm dark:bg-sky-500">
                        <AppLogoIcon class="size-6" />
                    </div>
                    <div class="leading-tight">
                        <span class="block text-lg font-semibold text-slate-900 dark:text-slate-50">SIE Matrícula</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Sistema Integrado de Educação</span>
                    </div>
                </Link>
            </div>

            <div class="relative z-10 px-10">
                <div class="grid max-w-md grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-5 backdrop-blur transition hover:border-sky-200 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900/70 dark:hover:border-sky-800">
                        <GraduationCap class="size-6 text-sky-600 dark:text-sky-400" />
                        <p class="mt-3 text-sm font-medium text-slate-900 dark:text-slate-100">Alunos</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Matrículas e jornada acadêmica</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-5 backdrop-blur transition hover:border-teal-200 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900/70 dark:hover:border-teal-800">
                        <Users class="size-6 text-teal-600 dark:text-teal-400" />
                        <p class="mt-3 text-sm font-medium text-slate-900 dark:text-slate-100">Funcionários</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Professores e equipe</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-5 backdrop-blur transition hover:border-amber-200 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900/70 dark:hover:border-amber-800">
                        <BookOpen class="size-6 text-amber-600 dark:text-amber-400" />
                        <p class="mt-3 text-sm font-medium text-slate-900 dark:text-slate-100">Turmas</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Disciplinas e horários</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-5 backdrop-blur transition hover:border-rose-200 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900/70 dark:hover:border-rose-800">
                        <NotebookPen class="size-6 text-rose-500 dark:text-rose-400" />
                        <p class="mt-3 text-sm font-medium text-slate-900 dark:text-slate-100">Notas</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Avaliações e desempenho</p>
                    </div>
                </div>
            </div>

            <div class="relative z-10 p-10">
                <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white/70 p-5 backdrop-blur dark:border-slate-800 dark:bg-slate-900/70">
                    <Quote class="mt-0.5 size-5 shrink-0 text-sky-500" />
                    <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                        “A educação é a arma mais poderosa que você pode usar para mudar o mundo.”
                        <span class="mt-1 block text-xs text-slate-400">— Nelson Mandela</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="flex flex-col bg-slate-50 dark:bg-slate-950">
            <div class="flex items-center justify-between p-6">
                <Link :href="route('home')" class="flex items-center gap-2 lg:hidden">
                    <div class="grid size-8 place-items-center rounded-lg bg-sky-600 text-white">
                        <AppLogoIcon class="size-5" />
                    </div>
                    <span class="text-sm font-semibold">SIE Matrícula</span>
                </Link>
                <div class="ml-auto">
                    <AppearanceTabs />
                </div>
            </div>

            <div class="flex flex-1 items-center justify-center px-6 pb-10">
                <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200/70 dark:bg-slate-900 dark:ring-slate-800">
                    <div class="mb-6 space-y-1">
                        <h1 v-if="title" class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ title }}</h1>
                        <p v-if="description" class="text-sm text-slate-500 dark:text-slate-400">{{ description }}</p>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
