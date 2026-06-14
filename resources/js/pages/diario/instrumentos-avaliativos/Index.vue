<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { InstrumentoAvaliativo } from '@/types/instrumento-avaliativo';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardCheck, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    instrumentos: Paginated<InstrumentoAvaliativo>;
    filters: { search: string; per_page: number };
}>();

const search  = ref(props.filters.search ?? '');
const perPage = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = { search: search.value, per_page: perPage.value };
    if (!resetPage) params.page = props.instrumentos.current_page;
    router.get('/diario/instrumentos-avaliativos', params, { preserveState: true, replace: true });
};
watch(search,  () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch(perPage, () => apply(true));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Instrumentos Avaliativos', href: '/diario/instrumentos-avaliativos' },
];

const remove = (iav: InstrumentoAvaliativo) => {
    if (!confirm(`Remover instrumento "${iav.iav_nome}"?`)) return;
    router.delete(`/diario/instrumentos-avaliativos/${iav.iav_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Instrumentos Avaliativos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Instrumentos Avaliativos</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Cadastre os instrumentos usados no lançamento de notas.</p>
                </div>
                <Link href="/diario/instrumentos-avaliativos/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Novo instrumento
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por nome..." class="pl-9" />
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/diario/instrumentos-avaliativos/export" :filters="{ search }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Instrumento</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="instrumentos.data.length === 0">
                            <td colspan="3" class="px-4 py-12 text-center text-muted-foreground">Nenhum instrumento cadastrado.</td>
                        </tr>
                        <tr v-for="iav in instrumentos.data" :key="iav.iav_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <ClipboardCheck class="size-4" />
                                    </div>
                                    <span class="font-medium">{{ iav.iav_nome }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', iav.iav_fl_ativo ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                    {{ iav.iav_fl_ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/diario/instrumentos-avaliativos/${iav.iav_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(iav)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="instrumentos.total > 0">{{ instrumentos.from }}–{{ instrumentos.to }} de {{ instrumentos.total }} registro{{ instrumentos.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="instrumentos.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in instrumentos.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
