<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { CursoFormacaoSuperiorCadastro, NivelGraduacao } from '@/types/formacao';
import { Head, Link, router } from '@inertiajs/vue3';
import { GraduationCap, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    cursos: Paginated<CursoFormacaoSuperiorCadastro>;
    niveis: Pick<NivelGraduacao, 'ngr_id' | 'ngr_nome'>[];
    filters: { search: string; ngr_id: string; per_page: number };
}>();

const search  = ref(props.filters.search ?? '');
const ngrId   = ref(props.filters.ngr_id ?? '');
const perPage = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = () => {
    router.get('/cursos-formacao-superior', { search: search.value, ngr_id: ngrId.value, per_page: perPage.value }, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(apply, 300); });
watch([ngrId, perPage], apply);

const { isReadOnly } = usePermissao();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Curso de Formação Superior', href: '/cursos-formacao-superior' }];

const remove = (c: CursoFormacaoSuperiorCadastro) => {
    if (!confirm(`Remover o curso "${c.cfs_nome}" (${c.cfs_codigo})?`)) return;
    router.delete(`/cursos-formacao-superior/${c.cfs_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Curso de Formação Superior" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Curso de Formação Superior</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Cursos de graduação com os códigos oficiais do INEP, usados na formação de professores e funcionários.</p>
                </div>
                <Link v-if="!isReadOnly" href="/cursos-formacao-superior/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Novo curso
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative max-w-sm flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por curso, área ou código INEP..." class="pl-9" />
                </div>
                <select v-model="ngrId" class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                    <option value="">Todos os níveis</option>
                    <option v-for="n in niveis" :key="n.ngr_id" :value="String(n.ngr_id)">{{ n.ngr_nome }}</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/cursos-formacao-superior/export" :filters="{ search, ngr_id: ngrId }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Curso</th>
                            <th class="px-4 py-3">Área de Conhecimento</th>
                            <th class="px-4 py-3">Nível</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="cursos.data.length === 0">
                            <td colspan="5" class="px-4 py-12 text-center text-muted-foreground">Nenhum curso encontrado.</td>
                        </tr>
                        <tr v-for="c in cursos.data" :key="c.cfs_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <GraduationCap class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ c.cfs_nome }}</span>
                                        <span class="font-mono text-xs text-muted-foreground">{{ c.cfs_codigo }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <span v-if="c.cfs_cd_area" class="mr-1 font-mono text-xs">{{ c.cfs_cd_area }}</span>
                                {{ c.cfs_area_detalhada ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ c.nivel?.ngr_nome ?? c.cfs_grau ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', c.cfs_fl_ativo ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                                    {{ c.cfs_fl_ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/cursos-formacao-superior/${c.cfs_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button v-if="!isReadOnly" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(c)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="cursos.total > 0">{{ cursos.from }}–{{ cursos.to }} de {{ cursos.total }} registro{{ cursos.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="cursos.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in cursos.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
