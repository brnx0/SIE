<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Users, FileBarChart, FileText, LayoutGrid, Accessibility, Activity, ClipboardList, Table, NotebookPen, GraduationCap, BookOpenText } from 'lucide-vue-next';
import { computed } from 'vue';

interface Relatorio {
    slug: string;
    href?: string;
    titulo: string;
    descricao: string;
    categoria: string;
    icone: string;
}

const props = withDefaults(defineProps<{
    relatorios: Relatorio[];
    titulo?: string;
    subtitulo?: string;
    grupo?: string;
}>(), {
    titulo: 'Central de Relatórios',
    subtitulo: 'Selecione um relatório para gerar.',
    grupo: 'central',
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    if (props.grupo === 'escola') return [{ title: 'Relatórios Gerais', href: '/relatorios-escola' }];
    if (props.grupo === 'diario') return [{ title: 'Relatórios do Diário', href: '/relatorios-diario' }];
    if (props.grupo === 'secretaria') return [{ title: 'Relatórios da Secretaria', href: '/relatorios-secretaria' }];
    if (props.grupo === 'pedagogico') return [{ title: 'Relatórios Pedagógicos', href: '/relatorios-pedagogico' }];
    return [{ title: 'Relatórios', href: '/relatorios' }];
});

const grupos = computed(() => {
    const map: Record<string, Relatorio[]> = {};
    for (const r of props.relatorios) {
        (map[r.categoria] ??= []).push(r);
    }
    return Object.entries(map);
});

const iconMap: Record<string, any> = { Users, FileBarChart, FileText, LayoutGrid, Accessibility, Activity, ClipboardList, Table, NotebookPen, GraduationCap, BookOpenText };
</script>

<template>
    <Head :title="titulo" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1">{{ titulo }}</h1>
            <p class="text-sm text-muted-foreground mb-6">{{ subtitulo }}</p>

            <div v-for="[categoria, lista] in grupos" :key="categoria" class="mb-8">
                <h2 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-3">{{ categoria }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="rel in lista"
                        :key="rel.slug"
                        :href="rel.href ?? `/relatorios/${rel.slug}`"
                        class="group rounded-xl border bg-card p-5 shadow-sm transition hover:border-indigo-500 hover:shadow-md"
                    >
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-indigo-100 p-2 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white dark:bg-indigo-900/40">
                                <component :is="iconMap[rel.icone] ?? FileBarChart" class="size-5" />
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold">{{ rel.titulo }}</h3>
                                <p class="text-xs text-muted-foreground mt-1">{{ rel.descricao }}</p>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
