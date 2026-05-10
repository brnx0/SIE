<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated, type User } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    users: Paginated<User>;
    filters: { search: string; role: string };
    roles: Record<string, string>;
}>();

const page = usePage<{ flash: { success?: string; error?: string } }>();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = () => {
    router.get('/users', { search: search.value, role: role.value }, { preserveState: true, replace: true });
};
watch(search, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(apply, 300);
});
watch(role, apply);

const breadcrumbs: BreadcrumbItem[] = [

    { title: 'Usuários', href: '/users' },
];

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
    <Head title="Funcionários" />
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
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Funcionários</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Gerencie professores, colaboradores e administradores do sistema.</p>
                </div>
                <Link href="/users/create">
                    <Button class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400">
                        <Plus class="mr-2 size-4" /> Novo funcionário
                    </Button>
                </Link>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Buscar por nome ou e-mail..." class="pl-9" />
                </div>
                <select
                    v-model="role"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring sm:w-56"
                >
                    <option value="">Todos os perfis</option>
                    <option v-for="(label, key) in roles" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">E-mail</th>
                            <th class="px-4 py-3">Perfil</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="users.data.length === 0">
                            <td colspan="5" class="px-4 py-12 text-center text-muted-foreground">Nenhum usuário encontrado.</td>
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
                            <td class="px-4 py-3 text-muted-foreground">{{ user.email }}</td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', roleClass(user.role)]">
                                    {{ roles[user.role ?? 'aluno'] ?? user.role }}
                                </span>
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

                <div v-if="users.last_page > 1" class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">{{ users.from }}–{{ users.to }} de {{ users.total }}</span>
                    <div class="flex flex-wrap gap-1">
                        <Link
                            v-for="(link, i) in users.links"
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
