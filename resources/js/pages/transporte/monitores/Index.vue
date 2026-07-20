<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { MonitorTransporte } from '@/types/transporte';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, UserCheck } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    monitores: Paginated<MonitorTransporte>;
    filters: { search: string; per_page: number };
}>();

const search  = ref(props.filters.search ?? '');
const perPage = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = { search: search.value, per_page: perPage.value };
    if (!resetPage) params.page = props.monitores.current_page;
    router.get('/transporte/monitores', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch(perPage, () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Monitores', href: '/transporte/monitores' }];

const fmtCpf = (cpf: string | null) =>
    cpf && cpf.length === 11 ? `${cpf.slice(0, 3)}.${cpf.slice(3, 6)}.${cpf.slice(6, 9)}-${cpf.slice(9)}` : (cpf ?? '');

const remove = (tmo: MonitorTransporte) => {
    if (!confirm(`Remover monitor "${tmo.tmo_nome}"?`)) return;
    router.delete(`/transporte/monitores/${tmo.tmo_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Monitores" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Monitores</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Responsáveis pelo acompanhamento dos alunos durante o transporte.</p>
                </div>
                <Link v-if="!isReadOnly" href="/transporte/monitores/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Novo monitor
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por nome ou CPF..." class="pl-9" />
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/transporte/monitores/export" :filters="{ search }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Monitor</th>
                            <th class="px-4 py-3">Telefone</th>
                            <th class="px-4 py-3">Vínculo funcional</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="monitores.data.length === 0">
                            <td colspan="5" class="px-4 py-12 text-center text-muted-foreground">Nenhum monitor cadastrado.</td>
                        </tr>
                        <tr v-for="tmo in monitores.data" :key="tmo.tmo_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <UserCheck class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ tmo.tmo_nome }}</span>
                                        <span class="text-xs text-muted-foreground">{{ fmtCpf(tmo.tmo_cpf) || '—' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ tmo.tmo_telefone || '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ tmo.funcionario?.fun_nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', tmo.tmo_fl_ativo ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                    {{ tmo.tmo_fl_ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/transporte/monitores/${tmo.tmo_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button v-if="!isReadOnly" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(tmo)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="monitores.total > 0">{{ monitores.from }}–{{ monitores.to }} de {{ monitores.total }} registro{{ monitores.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="monitores.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in monitores.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
