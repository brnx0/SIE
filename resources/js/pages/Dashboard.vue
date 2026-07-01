<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity, AlertTriangle, ArrowRightLeft, BookMarked, CalendarDays, ClipboardCheck,
    ClipboardList, ClipboardPen, DoorOpen, FileText, GraduationCap, School, Settings,
    Stethoscope, UserPlus, Users,
} from 'lucide-vue-next';
import { computed, type Component } from 'vue';

interface Kpi { key: string; label: string; value: number | string; icon: string; tone: string; href?: string | null }
interface ShortcutItem { title: string; desc: string; href: string; icon: string }
interface PanelItem { title: string; meta?: string | null; href?: string | null; tone?: string }
interface Panel { key: string; title: string; subtitle?: string; items: PanelItem[]; empty: string }

const props = defineProps<{
    perfil?: string;
    contexto?: string;
    kpis?: Kpi[];
    shortcuts?: ShortcutItem[];
    panels?: Panel[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Painel', href: '/dashboard' }];

const page = usePage<SharedData>();
const userName = computed(() => page.props.auth.user?.name ?? 'Educador');

const kpis = computed(() => props.kpis ?? []);
const shortcuts = computed(() => props.shortcuts ?? []);
const panels = computed(() => props.panels ?? []);

const iconMap: Record<string, Component> = {
    'activity': Activity, 'alert-triangle': AlertTriangle, 'arrow-right-left': ArrowRightLeft,
    'book-marked': BookMarked, 'calendar-days': CalendarDays, 'clipboard-check': ClipboardCheck,
    'clipboard-list': ClipboardList, 'clipboard-pen': ClipboardPen, 'door-open': DoorOpen,
    'file-text': FileText, 'graduation-cap': GraduationCap, 'school': School, 'settings': Settings,
    'stethoscope': Stethoscope, 'user-plus': UserPlus, 'users': Users,
};
const icon = (name: string): Component => iconMap[name] ?? Activity;

const toneClass: Record<string, string> = {
    sky: 'bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-300',
    teal: 'bg-teal-50 text-teal-600 dark:bg-teal-900/30 dark:text-teal-300',
    amber: 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-300',
    rose: 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300',
    emerald: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-300',
    red: 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-300',
    violet: 'bg-violet-50 text-violet-600 dark:bg-violet-900/30 dark:text-violet-300',
};
const tone = (t?: string) => toneClass[t ?? 'sky'] ?? toneClass.sky;
</script>

<template>
    <Head title="Painel" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <!-- Cabeçalho -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Olá, bem-vindo(a) de volta</p>
                        <h1 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-50">{{ userName }}</h1>
                        <p v-if="perfil" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ perfil }}</p>
                    </div>
                    <div v-if="contexto" class="inline-flex items-center gap-2 rounded-lg bg-sky-50 px-3 py-1.5 text-sm text-sky-700 ring-1 ring-sky-100 dark:bg-sky-900/30 dark:text-sky-200 dark:ring-sky-900/40">
                        <Users class="size-4" />
                        {{ contexto }}
                    </div>
                </div>
            </div>

            <!-- KPIs -->
            <div v-if="kpis.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <component
                    :is="k.href ? Link : 'div'"
                    v-for="k in kpis"
                    :key="k.key"
                    :href="k.href || undefined"
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                    :class="k.href ? 'hover:border-sky-300 dark:hover:border-sky-700' : ''"
                >
                    <div class="flex items-center justify-between">
                        <div :class="['grid size-10 place-items-center rounded-lg', tone(k.tone)]">
                            <component :is="icon(k.icon)" class="size-5" />
                        </div>
                        <span class="text-xs text-slate-400">Atual</span>
                    </div>
                    <p class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">{{ k.value }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ k.label }}</p>
                </component>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Atalhos -->
                <div v-if="shortcuts.length" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-50">Atalhos rápidos</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Acesse suas principais áreas.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <Link
                            v-for="s in shortcuts"
                            :key="s.href"
                            :href="s.href"
                            class="group flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50/50 p-4 transition hover:border-sky-300 hover:bg-sky-50/60 dark:border-slate-800 dark:bg-slate-900/40 dark:hover:border-sky-700 dark:hover:bg-sky-950/30"
                        >
                            <div class="grid size-10 place-items-center rounded-lg bg-white text-sky-600 ring-1 ring-slate-200 transition group-hover:bg-sky-600 group-hover:text-white group-hover:ring-sky-600 dark:bg-slate-800 dark:text-sky-300 dark:ring-slate-700">
                                <component :is="icon(s.icon)" class="size-5" />
                            </div>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-50">{{ s.title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ s.desc }}</p>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Painéis (listas/config) -->
                <div
                    v-for="p in panels"
                    :key="p.key"
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-50">{{ p.title }}</h2>
                    <p v-if="p.subtitle" class="text-sm text-slate-500 dark:text-slate-400">{{ p.subtitle }}</p>

                    <ul v-if="p.items.length" class="mt-4 space-y-2 text-sm">
                        <component
                            :is="it.href ? Link : 'li'"
                            v-for="(it, i) in p.items"
                            :key="i"
                            :href="it.href || undefined"
                            class="flex items-start justify-between gap-3 rounded-lg border border-transparent px-2 py-2 transition"
                            :class="it.href ? 'hover:border-slate-200 hover:bg-slate-50 dark:hover:border-slate-800 dark:hover:bg-slate-800/40' : ''"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-medium text-slate-900 dark:text-slate-50">{{ it.title }}</p>
                                <p v-if="it.meta" class="truncate text-xs text-slate-500 dark:text-slate-400">{{ it.meta }}</p>
                            </div>
                            <span
                                v-if="it.tone"
                                :class="['mt-1 size-2 shrink-0 rounded-full', tone(it.tone).split(' ')[0]]"
                            />
                        </component>
                    </ul>
                    <p v-else class="mt-4 text-sm text-slate-400">{{ p.empty }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
