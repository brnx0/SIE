<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { Escola } from '@/types/escola';
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    escolas: Paginated<Escola>;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');
let timer: ReturnType<typeof setTimeout> | null = null;
const apply = () => router.get('/escolas', { search: search.value }, { preserveState: true, replace: true });
watch(search, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(apply, 300);
});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Escolas', href: '/escolas' }];

const remove = (escola: Escola) => {
    if (!confirm(`Remover escola ${escola.esc_apelido || escola.esc_nome}?`)) return;
    router.delete(`/escolas/${escola.esc_id}`, { preserveScroll: true });
};

const formatCnpj = (cnpj: string | null) => {
    if (!cnpj) return '—';
    return cnpj.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
};

const situacaoLabel = (s: number) => ({ 1: 'Em atividade', 2: 'Paralisada', 3: 'Extinta' }[s] ?? '—');
const situacaoClass = (s: number) => {
    switch (s) {
        case 1: return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
        case 2: return 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 3: return 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300';
        default: return 'bg-muted text-muted-foreground';
    }
};
</script>

<template>
    <Head title="Escolas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Escolas</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Gerencie as unidades escolares.</p>
                </div>
                <Link href="/escolas/create">
                    <Button class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400">
                        <Plus class="mr-2 size-4" /> Nova escola
                    </Button>
                </Link>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por nome, apelido, INEP ou CNPJ..." class="pl-9" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Escola</th>
                            <th class="px-4 py-3">INEP</th>
                            <th class="px-4 py-3">CNPJ</th>
                            <th class="px-4 py-3">Cidade / UF</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="escolas.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">Nenhuma escola cadastrada.</td>
                        </tr>
                        <tr v-for="esc in escolas.data" :key="esc.esc_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center overflow-hidden rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300">
                                        <img v-if="esc.esc_logo_url" :src="esc.esc_logo_url" :alt="esc.esc_nome" class="size-full object-cover" />
                                        <Building2 v-else class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ esc.esc_apelido }}</span>
                                        <span class="text-xs text-muted-foreground">{{ esc.esc_nome }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ esc.esc_cd_inep }}</td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ formatCnpj(esc.esc_cnpj) }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <span v-if="esc.municipio">{{ esc.municipio.mun_nome }} / {{ esc.municipio.mun_uf }}</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', situacaoClass(esc.esc_situacao_func)]">
                                    {{ situacaoLabel(esc.esc_situacao_func) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/escolas/${esc.esc_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(esc)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="escolas.last_page > 1" class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">{{ escolas.from }}–{{ escolas.to }} de {{ escolas.total }}</span>
                    <div class="flex flex-wrap gap-1">
                        <Link
                            v-for="(link, i) in escolas.links"
                            :key="i"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            :class="[
                                'rounded-md px-3 py-1 text-xs',
                                link.active ? 'bg-sky-600 text-white' : 'border bg-background hover:bg-muted',
                                !link.url && 'pointer-events-none opacity-40',
                            ]"
                            preserve-scroll
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
