<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated, type User } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    users: Paginated<User>;
    filters: { search: string; role: string; per_page: number };
    roles: Record<string, string>;
}>();

const search  = ref(props.filters.search ?? '');
const role    = ref(props.filters.role ?? '');
const perPage = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = { search: search.value, role: role.value, per_page: perPage.value };
    if (!resetPage) params.page = props.users.current_page;
    router.get('/users', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([role, perPage], () => apply(true));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Usuários', href: '/users' }];

const roleClass = (key?: string) => {
    switch (key) {
        case 'admin': return 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300';
        case 'professor': return 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300';
        case 'colaborador': return 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        default: return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
    }
};

const remove = (user: User) => {
    if (!confirm(`Remover usuário ${user.name}?`)) return;
    router.delete(`/users/${user.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Usuários" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Usuários</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Gerencie professores, colaboradores e administradores do sistema.</p>
                </div>
                <Link href="/users/create">
                    <Button class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400">
                        <Plus class="mr-2 size-4" /> Novo usuário
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por nome, login ou e-mail..." class="pl-9" />
                </div>
                <select v-model="role" class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                    <option value="">Todos os perfis</option>
                    <option v-for="(label, key) in roles" :key="key" :value="key">{{ label }}</option>
                </select>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                    <ExportMenu base-url="/users/export" :filters="{ search, role }" />
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">Login</th>
                            <th class="px-4 py-3">E-mail</th>
                            <th class="px-4 py-3">Perfis</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">Nenhum usuário encontrado.</td>
                        </tr>
                        <tr v-for="user in users.data" :key="user.id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-9 place-items-center rounded-full bg-sky-100 text-xs font-semibold text-sky-700 dark:bg-sky-900/50 dark:text-sky-300">
                                        {{ user.name.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase() }}
                                    </div>
                                    <span class="font-medium">{{ user.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-sm text-muted-foreground">{{ (user as any).login }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ user.email || '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="r in (user.roles ?? [])"
                                        :key="r"
                                        :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', roleClass(r)]"
                                    >
                                        {{ roles[r] ?? r }}
                                    </span>
                                    <span v-if="!user.roles?.length" class="text-xs text-muted-foreground">—</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="user.active" class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                    <span class="size-1.5 rounded-full bg-emerald-500" /> Ativo
                                </span>
                                <span v-else class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                    <span class="size-1.5 rounded-full bg-muted-foreground" /> Inativo
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/users/${user.id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(user)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="users.total > 0">{{ users.from }}–{{ users.to }} de {{ users.total }} registro{{ users.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="users.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in users.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-sky-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
