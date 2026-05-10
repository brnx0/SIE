<script setup lang="ts">
import UserForm from '@/components/usuario/UserForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type User } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    user: User;
    roles: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Usuários', href: '/users' },
    { title: props.user.name, href: `/users/${props.user.id}/edit` },
];
</script>

<template>
    <Head :title="`Editar — ${user.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar funcionário</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Atualize informações de acesso e perfil de {{ user.name }}.</p>
            </div>
            <UserForm
                mode="edit"
                :roles="roles"
                :initial="{
                    id: user.id,
                    name: user.name,
                    email: user.email,
                    role: user.role,
                    phone: user.phone ?? '',
                    active: user.active ?? true,
                }"
            />
        </div>
    </AppLayout>
</template>
