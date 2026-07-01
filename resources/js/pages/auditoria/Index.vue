<script setup lang="ts">
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Mudanca { campo: string; de: string; para: string }
interface Atividade {
    id: number;
    quando: string | null;
    usuario: string;
    acao: string;
    acao_key: string | null;
    entidade: string | null;
    registro_id: number | string | null;
    mudancas: Mudanca[];
}
interface UsuarioOpt { id: number; name: string }
interface AcaoOpt { value: string; label: string }

const props = defineProps<{
    atividades: Paginated<Atividade>;
    filters: { busca: string; causer: number | string | null; entidade: string; acao: string; de: string; ate: string; per_page: number };
    entidades: string[];
    usuarios: UsuarioOpt[];
    acoes: AcaoOpt[];
}>();

const busca    = ref(props.filters.busca ?? '');
const causer   = ref(props.filters.causer ?? '');
const entidade = ref(props.filters.entidade ?? '');
const acao     = ref(props.filters.acao ?? '');
const de       = ref(props.filters.de ?? '');
const ate      = ref(props.filters.ate ?? '');
const perPage  = ref(props.filters.per_page ?? 25);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        busca: busca.value, causer: causer.value ?? '', entidade: entidade.value,
        acao: acao.value, de: de.value, ate: ate.value, per_page: perPage.value,
    };
    if (!resetPage) params.page = props.atividades.current_page;
    router.get('/auditoria', params, { preserveState: true, replace: true });
};
watch(busca, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([causer, entidade, acao, de, ate, perPage], () => apply(true));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Auditoria', href: '/auditoria' }];

const expandido = ref<Set<number>>(new Set());
const toggle = (id: number) => {
    const s = new Set(expandido.value);
    if (s.has(id)) s.delete(id); else s.add(id);
    expandido.value = s;
};

const acaoClass = (key: string | null) => {
    switch (key) {
        case 'created':  return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
        case 'updated':  return 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 'deleted':  return 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300';
        default:         return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
    }
};

const formatWhen = (iso: string | null) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR');
};
</script>

<template>
    <Head title="Auditoria" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Auditoria</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Trilha de quem alterou o quê e quando no sistema.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative min-w-[220px] flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="busca" placeholder="Buscar por usuário, entidade ou ação..." class="pl-9" />
                </div>
                <select v-model="causer" class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                    <option value="">Todos os usuários</option>
                    <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <select v-model="entidade" class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                    <option value="">Todas as entidades</option>
                    <option v-for="e in entidades" :key="e" :value="e">{{ e }}</option>
                </select>
                <select v-model="acao" class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                    <option value="">Todas as ações</option>
                    <option v-for="a in acoes" :key="a.value" :value="a.value">{{ a.label }}</option>
                </select>
                <label class="flex items-center gap-1 text-xs text-muted-foreground">
                    De <Input v-model="de" type="date" class="h-9 w-[9.5rem]" />
                </label>
                <label class="flex items-center gap-1 text-xs text-muted-foreground">
                    Até <Input v-model="ate" type="date" class="h-9 w-[9.5rem]" />
                </label>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="w-8 px-2 py-3"></th>
                            <th class="px-4 py-3">Quando</th>
                            <th class="px-4 py-3">Usuário</th>
                            <th class="px-4 py-3">Ação</th>
                            <th class="px-4 py-3">Entidade</th>
                            <th class="px-4 py-3">Registro</th>
                            <th class="px-4 py-3">Alterações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="atividades.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">Nenhum registro de auditoria encontrado.</td>
                        </tr>
                        <template v-for="a in atividades.data" :key="a.id">
                            <tr class="transition-colors hover:bg-muted/30" :class="a.mudancas.length ? 'cursor-pointer' : ''" @click="a.mudancas.length && toggle(a.id)">
                                <td class="px-2 py-3 text-muted-foreground">
                                    <component :is="expandido.has(a.id) ? ChevronDown : ChevronRight" v-if="a.mudancas.length" class="size-4" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">{{ formatWhen(a.quando) }}</td>
                                <td class="px-4 py-3 font-medium">{{ a.usuario }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', acaoClass(a.acao_key)]">{{ a.acao }}</span>
                                </td>
                                <td class="px-4 py-3">{{ a.entidade ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-muted-foreground">#{{ a.registro_id ?? '—' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ a.mudancas.length ? `${a.mudancas.length} campo${a.mudancas.length !== 1 ? 's' : ''}` : '—' }}
                                </td>
                            </tr>
                            <tr v-if="expandido.has(a.id) && a.mudancas.length" class="bg-muted/20">
                                <td></td>
                                <td colspan="6" class="px-4 py-3">
                                    <table class="w-full text-xs">
                                        <thead class="text-left text-muted-foreground">
                                            <tr>
                                                <th class="py-1 pr-4 font-medium">Campo</th>
                                                <th class="py-1 pr-4 font-medium">De</th>
                                                <th class="py-1 font-medium">Para</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="m in a.mudancas" :key="m.campo" class="border-t border-border/50">
                                                <td class="py-1 pr-4 font-mono">{{ m.campo }}</td>
                                                <td class="py-1 pr-4 text-rose-600 dark:text-rose-400">{{ m.de }}</td>
                                                <td class="py-1 text-emerald-600 dark:text-emerald-400">{{ m.para }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="atividades.total > 0">{{ atividades.from }}–{{ atividades.to }} de {{ atividades.total }} registro{{ atividades.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="atividades.last_page > 1" class="flex flex-wrap gap-1">
                        <!-- eslint-disable-next-line vue/no-v-text-v-html-on-component -->
                        <Link v-for="(link, i) in atividades.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-sky-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
