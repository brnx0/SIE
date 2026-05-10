<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { Aluno } from '@/types/aluno';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    alunos: Paginated<Aluno>;
    filters: { search: string };
}>();

const page = usePage<{ flash: { success?: string; error?: string } }>();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const search = ref(props.filters.search ?? '');

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = () => router.get('/alunos', { search: search.value }, { preserveState: true, replace: true });
watch(search, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(apply, 300);
});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Alunos', href: '/alunos' }];

const remove = (aluno: Aluno) => {
    if (!confirm(`Remover aluno ${aluno.aln_nome}?`)) return;
    router.delete(`/alunos/${aluno.aln_id}`, { preserveScroll: true });
};

const formatCpf = (cpf: string | null) => {
    if (!cpf) return '—';
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
};

const formatDate = (iso: string) => {
    if (!iso) return '';
    const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : iso;
};
</script>

<template>
    <Head title="Alunos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div v-if="flashSuccess" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-300">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-300">
                {{ flashError }}
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Alunos</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Gerencie os cadastros de alunos da instituição.</p>
                </div>
                <Link href="/alunos/create">
                    <Button class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400">
                        <Plus class="mr-2 size-4" /> Novo aluno
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
                            <th class="px-4 py-3">Nascimento</th>
                            <th class="px-4 py-3">Município</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="alunos.data.length === 0">
                            <td colspan="5" class="px-4 py-12 text-center text-muted-foreground">Nenhum aluno cadastrado.</td>
                        </tr>
                        <tr v-for="aluno in alunos.data" :key="aluno.aln_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center overflow-hidden rounded-full bg-sky-100 text-xs font-semibold text-sky-700 dark:bg-sky-900/50 dark:text-sky-300">
                                        <img v-if="aluno.aln_foto_url" :src="aluno.aln_foto_url" :alt="aluno.aln_nome" class="size-full object-cover" />
                                        <span v-else>{{ aluno.aln_nome.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase() }}</span>
                                    </div>
                                    <span>{{ aluno.aln_nome }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground tabular-nums">{{ formatCpf(aluno.aln_cpf) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ formatDate(aluno.aln_dt_nascimento) }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <span v-if="aluno.municipio_nascimento">
                                    {{ aluno.municipio_nascimento.mun_nome }} — {{ aluno.municipio_nascimento.mun_uf }}
                                </span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/alunos/${aluno.aln_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(aluno)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="alunos.last_page > 1" class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">{{ alunos.from }}–{{ alunos.to }} de {{ alunos.total }}</span>
                    <div class="flex flex-wrap gap-1">
                        <Link
                            v-for="(link, i) in alunos.links"
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
