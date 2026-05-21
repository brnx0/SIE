<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { Funcionario } from '@/types/funcionario';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    funcionarios: Paginated<Funcionario>;
    filters: { search: string };
}>();

const search = ref(props.filters.search ?? '');

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = () => router.get('/funcionarios', { search: search.value }, { preserveState: true, replace: true });
watch(search, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(apply, 300);
});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Funcionários', href: '/funcionarios' }];

const remove = (f: Funcionario) => {
    if (!confirm(`Remover funcionário ${f.fun_nome}?`)) return;
    router.delete(`/funcionarios/${f.fun_id}`, { preserveScroll: true });
};

const formatCpf = (cpf: string | null) => {
    if (!cpf) return '—';
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
};
</script>

<template>
    <Head title="Funcionários" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Funcionários</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Gerencie os cadastros de funcionários.</p>
                </div>
                <Link href="/funcionarios/create">
                    <Button class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400">
                        <Plus class="mr-2 size-4" /> Novo funcionário
                    </Button>
                </Link>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por nome ou CPF..." class="pl-9" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">CPF</th>
                            <th class="px-4 py-3">Naturalidade</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="funcionarios.data.length === 0">
                            <td colspan="5" class="px-4 py-12 text-center text-muted-foreground">Nenhum funcionário cadastrado.</td>
                        </tr>
                        <tr v-for="f in funcionarios.data" :key="f.fun_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center overflow-hidden rounded-full bg-sky-100 text-xs font-semibold text-sky-700 dark:bg-sky-900/50 dark:text-sky-300">
                                        <img v-if="f.fun_foto_url" :src="f.fun_foto_url" :alt="f.fun_nome" class="size-full object-cover" />
                                        <span v-else>{{ f.fun_nome.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase() }}</span>
                                    </div>
                                    <span>{{ f.fun_nome }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground tabular-nums">{{ formatCpf(f.fun_cpf) }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <span v-if="f.municipio_nascimento">
                                    {{ f.municipio_nascimento.mun_nome }} — {{ f.municipio_nascimento.mun_uf }}
                                </span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="f.fun_fl_ativo
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'"
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                >
                                    {{ f.fun_fl_ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/funcionarios/${f.fun_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(f)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="funcionarios.last_page > 1" class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">{{ funcionarios.from }}–{{ funcionarios.to }} de {{ funcionarios.total }}</span>
                    <div class="flex flex-wrap gap-1">
                        <Link
                            v-for="(link, i) in funcionarios.links"
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
