<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity, AlertTriangle, ArrowRightLeft, ArrowUpRight, BookMarked, CalendarDays, CheckCircle2,
    ClipboardCheck, ClipboardList, ClipboardPen, DoorOpen, FileText, GraduationCap, School, Search,
    Settings, Stethoscope, UserPlus, Users,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, type Component } from 'vue';

interface Kpi { key: string; label: string; value: number | string; icon: string; tone: string; href?: string | null }
interface ShortcutItem { title: string; desc: string; href: string; icon: string }
interface PanelItem { title: string; meta?: string | null; href?: string | null; tone?: string }
interface Panel { key: string; title: string; subtitle?: string; items: PanelItem[]; empty: string }
interface Linha { nome: string; valor: number }
interface Visao {
    por_segmento: Linha[];
    por_escola: Linha[];
    por_turno: Linha[];
    ocupacao: { capacidade: number; ocupadas: number };
}

const props = defineProps<{
    perfil?: string;
    contexto?: string;
    anoLetivo?: number | string | null;
    visao?: Visao | null;
    kpis?: Kpi[];
    shortcuts?: ShortcutItem[];
    panels?: Panel[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Painel', href: '/dashboard' }];

const page = usePage<SharedData>();

// "MARIA DA SILVA" → "Maria" (nomes costumam vir em caixa alta).
const primeiroNome = computed(() => {
    const p = (page.props.auth.user?.name ?? '').trim().split(/\s+/)[0] ?? '';
    return p ? p.charAt(0).toLocaleUpperCase('pt-BR') + p.slice(1).toLocaleLowerCase('pt-BR') : 'Educador';
});
const saudacao = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Bom dia' : h < 18 ? 'Boa tarde' : 'Boa noite';
});

const iconMap: Record<string, Component> = {
    'activity': Activity, 'alert-triangle': AlertTriangle, 'arrow-right-left': ArrowRightLeft,
    'book-marked': BookMarked, 'calendar-days': CalendarDays, 'clipboard-check': ClipboardCheck,
    'clipboard-list': ClipboardList, 'clipboard-pen': ClipboardPen, 'door-open': DoorOpen,
    'file-text': FileText, 'graduation-cap': GraduationCap, 'school': School, 'settings': Settings,
    'stethoscope': Stethoscope, 'user-plus': UserPlus, 'users': Users,
};
const icon = (name: string): Component => iconMap[name] ?? Activity;

const fmt = (n: number) => n.toLocaleString('pt-BR');

// ── Números de destaque (faixa azul) × pontos de atenção ───────────────────
// Destaque = volume do que o usuário enxerga. O resto (lotadas, sem professor,
// planos pendentes) é pendência → vira lista de atenção, não card.
const CHAVES_DESTAQUE = ['alunos', 'escolas', 'turmas', 'funcionarios', 'minhas_turmas'];

const destaques = computed(() => (props.kpis ?? []).filter((k) => CHAVES_DESTAQUE.includes(k.key)));

const ocupacao = computed(() => {
    const o = props.visao?.ocupacao;
    if (!o || o.capacidade <= 0) return null;
    const pct = Math.min(100, Math.round((o.ocupadas / o.capacidade) * 100));
    return { ...o, pct, livres: Math.max(0, o.capacidade - o.ocupadas) };
});

const atencao = computed(() =>
    (props.kpis ?? [])
        .filter((k) => !CHAVES_DESTAQUE.includes(k.key))
        // "Vagas livres" já aparece no medidor de ocupação quando ele existe.
        .filter((k) => !(k.key === 'vagas' && ocupacao.value))
        .map((k) => ({ ...k, numero: Number(k.value) || 0, informativo: k.key === 'vagas' })),
);
const pendencias = computed(() => atencao.value.filter((a) => !a.informativo && a.numero > 0).length);

// Contagem animada dos destaques (respeita movimento reduzido).
const exibido = reactive<Record<string, number>>({});
onMounted(() => {
    const reduzido = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const inicio = performance.now();
    const DUR = 1100;
    const alvo = (k: Kpi) => Number(k.value) || 0;
    if (reduzido) {
        destaques.value.forEach((k) => (exibido[k.key] = alvo(k)));
        return;
    }
    const passo = (agora: number) => {
        const t = Math.min(1, (agora - inicio) / DUR);
        const e = 1 - Math.pow(1 - t, 4); // easeOutQuart
        destaques.value.forEach((k) => (exibido[k.key] = Math.round(alvo(k) * e)));
        if (t < 1) requestAnimationFrame(passo);
    };
    requestAnimationFrame(passo);
});
const numeroDestaque = (k: Kpi) => (typeof k.value === 'number' ? fmt(exibido[k.key] ?? 0) : k.value);

// ── Matrículas: barras com alternância de recorte ──────────────────────────
type Recorte = 'por_segmento' | 'por_escola' | 'por_turno';
const recortes = computed(() => {
    const v = props.visao;
    if (!v) return [];
    return ([
        { key: 'por_segmento', label: 'Segmento' },
        { key: 'por_escola', label: 'Escola' },
        { key: 'por_turno', label: 'Turno' },
    ] as { key: Recorte; label: string }[]).filter((r) => {
        const linhas = v[r.key] ?? [];
        // "Escola" só faz sentido com mais de uma escola no escopo.
        return r.key === 'por_escola' ? linhas.length > 1 : linhas.length > 0;
    });
});
const recorte = ref<Recorte>('por_segmento');
const recorteAtivo = computed<Recorte | null>(() =>
    recortes.value.some((r) => r.key === recorte.value) ? recorte.value : (recortes.value[0]?.key ?? null),
);
const linhas = computed<Linha[]>(() => (recorteAtivo.value ? (props.visao?.[recorteAtivo.value] ?? []) : []));
const maiorLinha = computed(() => Math.max(1, ...linhas.value.map((l) => l.valor)));
const totalAlunos = computed(() => Number((props.kpis ?? []).find((k) => k.key === 'alunos')?.value) || 0);
const pctDoTotal = (v: number) => (totalAlunos.value ? ((v / totalAlunos.value) * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) : '0');
const linhaFoco = ref<string | null>(null);

// ── Medidor de ocupação (SVG) ──────────────────────────────────────────────
const RAIO = 52;
const CIRC = 2 * Math.PI * RAIO;

// ── Atalhos com busca ──────────────────────────────────────────────────────
const busca = ref('');
const semAcento = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const atalhos = computed(() => {
    const q = semAcento(busca.value.trim());
    const todos = props.shortcuts ?? [];
    return q ? todos.filter((s) => semAcento(`${s.title} ${s.desc}`).includes(q)) : todos;
});

const panels = computed(() => props.panels ?? []);

const pontoTom: Record<string, string> = {
    red: 'bg-red-500', amber: 'bg-amber-500', violet: 'bg-violet-500', emerald: 'bg-emerald-500',
    sky: 'bg-sky-500', teal: 'bg-teal-500', rose: 'bg-rose-500',
};
</script>

<template>
    <Head title="Painel" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-4 md:p-6">
            <!-- Faixa de abertura: saudação + grandes números -->
            <section class="relative isolate overflow-hidden rounded-3xl bg-[#0c2a52] px-6 py-7 text-white shadow-lg shadow-[#0c2a52]/20 sm:px-9 sm:py-9">
                <div class="absolute inset-0 -z-10 bg-[radial-gradient(70%_120%_at_88%_0%,#2a6fbd_0%,transparent_62%),linear-gradient(160deg,#0f3566_0%,#0c2a52_55%,#081d3a_100%)]" />
                <div class="painel-doodles absolute inset-0 -z-10 bg-white opacity-[0.06]" aria-hidden="true" />

                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                            {{ saudacao }}, <span class="text-[#9fd0ff]">{{ primeiroNome }}</span>
                        </h1>
                        <p v-if="perfil" class="mt-2 text-sm text-sky-100/75">{{ perfil }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <span v-if="contexto" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 ring-1 ring-white/15 backdrop-blur">
                            <School class="size-4 text-[#9fd0ff]" />
                            {{ contexto }}
                        </span>
                        <span v-if="anoLetivo" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 ring-1 ring-white/15 backdrop-blur">
                            <CalendarDays class="size-4 text-[#9fd0ff]" />
                            Ano letivo {{ anoLetivo }}
                        </span>
                    </div>
                </div>

                <div v-if="destaques.length" class="mt-9 grid grid-cols-2 items-end gap-x-6 gap-y-7 lg:flex lg:flex-wrap lg:gap-x-0">
                    <component
                        :is="k.href ? Link : 'div'"
                        v-for="(k, i) in destaques"
                        :key="k.key"
                        :href="k.href || undefined"
                        class="group relative lg:flex-1 lg:px-8 lg:first:pl-0"
                        :class="i > 0 ? 'lg:border-l lg:border-white/12' : ''"
                    >
                        <div class="flex items-center gap-2 text-sm text-sky-100/70">
                            <component :is="icon(k.icon)" class="size-4" />
                            {{ k.label }}
                            <ArrowUpRight v-if="k.href" class="size-4 -translate-x-1 opacity-0 transition group-hover:translate-x-0 group-hover:opacity-100" />
                        </div>
                        <p class="mt-2 text-4xl font-semibold tracking-tight tabular-nums sm:text-5xl xl:text-6xl">{{ numeroDestaque(k) }}</p>
                    </component>
                </div>
            </section>

            <!-- Matrículas + ocupação/atenção -->
            <div v-if="recorteAtivo || ocupacao || atencao.length" class="grid gap-5 lg:grid-cols-3">
                <section v-if="recorteAtivo" class="cartao lg:col-span-2">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="titulo">Matrículas ativas</h2>
                            <p class="subtitulo">
                                Distribuição dos alunos por
                                {{ recortes.find((r) => r.key === recorteAtivo)?.label.toLowerCase() }}<template v-if="recorteAtivo === 'por_escola'"> — as {{ linhas.length }} maiores</template>.
                            </p>
                        </div>
                        <div v-if="recortes.length > 1" class="inline-flex rounded-full bg-slate-100 p-1 dark:bg-slate-800" role="tablist" aria-label="Recorte">
                            <button
                                v-for="r in recortes"
                                :key="r.key"
                                type="button"
                                role="tab"
                                :aria-selected="recorteAtivo === r.key"
                                class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                                :class="recorteAtivo === r.key
                                    ? 'bg-white text-[#1e4e8c] shadow-sm dark:bg-slate-950 dark:text-sky-300'
                                    : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
                                @click="recorte = r.key"
                            >
                                {{ r.label }}
                            </button>
                        </div>
                    </div>

                    <ul class="mt-6 space-y-1" @mouseleave="linhaFoco = null">
                        <li
                            v-for="l in linhas"
                            :key="recorteAtivo + l.nome"
                            class="group grid grid-cols-[minmax(0,11rem)_1fr_auto] items-center gap-4 rounded-lg px-2 py-2 transition-opacity sm:grid-cols-[minmax(0,15rem)_1fr_auto]"
                            :class="linhaFoco && linhaFoco !== l.nome ? 'opacity-45' : ''"
                            :title="`${l.nome}: ${fmt(l.valor)} alunos (${pctDoTotal(l.valor)}% do total)`"
                            @mouseenter="linhaFoco = l.nome"
                        >
                            <span class="truncate text-sm text-slate-700 dark:text-slate-200">{{ l.nome }}</span>
                            <span class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <span
                                    class="barra block h-full rounded-full bg-[#1e4e8c] dark:bg-sky-400"
                                    :style="{ width: `${Math.max(1.5, (l.valor / maiorLinha) * 100)}%` }"
                                />
                            </span>
                            <span class="w-28 text-right text-sm tabular-nums">
                                <span class="font-semibold text-slate-900 dark:text-slate-50">{{ fmt(l.valor) }}</span>
                                <span class="ml-1.5 text-xs text-slate-400">{{ pctDoTotal(l.valor) }}%</span>
                            </span>
                        </li>
                    </ul>
                </section>

                <div class="flex flex-col gap-5" :class="recorteAtivo ? '' : 'lg:col-span-3 lg:flex-row'">
                    <!-- Ocupação das vagas -->
                    <section v-if="ocupacao" class="cartao flex-1">
                        <h2 class="titulo">Ocupação das vagas</h2>
                        <p class="subtitulo">Turmas regulares abertas.</p>
                        <div class="mt-5 flex items-center gap-6">
                            <div class="relative size-32 shrink-0">
                                <svg viewBox="0 0 120 120" class="size-full -rotate-90" role="img" :aria-label="`${ocupacao.pct}% das vagas ocupadas`">
                                    <circle cx="60" cy="60" :r="RAIO" fill="none" stroke-width="10" class="stroke-slate-100 dark:stroke-slate-800" />
                                    <circle
                                        cx="60" cy="60" :r="RAIO" fill="none" stroke-width="10" stroke-linecap="round"
                                        class="medidor stroke-[#1e4e8c] dark:stroke-sky-400"
                                        :stroke-dasharray="CIRC"
                                        :style="{ '--alvo': CIRC * (1 - ocupacao.pct / 100), '--cheio': CIRC }"
                                    />
                                </svg>
                                <div class="absolute inset-0 grid place-items-center">
                                    <span class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-50">{{ ocupacao.pct }}%</span>
                                </div>
                            </div>
                            <dl class="space-y-2 text-sm">
                                <div>
                                    <dt class="text-slate-500 dark:text-slate-400">Ocupadas</dt>
                                    <dd class="font-semibold tabular-nums text-slate-900 dark:text-slate-50">{{ fmt(ocupacao.ocupadas) }} de {{ fmt(ocupacao.capacidade) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 dark:text-slate-400">Vagas livres</dt>
                                    <dd class="font-semibold tabular-nums text-slate-900 dark:text-slate-50">{{ fmt(ocupacao.livres) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </section>

                    <!-- Pontos de atenção -->
                    <section v-if="atencao.length" class="cartao flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="titulo">Pontos de atenção</h2>
                            <span
                                v-if="pendencias === 0"
                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                            >
                                <CheckCircle2 class="size-3.5" /> Tudo em dia
                            </span>
                        </div>
                        <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                            <component
                                :is="a.href ? Link : 'li'"
                                v-for="a in atencao"
                                :key="a.key"
                                :href="a.href || undefined"
                                class="group -mx-2 flex items-center gap-3 rounded-lg px-2 py-3 transition"
                                :class="a.href ? 'hover:bg-slate-50 dark:hover:bg-slate-800/50' : ''"
                            >
                                <span
                                    class="size-2 shrink-0 rounded-full"
                                    :class="a.informativo ? 'bg-sky-500' : a.numero > 0 ? (pontoTom[a.tone] ?? 'bg-amber-500') : 'bg-slate-300 dark:bg-slate-600'"
                                />
                                <span class="min-w-0 flex-1 truncate text-sm text-slate-700 dark:text-slate-200">{{ a.label }}</span>
                                <span
                                    class="text-lg font-semibold tabular-nums"
                                    :class="a.numero > 0 ? 'text-slate-900 dark:text-slate-50' : 'text-slate-300 dark:text-slate-600'"
                                >{{ fmt(a.numero) }}</span>
                                <ArrowUpRight v-if="a.href" class="size-4 text-slate-300 transition group-hover:text-[#1e4e8c] dark:text-slate-600 dark:group-hover:text-sky-300" />
                            </component>
                        </ul>
                    </section>
                </div>
            </div>

            <!-- Atalhos (com busca) + painéis -->
            <div class="grid gap-5 lg:grid-cols-3">
                <section v-if="(shortcuts ?? []).length" class="cartao" :class="panels.length ? 'lg:col-span-2' : 'lg:col-span-3'">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="titulo">O que você precisa fazer?</h2>
                            <p class="subtitulo">Atalhos para as suas principais áreas.</p>
                        </div>
                        <label class="relative block w-full sm:w-64">
                            <span class="sr-only">Buscar atalho</span>
                            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="busca"
                                type="search"
                                placeholder="Buscar atalho…"
                                class="h-10 w-full rounded-full border-0 bg-slate-100 pr-4 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-[#1e4e8c]/40 dark:bg-slate-800 dark:text-slate-100 dark:focus:bg-slate-900"
                            />
                        </label>
                    </div>

                    <div v-if="atalhos.length" class="mt-5 grid gap-3 sm:grid-cols-2" :class="panels.length ? '' : 'xl:grid-cols-3'">
                        <Link
                            v-for="s in atalhos"
                            :key="s.href"
                            :href="s.href"
                            class="group flex items-center gap-4 rounded-2xl bg-slate-50 p-4 ring-1 ring-transparent transition hover:-translate-y-0.5 hover:bg-white hover:shadow-md hover:shadow-[#0c2a52]/10 hover:ring-[#1e4e8c]/20 dark:bg-slate-800/50 dark:hover:bg-slate-800"
                        >
                            <div class="grid size-11 shrink-0 place-items-center rounded-xl bg-white text-[#1e4e8c] shadow-sm ring-1 ring-slate-200/70 transition group-hover:bg-[#1e4e8c] group-hover:text-white group-hover:ring-[#1e4e8c] dark:bg-slate-900 dark:text-sky-300 dark:ring-slate-700">
                                <component :is="icon(s.icon)" class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-slate-900 dark:text-slate-50">{{ s.title }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ s.desc }}</p>
                            </div>
                            <ArrowUpRight class="size-4 shrink-0 text-slate-300 transition group-hover:text-[#1e4e8c] dark:text-slate-600 dark:group-hover:text-sky-300" />
                        </Link>
                    </div>
                    <p v-else class="mt-6 text-sm text-slate-400">Nenhum atalho encontrado para “{{ busca }}”.</p>
                </section>

                <section v-for="p in panels" :key="p.key" class="cartao">
                    <h2 class="titulo">{{ p.title }}</h2>
                    <p v-if="p.subtitle" class="subtitulo">{{ p.subtitle }}</p>

                    <ul v-if="p.items.length" class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <component
                            :is="it.href ? Link : 'li'"
                            v-for="(it, i) in p.items"
                            :key="i"
                            :href="it.href || undefined"
                            class="-mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-2.5 transition"
                            :class="it.href ? 'hover:bg-slate-50 dark:hover:bg-slate-800/50' : ''"
                        >
                            <span v-if="it.tone" :class="['size-2 shrink-0 rounded-full', pontoTom[it.tone] ?? 'bg-slate-400']" />
                            <span class="min-w-0 flex-1 truncate text-slate-700 dark:text-slate-200">{{ it.title }}</span>
                            <span v-if="it.meta" class="shrink-0 truncate font-medium text-slate-900 dark:text-slate-50">{{ it.meta }}</span>
                        </component>
                    </ul>
                    <p v-else class="mt-4 text-sm text-slate-400">{{ p.empty }}</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.cartao {
    @apply rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70 dark:bg-slate-900 dark:ring-slate-800;
}
.titulo {
    @apply text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-50;
}
.subtitulo {
    @apply mt-0.5 text-sm text-slate-500 dark:text-slate-400;
}

/* Doodles escolares (mesmo padrão do login). */
.painel-doodles {
    -webkit-mask: url('/img/login/fundo-icones.png') 0 0 / 560px repeat;
    mask: url('/img/login/fundo-icones.png') 0 0 / 560px repeat;
}

/* Barras crescem da esquerda ao entrar/trocar de recorte. */
.barra {
    transform-origin: left;
    animation: barra-cresce 700ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
@keyframes barra-cresce {
    from { transform: scaleX(0); }
}

.medidor {
    stroke-dashoffset: var(--alvo);
    animation: medidor-enche 1100ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
@keyframes medidor-enche {
    from { stroke-dashoffset: var(--cheio); }
}

@media (prefers-reduced-motion: reduce) {
    .barra, .medidor { animation: none; }
}
</style>
