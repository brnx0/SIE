<script setup lang="ts">
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { PlanoAee, PlanoStatus } from '@/types/diario';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    planos: Paginated<PlanoAee>;
    filters: { search: string; status: string; tur_id: string; per_page: number };
    statuses: PlanoStatus[];
}>();

const search = ref(props.filters.search ?? '');
const status = ref<string>(props.filters.status ?? '');
const perPage = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        search: search.value,
        status: status.value,
        per_page: perPage.value,
    };
    if (!resetPage) params.page = props.planos.current_page;
    router.get('/diario/planos-aee', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([status, perPage], () => apply(true));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Plano de Aula AEE', href: '/diario/planos-aee' },
];

const remove = (p: PlanoAee) => {
    if (p.dae_status !== 'pendente') {
        alert('Plano só pode ser excluído enquanto estiver pendente.');
        return;
    }
    if (!confirm(`Remover plano "${p.dae_tema}"?`)) return;
    router.delete(`/diario/planos-aee/${p.dae_id}`, { preserveScroll: true });
};

const statusBadge = (s: PlanoStatus) => ({
    pendente: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    aprovado: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    reprovado: 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
    correcao: 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
}[s]);

const statusLabel = (s: PlanoStatus) => ({
    pendente: 'Pendente',
    aprovado: 'Aprovado',
    reprovado: 'Reprovado',
    correcao: 'Em correção',
}[s]);

const fmtDate = (d: string) => {
    if (!d) return '—';
    const [y, m, day] = d.substring(0, 10).split('-');
    return `${day}/${m}/${y}`;
};
</script>

<template>
    <Head title="Planos de Aula AEE" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Planos de Aula AEE</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Diário online — planejamento de intervenções no AEE.</p>
                </div>
                <Link href="/diario/planos-aee/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Novo plano
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por tema..." class="pl-9" />
                </div>
                <select v-model="status" class="rounded-md border bg-background px-3 py-2 text-sm">
                    <option value="">Todos os status</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel(s) }}</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Tema</th>
                            <th class="px-4 py-3">Turma</th>
                            <th class="px-4 py-3">Escola</th>
                            <th class="px-4 py-3">Período</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="planos.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">Nenhum plano cadastrado.</td>
                        </tr>
                        <tr v-for="p in planos.data" :key="p.dae_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/50 dark:text-fuchsia-300">
                                        <BookOpen class="size-4" />
                                    </div>
                                    <span class="font-medium">{{ p.dae_tema }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ p.turma?.tur_nome }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ p.escola?.esc_nome }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground tabular-nums">
                                {{ fmtDate(p.dae_dt_inicio) }} – {{ fmtDate(p.dae_dt_fim) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadge(p.dae_status)]">
                                    {{ statusLabel(p.dae_status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/diario/planos-aee/${p.dae_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button v-if="p.dae_status === 'pendente'" variant="ghost" size="sm"
                                        class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                        @click="remove(p)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="planos.total > 0">{{ planos.from }}–{{ planos.to }} de {{ planos.total }} registro{{ planos.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="planos.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in planos.links" :key="i" :href="link.url ?? '#'" v-html="link.label"
                            :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']"
                            preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
