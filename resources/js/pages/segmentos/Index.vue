<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { Segmento } from '@/types/segmento';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    segmentos: Paginated<Segmento>;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');
let timer: ReturnType<typeof setTimeout> | null = null;
const apply = () => router.get('/segmentos', { search: search.value }, { preserveState: true, replace: true });
watch(search, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(apply, 300);
});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Segmentos', href: '/segmentos' }];

const remove = (seg: Segmento) => {
    if (!confirm(`Remover segmento "${seg.seg_nome_reduzido}"?`)) return;
    router.delete(`/segmentos/${seg.seg_id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Segmentos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Segmentos</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Gerencie os segmentos da rede de ensino.</p>
                </div>
                <Link href="/segmentos/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        <Plus class="mr-2 size-4" /> Novo segmento
                    </Button>
                </Link>
            </div>

            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Buscar por nome ou INEP..." class="pl-9" />
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Segmento</th>
                            <th class="px-4 py-3">Código INEP</th>
                            <th class="px-4 py-3 text-center">Anos</th>
                            <th class="px-4 py-3 text-center">Ordem</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="segmentos.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">Nenhum segmento cadastrado.</td>
                        </tr>
                        <tr v-for="seg in segmentos.data" :key="seg.seg_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        <BookOpen class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ seg.seg_nome_reduzido }}</span>
                                        <span class="text-xs text-muted-foreground">{{ seg.seg_nome_completo }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ seg.seg_cd_inep ?? '—' }}</td>
                            <td class="px-4 py-3 text-center tabular-nums">{{ seg.seg_qt_anos_escolares }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-muted-foreground">{{ seg.seg_ordem }}</td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        seg.seg_fl_ativo
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                                    ]"
                                >
                                    {{ seg.seg_fl_ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/segmentos/${seg.seg_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                        @click="remove(seg)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="segmentos.last_page > 1" class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">{{ segmentos.from }}–{{ segmentos.to }} de {{ segmentos.total }}</span>
                    <div class="flex flex-wrap gap-1">
                        <Link
                            v-for="(link, i) in segmentos.links"
                            :key="i"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            :class="[
                                'rounded-md px-3 py-1 text-xs',
                                link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted',
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
