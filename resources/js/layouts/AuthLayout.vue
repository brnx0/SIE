<script setup lang="ts">
import AppearanceTabs from '@/components/common/AppearanceTabs.vue';
import FundoEscolar from '@/components/common/FundoEscolar.vue';
import { usePage } from '@inertiajs/vue3';
import { GraduationCap } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

// Logo do município (ParametroEntidade), compartilhada só p/ visitante.
const page = usePage();
const logoUrl = computed(() => ((page.props as any).entidade?.logo_url as string | null) ?? null);

const ano = computed(() => new Date().getFullYear());
</script>

<template>
    <main class="relative isolate flex min-h-svh flex-col overflow-hidden">
        <FundoEscolar />

        <header class="flex justify-end px-5 py-5 sm:px-8">
            <AppearanceTabs />
        </header>

        <div class="flex flex-1 items-center justify-center px-5 pt-10 pb-8">
            <div class="w-full max-w-[420px]">
                <!-- Cartão; a logo do município "pousa" na borda de cima -->
                <div class="relative rounded-[28px] bg-white px-7 pt-20 pb-9 shadow-2xl shadow-[#0c2a52]/20 sm:px-10 dark:bg-[#0b1b31] dark:shadow-black/50 dark:ring-1 dark:ring-white/10">
                    <div
                        class="absolute top-0 left-1/2 grid h-24 min-w-24 max-w-[260px] -translate-x-1/2 -translate-y-1/2 place-items-center rounded-2xl bg-white px-5 shadow-lg shadow-[#0c2a52]/15 ring-1 ring-slate-200/80"
                    >
                        <img v-if="logoUrl" :src="logoUrl" alt="Logo do município" class="max-h-16 w-auto max-w-full object-contain" />
                        <GraduationCap v-else class="size-10 text-[#1e4e8c]" />
                    </div>

                    <div class="mb-8 text-center">
                        <p class="text-xs font-semibold tracking-[0.22em] text-[#1e4e8c] uppercase dark:text-sky-300">Sistema Integrado de Educação</p>
                        <h1 v-if="title" class="mt-3 text-[1.7rem] font-semibold tracking-tight text-slate-900 dark:text-white">{{ title }}</h1>
                        <p v-if="description" class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ description }}</p>
                    </div>
                    <slot />
                </div>
            </div>
        </div>

        <footer class="pb-5 text-center text-xs text-slate-500 dark:text-slate-400">© {{ ano }} SIE · Sistema Integrado de Educação</footer>
    </main>
</template>
