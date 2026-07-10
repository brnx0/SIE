<script setup lang="ts">
import AppLogoIcon from '@/components/layout/AppLogoIcon.vue';
import AppearanceTabs from '@/components/common/AppearanceTabs.vue';
import { Link } from '@inertiajs/vue3';
import { BookOpen, GraduationCap, NotebookPen, Quote, Users } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const ano = computed(() => new Date().getFullYear());

const pilares = [
    { icon: GraduationCap, label: 'Matrícula' },
    { icon: Users, label: 'Funcionários' },
    { icon: BookOpen, label: 'Turmas e diário' },
    { icon: NotebookPen, label: 'Histórico escolar' },
];
</script>

<template>
    <div class="grid min-h-svh bg-slate-100 dark:bg-slate-950 lg:grid-cols-[1.05fr_1fr]">
        <!-- Painel institucional educacional (sempre escuro — leitura premium em vídeo) -->
        <div class="relative hidden overflow-hidden bg-[#0d1b34] text-slate-100 lg:flex lg:flex-col lg:justify-between">
            <!-- Base: navy acadêmico sóbrio -->
            <div class="absolute inset-0 bg-[linear-gradient(160deg,#0d1b34_0%,#12274f_52%,#0e1d3a_100%)]" />
            <!-- Realce suave (não-neon) -->
            <div class="absolute inset-0 [background-image:radial-gradient(120%_90%_at_12%_0%,rgba(255,255,255,0.10),transparent_60%)]" />
            <!-- Grade fina, discreta -->
            <div
                class="absolute inset-0 opacity-[0.22] [background-image:linear-gradient(to_right,rgba(255,255,255,0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.06)_1px,transparent_1px)] [background-size:60px_60px] [mask-image:radial-gradient(ellipse_at_center,black,transparent_80%)]"
            />

            <!-- Topo: marca -->
            <div class="relative z-10 p-12">
                <Link :href="route('home')" class="inline-flex items-center gap-4">
                    <div class="grid size-12 place-items-center rounded-xl bg-indigo-600 text-white shadow-lg ring-1 ring-white/10">
                        <AppLogoIcon class="size-6" />
                    </div>
                    <div class="leading-tight">
                        <span class="block text-lg font-semibold tracking-tight text-white">SIE Matrícula</span>
                        <span class="block text-xs uppercase tracking-[0.18em] text-indigo-200/80">Sistema Integrado de Educação</span>
                    </div>
                </Link>
            </div>

            <!-- Centro: declaração + pilares educacionais -->
            <div class="relative z-10 px-12">
                <div class="max-w-lg">
                    <div class="mb-6 h-px w-16 bg-gradient-to-r from-amber-300 to-amber-300/0" />
                    <h2 class="text-3xl font-semibold leading-tight tracking-tight text-white sm:text-[2.05rem]">
                        A jornada acadêmica de cada aluno, do primeiro registro ao histórico escolar.
                    </h2>
                    <p class="mt-5 text-base leading-relaxed text-slate-300/90">
                        Plataforma integrada para a gestão da rede de ensino — matrícula,
                        quadro de funcionários, turmas e diário online em um só lugar.
                    </p>

                    <ul class="mt-8 grid grid-cols-2 gap-x-6 gap-y-4">
                        <li
                            v-for="p in pilares"
                            :key="p.label"
                            class="flex items-center gap-3 text-sm text-slate-200"
                        >
                            <span class="grid size-9 place-items-center rounded-lg bg-white/5 ring-1 ring-white/10">
                                <component :is="p.icon" class="size-[18px] text-amber-300" />
                            </span>
                            {{ p.label }}
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Rodapé: citação institucional -->
            <div class="relative z-10 p-12">
                <figure class="flex items-start gap-3 border-t border-white/10 pt-6">
                    <Quote class="mt-0.5 size-5 shrink-0 text-amber-300/90" />
                    <div>
                        <blockquote class="text-sm leading-relaxed text-slate-200">
                            “A educação é a arma mais poderosa que você pode usar para mudar o mundo.”
                        </blockquote>
                        <figcaption class="mt-1 text-xs text-slate-400">— Nelson Mandela</figcaption>
                    </div>
                </figure>
                <p class="mt-6 text-xs text-slate-500">
                    © {{ ano }} SIE Matrícula · Sistema Integrado de Educação
                </p>
            </div>
        </div>

        <!-- Coluna do formulário -->
        <div class="flex flex-col bg-slate-100 dark:bg-slate-950">
            <div class="flex items-center justify-between p-6">
                <Link :href="route('home')" class="flex items-center gap-2 lg:hidden">
                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-600 text-white">
                        <AppLogoIcon class="size-5" />
                    </div>
                    <span class="text-sm font-semibold tracking-tight">SIE Matrícula</span>
                </Link>
                <div class="ml-auto">
                    <AppearanceTabs />
                </div>
            </div>

            <div class="flex flex-1 items-center justify-center px-6 pb-16">
                <div class="w-full max-w-md">
                    <div class="rounded-2xl bg-white p-8 shadow-xl shadow-slate-900/5 ring-1 ring-slate-200/70 dark:bg-slate-900 dark:shadow-black/20 dark:ring-slate-800 sm:p-10">
                        <div class="mb-8 space-y-2">
                            <h1 v-if="title" class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ title }}</h1>
                            <p v-if="description" class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ description }}</p>
                        </div>
                        <slot />
                    </div>
                    <p class="mt-6 text-center text-xs text-slate-400 dark:text-slate-600 lg:hidden">
                        © {{ ano }} SIE Matrícula · Sistema Integrado de Educação
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
