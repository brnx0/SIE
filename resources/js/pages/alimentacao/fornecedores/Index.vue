<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { Fornecedor } from '@/types/alimentacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, Truck } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    fornecedores: Paginated<Fornecedor>;
    filters: { search: string; tipo: string; situacao: string; per_page: number };
}>();

const search   = ref(props.filters.search ?? '');
const tipo     = ref(props.filters.tipo ?? '');
const situacao = ref(props.filters.situacao ?? '');
const perPage  = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        search: search.value, tipo: tipo.value, situacao: situacao.value, per_page: perPage.value,
    };
    if (!resetPage) params.page = props.fornecedores.current_page;
    router.get('/alimentacao/fornecedores', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([tipo, situacao, perPage], () => apply(true));

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Fornecedores', href: '/alimentacao/fornecedores' }];

// Guardados sem máscara; a máscara é de exibição.
// O CNPJ alfanumérico (jul/2026) tem [0-9A-Z] nas 12 primeiras posições e só o
// DV numérico — casar com \d deixaria esses CNPJs sem formatação nenhuma.
const documento = (f: Fornecedor): string => {
    if (f.frn_cnpj) return f.frn_cnpj.replace(/^([0-9A-Z]{2})([0-9A-Z]{3})([0-9A-Z]{3})([0-9A-Z]{4})(\d{2})$/, '$1.$2.$3/$4-$5');
    if (f.frn_cpf) return f.frn_cpf.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
    return '—';
};

// Guardado só com dígitos: fixo (10) vira (11) 3456-7890, celular (11) vira (11) 98765-4321.
const fone = (v: string | null): string => {
    const d = (v ?? '').replace(/\D/g, '');
    if (d.length === 11) return d.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    if (d.length === 10) return d.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    return v ?? '';
};

const remove = (f: Fornecedor) => {
    if (!confirm(`Remover o fornecedor "${f.frn_razao_social}"?`)) return;
    router.delete(`/alimentacao/fornecedores/${f.frn_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Fornecedores" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Fornecedores</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Fornecedores de gêneros alimentícios, incluindo os da agricultura familiar.
                    </p>
                </div>
                <Link v-if="!isReadOnly" href="/alimentacao/fornecedores/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Novo fornecedor
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por razão social, fantasia, CNPJ ou CPF..." class="pl-9" />
                </div>
                <select v-model="tipo" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todos os tipos</option>
                    <option value="agricultura_familiar">Agricultura familiar</option>
                    <option value="pj">Pessoa jurídica (CNPJ)</option>
                    <option value="pf">Pessoa física (CPF)</option>
                </select>
                <select v-model="situacao" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as situações</option>
                    <option value="ativo">Ativos</option>
                    <option value="inativo">Inativos</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/alimentacao/fornecedores/export" :filters="{ search, tipo, situacao }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Fornecedor</th>
                            <th class="px-4 py-3">Documento</th>
                            <th class="px-4 py-3">Contato</th>
                            <th class="px-4 py-3">Município</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="fornecedores.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">Nenhum fornecedor cadastrado.</td>
                        </tr>
                        <tr v-for="f in fornecedores.data" :key="f.frn_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <Truck class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ f.frn_razao_social }}</span>
                                        <span class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <template v-if="f.frn_nome_fantasia">{{ f.frn_nome_fantasia }}</template>
                                            <span v-if="f.frn_fl_agricultura_familiar" class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                Agric. familiar
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ documento(f) }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div class="flex flex-col">
                                    <span v-if="f.frn_email">{{ f.frn_email }}</span>
                                    <span v-if="f.frn_fone" class="text-xs">{{ fone(f.frn_fone) }}</span>
                                    <span v-if="!f.frn_email && !f.frn_fone">—</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ f.municipio ? `${f.municipio.mun_nome}/${f.municipio.mun_uf}` : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', f.frn_fl_ativo ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                    {{ f.frn_fl_ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/alimentacao/fornecedores/${f.frn_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button v-if="!isReadOnly" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(f)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="fornecedores.total > 0">{{ fornecedores.from }}–{{ fornecedores.to }} de {{ fornecedores.total }} registro{{ fornecedores.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="fornecedores.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in fornecedores.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
