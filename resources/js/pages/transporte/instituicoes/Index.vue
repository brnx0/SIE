<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { InstituicaoTransporte } from '@/types/transporte';
import { Head, Link, router } from '@inertiajs/vue3';
import { Landmark, MapPin, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    instituicoes: Paginated<InstituicaoTransporte>;
    esferas: Record<string, string>;
    filters: { search: string; esfera: string; per_page: number };
}>();

const search  = ref(props.filters.search ?? '');
const esfera  = ref(props.filters.esfera ?? '');
const perPage = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = { search: search.value, esfera: esfera.value, per_page: perPage.value };
    if (!resetPage) params.page = props.instituicoes.current_page;
    router.get('/transporte/instituicoes', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([esfera, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Instituições', href: '/transporte/instituicoes' }];

const remove = (tin: InstituicaoTransporte) => {
    if (!confirm(`Remover instituição "${tin.tin_nome}"?`)) return;
    router.delete(`/transporte/instituicoes/${tin.tin_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Instituições" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Instituições</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Instituições de ensino atendidas pelo transporte escolar.</p>
                </div>
                <Link v-if="!isReadOnly" href="/transporte/instituicoes/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Nova instituição
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por nome ou endereço..." class="pl-9" />
                </div>
                <select v-model="esfera" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as esferas</option>
                    <option v-for="(label, key) in esferas" :key="key" :value="key">{{ label }}</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/transporte/instituicoes/export" :filters="{ search, esfera }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Instituição</th>
                            <th class="px-4 py-3">Esfera</th>
                            <th class="px-4 py-3">Escola da rede</th>
                            <th class="px-4 py-3">Bairro</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="instituicoes.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">Nenhuma instituição cadastrada.</td>
                        </tr>
                        <tr v-for="tin in instituicoes.data" :key="tin.tin_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <Landmark class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="flex items-center gap-1.5 font-medium">
                                            {{ tin.tin_nome }}
                                            <MapPin v-if="tin.tin_latitude != null && tin.tin_longitude != null" class="size-3.5 text-emerald-600" title="Georreferenciada" />
                                        </span>
                                        <span class="text-xs text-muted-foreground">{{ tin.tin_endereco || '—' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ esferas[tin.tin_esfera] ?? tin.tin_esfera }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ tin.escola?.esc_nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ tin.bairro?.bai_nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', tin.tin_fl_ativo ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                    {{ tin.tin_fl_ativo ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/transporte/instituicoes/${tin.tin_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button v-if="!isReadOnly" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(tin)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="instituicoes.total > 0">{{ instituicoes.from }}–{{ instituicoes.to }} de {{ instituicoes.total }} registro{{ instituicoes.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="instituicoes.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in instituicoes.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
