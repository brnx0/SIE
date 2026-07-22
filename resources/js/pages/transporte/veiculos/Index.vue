<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import DocVencimentoBadge from '@/components/transporte/DocVencimentoBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { Veiculo } from '@/types/transporte';
import { Head, Link, router } from '@inertiajs/vue3';
import { Accessibility, Bus, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    veiculos: Paginated<Veiculo>;
    tipos: Record<string, string>;
    filters: { search: string; tipo: string; per_page: number };
}>();

const search  = ref(props.filters.search ?? '');
const tipo    = ref(props.filters.tipo ?? '');
const perPage = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = { search: search.value, tipo: tipo.value, per_page: perPage.value };
    if (!resetPage) params.page = props.veiculos.current_page;
    router.get('/transporte/veiculos', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([tipo, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Veículos', href: '/transporte/veiculos' }];

const remove = (vei: Veiculo) => {
    if (!confirm(`Remover veículo de placa "${vei.vei_placa}"?`)) return;
    router.delete(`/transporte/veiculos/${vei.vei_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Veículos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Veículos</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Frota do transporte escolar — dados e documentação.</p>
                </div>
                <Link v-if="!isReadOnly" href="/transporte/veiculos/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Novo veículo
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por placa, marca, modelo..." class="pl-9" />
                </div>
                <select v-model="tipo" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todos os tipos</option>
                    <option v-for="(label, key) in tipos" :key="key" :value="key">{{ label }}</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/transporte/veiculos/export" :filters="{ search, tipo }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Veículo</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3 text-center">Capacidade</th>
                            <th class="px-4 py-3">Documentação</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="veiculos.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">Nenhum veículo cadastrado.</td>
                        </tr>
                        <tr v-for="vei in veiculos.data" :key="vei.vei_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <Bus class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="flex items-center gap-1.5 font-medium">
                                            {{ vei.vei_placa }}
                                            <Accessibility v-if="vei.vei_fl_acessivel" class="size-3.5 text-indigo-500" title="Veículo acessível" />
                                        </span>
                                        <span class="text-xs text-muted-foreground">
                                            <template v-if="vei.vei_marca || vei.vei_modelo">{{ [vei.vei_marca, vei.vei_modelo].filter(Boolean).join(' ') }}</template>
                                            <template v-if="vei.vei_ano_fabricacao"> · {{ vei.vei_ano_fabricacao }}</template>
                                            <template v-if="vei.vei_fl_terceirizado"> · Terceirizado</template>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ tipos[vei.vei_tipo] ?? vei.vei_tipo }}</td>
                            <td class="px-4 py-3 text-center">{{ vei.vei_capacidade }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <DocVencimentoBadge :date="vei.vei_dt_licenciamento" label="Lic." />
                                    <DocVencimentoBadge :date="vei.vei_dt_seguro" label="Seg." />
                                    <DocVencimentoBadge :date="vei.vei_dt_vistoria" label="Vist." />
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', vei.vei_fl_ativo ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                    {{ vei.vei_fl_ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/transporte/veiculos/${vei.vei_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button v-if="!isReadOnly" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(vei)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="veiculos.total > 0">{{ veiculos.from }}–{{ veiculos.to }} de {{ veiculos.total }} registro{{ veiculos.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="veiculos.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in veiculos.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
