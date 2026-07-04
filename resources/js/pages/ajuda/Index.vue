<script setup lang="ts">
import AppLogoIcon from '@/components/layout/AppLogoIcon.vue';
import AppearanceTabs from '@/components/common/AppearanceTabs.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const usuarioLogado = computed(() => !!(page.props as any).auth?.user);

interface Artigo {
    slug: string;
    titulo: string;
    secao: string;
    ordem: number;
    resumo: string | null;
    html: string;
    texto: string;
}
interface Secao {
    secao: string;
    ordem: number;
    artigos: Artigo[];
}

const props = withDefaults(defineProps<{ secoes?: Secao[] }>(), {
    secoes: () => [],
});

const busca = ref('');
const artigoAtivoSlug = ref<string | null>(null);
const menuAbertoMobile = ref(false);

// Lista achatada para busca e resolução de slug.
const todosArtigos = computed<Artigo[]>(() => props.secoes.flatMap((s) => s.artigos));

const buscaNorm = computed(() =>
    busca.value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim(),
);

const norm = (s: string) =>
    s
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

// Seções filtradas pela busca (título + resumo + texto plano do artigo).
const secoesFiltradas = computed<Secao[]>(() => {
    if (!buscaNorm.value) return props.secoes;
    const q = buscaNorm.value;
    return props.secoes
        .map((s) => ({
            ...s,
            artigos: s.artigos.filter(
                (a) =>
                    norm(a.titulo).includes(q) ||
                    norm(a.resumo ?? '').includes(q) ||
                    norm(a.texto).includes(q),
            ),
        }))
        .filter((s) => s.artigos.length > 0);
});

const totalResultados = computed(() => secoesFiltradas.value.reduce((n, s) => n + s.artigos.length, 0));

const artigoAtivo = computed<Artigo | null>(
    () => todosArtigos.value.find((a) => a.slug === artigoAtivoSlug.value) ?? null,
);

function abrir(slug: string) {
    artigoAtivoSlug.value = slug;
    menuAbertoMobile.value = false;
    if (typeof window !== 'undefined') {
        history.replaceState(null, '', `#${slug}`);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function limparBusca() {
    busca.value = '';
}

onMounted(() => {
    // Deep-link por hash (#slug); senão abre o primeiro artigo.
    const hash = window.location.hash.replace('#', '');
    const alvo = todosArtigos.value.find((a) => a.slug === hash);
    artigoAtivoSlug.value = alvo?.slug ?? todosArtigos.value[0]?.slug ?? null;
});
</script>

<template>
    <Head title="Central de Ajuda — SIE Matrícula" />

    <div class="min-h-svh bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <!-- Header -->
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
            <div class="mx-auto flex w-[95%] max-w-7xl items-center justify-between gap-3 py-3">
                <Link :href="route('home')" class="flex items-center gap-2">
                    <div class="grid size-9 place-items-center rounded-lg bg-gradient-to-br from-indigo-600 to-fuchsia-600 text-white">
                        <AppLogoIcon class="size-5" />
                    </div>
                    <div class="leading-tight">
                        <span class="block text-sm font-semibold">Central de Ajuda</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">SIE Matrícula</span>
                    </div>
                </Link>

                <div class="flex items-center gap-2">
                    <AppearanceTabs class="hidden sm:inline-flex" />
                    <Link
                        :href="usuarioLogado ? route('dashboard') : route('home')"
                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <ArrowLeft class="size-4" />
                        <span class="hidden sm:inline">{{ usuarioLogado ? 'Voltar ao sistema' : 'Início' }}</span>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Hero + busca -->
        <section class="border-b border-slate-200 bg-gradient-to-br from-indigo-600 to-fuchsia-600 dark:border-slate-800">
            <div class="mx-auto w-[95%] max-w-7xl py-8 text-white sm:py-10">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Como podemos ajudar?</h1>
                <p class="mt-1 text-sm text-white/80">Dúvidas sobre as telas e funcionalidades do sistema.</p>
                <div class="relative mt-5 max-w-xl">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="busca"
                        type="search"
                        placeholder="Buscar por tela, funcionalidade..."
                        class="w-full rounded-lg border-0 bg-white py-3 pl-10 pr-10 text-sm text-slate-900 shadow-sm outline-none ring-2 ring-transparent placeholder:text-slate-400 focus:ring-white/60"
                    />
                    <button
                        v-if="busca"
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                        @click="limparBusca"
                    >
                        <X class="size-5" />
                    </button>
                </div>
                <p v-if="buscaNorm" class="mt-2 text-xs text-white/80">
                    {{ totalResultados }} resultado(s) para "{{ busca }}"
                </p>
            </div>
        </section>

        <!-- Conteúdo -->
        <main class="mx-auto flex w-[95%] max-w-7xl flex-col gap-6 py-6 lg:flex-row lg:py-8">
            <!-- Índice lateral -->
            <aside class="lg:w-72 lg:shrink-0">
                <button
                    type="button"
                    class="mb-3 inline-flex w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium dark:border-slate-800 dark:bg-slate-900 lg:hidden"
                    @click="menuAbertoMobile = !menuAbertoMobile"
                >
                    <span class="inline-flex items-center gap-2"><BookOpen class="size-4" /> Tópicos</span>
                    <span class="text-xs text-slate-500">{{ menuAbertoMobile ? 'fechar' : 'abrir' }}</span>
                </button>

                <nav
                    :class="menuAbertoMobile ? 'block' : 'hidden'"
                    class="space-y-5 lg:sticky lg:top-20 lg:block lg:max-h-[calc(100svh-6rem)] lg:overflow-y-auto lg:pr-2"
                >
                    <div v-for="s in secoesFiltradas" :key="s.secao">
                        <p class="mb-1.5 px-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ s.secao }}
                        </p>
                        <ul class="space-y-0.5">
                            <li v-for="a in s.artigos" :key="a.slug">
                                <button
                                    type="button"
                                    class="w-full rounded-md px-2 py-1.5 text-left text-sm transition"
                                    :class="
                                        a.slug === artigoAtivoSlug
                                            ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300'
                                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                                    "
                                    @click="abrir(a.slug)"
                                >
                                    {{ a.titulo }}
                                </button>
                            </li>
                        </ul>
                    </div>
                    <p v-if="secoesFiltradas.length === 0" class="px-2 text-sm text-slate-500">
                        Nenhum tópico encontrado.
                    </p>
                </nav>
            </aside>

            <!-- Artigo -->
            <article class="min-w-0 flex-1">
                <div
                    v-if="artigoAtivo"
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">
                        {{ artigoAtivo.secao }}
                    </p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ artigoAtivo.titulo }}</h2>
                    <p v-if="artigoAtivo.resumo" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ artigoAtivo.resumo }}
                    </p>
                    <hr class="my-5 border-slate-200 dark:border-slate-800" />
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <div class="ajuda-conteudo" v-html="artigoAtivo.html" />
                </div>

                <div
                    v-else
                    class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500 dark:border-slate-700 dark:bg-slate-900"
                >
                    Selecione um tópico ao lado para ver o conteúdo.
                </div>
            </article>
        </main>

        <footer class="mx-auto w-[95%] max-w-7xl py-8 text-center text-xs text-slate-500 dark:text-slate-400">
            © {{ new Date().getFullYear() }} SIE Matrícula — Central de Ajuda.
        </footer>
    </div>
</template>

<style scoped>
/* Estilo do HTML gerado do Markdown (sem plugin typography). */
.ajuda-conteudo :deep(h2) {
    margin-top: 1.75rem;
    margin-bottom: 0.6rem;
    font-size: 1.15rem;
    font-weight: 600;
    line-height: 1.3;
}
.ajuda-conteudo :deep(h2:first-child) {
    margin-top: 0;
}
.ajuda-conteudo :deep(h3) {
    margin-top: 1.35rem;
    margin-bottom: 0.4rem;
    font-size: 1rem;
    font-weight: 600;
}
.ajuda-conteudo :deep(p) {
    margin: 0.65rem 0;
    line-height: 1.7;
    font-size: 0.925rem;
}
.ajuda-conteudo :deep(ul),
.ajuda-conteudo :deep(ol) {
    margin: 0.65rem 0;
    padding-left: 1.4rem;
    line-height: 1.7;
    font-size: 0.925rem;
}
.ajuda-conteudo :deep(ul) {
    list-style: disc;
}
.ajuda-conteudo :deep(ol) {
    list-style: decimal;
}
.ajuda-conteudo :deep(li) {
    margin: 0.2rem 0;
}
.ajuda-conteudo :deep(li::marker) {
    color: rgb(129 140 248);
}
.ajuda-conteudo :deep(a) {
    color: rgb(79 70 229);
    text-decoration: underline;
    text-underline-offset: 2px;
}
.dark .ajuda-conteudo :deep(a) {
    color: rgb(165 180 252);
}
.ajuda-conteudo :deep(strong) {
    font-weight: 600;
}
.ajuda-conteudo :deep(code) {
    border-radius: 0.3rem;
    background: rgb(241 245 249);
    padding: 0.1rem 0.35rem;
    font-size: 0.85em;
}
.dark .ajuda-conteudo :deep(code) {
    background: rgb(30 41 59);
}
.ajuda-conteudo :deep(blockquote) {
    margin: 0.9rem 0;
    border-left: 3px solid rgb(217 70 239);
    background: rgb(250 245 255);
    padding: 0.6rem 0.9rem;
    border-radius: 0 0.4rem 0.4rem 0;
    font-size: 0.9rem;
}
.dark .ajuda-conteudo :deep(blockquote) {
    background: rgb(59 7 100 / 0.25);
}
.ajuda-conteudo :deep(table) {
    width: 100%;
    margin: 0.9rem 0;
    border-collapse: collapse;
    font-size: 0.875rem;
    display: block;
    overflow-x: auto;
}
.ajuda-conteudo :deep(th),
.ajuda-conteudo :deep(td) {
    border: 1px solid rgb(226 232 240);
    padding: 0.45rem 0.7rem;
    text-align: left;
}
.dark .ajuda-conteudo :deep(th),
.dark .ajuda-conteudo :deep(td) {
    border-color: rgb(51 65 85);
}
.ajuda-conteudo :deep(th) {
    background: rgb(248 250 252);
    font-weight: 600;
}
.dark .ajuda-conteudo :deep(th) {
    background: rgb(30 41 59);
}
</style>
