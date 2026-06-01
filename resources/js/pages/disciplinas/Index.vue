<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { AreaConhecimento, Disciplina } from '@/types/disciplina';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    disciplinas: Paginated<Disciplina>;
    areas: Pick<AreaConhecimento, 'arc_id' | 'arc_nome'>[];
    filters: { search: string; arc_id: string; per_page: number };
}>();

const search  = ref(props.filters.search ?? '');
const arcId   = ref(props.filters.arc_id ?? '');
const perPage = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = { search: search.value, arc_id: arcId.value, per_page: perPage.value };
    if (!resetPage) params.page = props.disciplinas.current_page;
    router.get('/disciplinas', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([arcId, perPage], () => apply(true));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Disciplinas', href: '/disciplinas' }];

const remove = (dis: Disciplina) => {
    if (!confirm(`Remover disciplina "${dis.dis_nome}"?`)) return;
    router.delete(`/disciplinas/${dis.dis_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Disciplinas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Disciplinas</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Gerencie as disciplinas da rede de ensino.</p>
                </div>
                <Link href="/disciplinas/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Nova disciplina
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por nome..." class="pl-9" />
                </div>
                <select v-model="arcId" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todas as áreas</option>
                    <option v-for="area in areas" :key="area.arc_id" :value="String(area.arc_id)">{{ area.arc_nome }}</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/disciplinas/export" :filters="{ search, arc_id: arcId }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Disciplina</th>
                            <th class="px-4 py-3">Área do Conhecimento</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="disciplinas.data.length === 0">
                            <td colspan="4" class="px-4 py-12 text-center text-muted-foreground">Nenhuma disciplina cadastrada.</td>
                        </tr>
                        <tr v-for="dis in disciplinas.data" :key="dis.dis_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <BookOpen class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ dis.dis_nome }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            <template v-if="dis.dis_nome_mec !== dis.dis_nome">{{ dis.dis_nome_mec }}</template>
                                            <template v-if="dis.dis_cod_ref"> · Ref: {{ dis.dis_cod_ref }}</template>
                                            <template v-if="dis.dis_sigla"> · {{ dis.dis_sigla }}</template>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ dis.areaConhecimento?.arc_nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', dis.dis_fl_ativo ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                    {{ dis.dis_fl_ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/disciplinas/${dis.dis_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(dis)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="disciplinas.total > 0">{{ disciplinas.from }}–{{ disciplinas.to }} de {{ disciplinas.total }} registro{{ disciplinas.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="disciplinas.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in disciplinas.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
