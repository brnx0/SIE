<script setup lang="ts">
import UserForm from '@/components/usuario/UserForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{ roles: Record<string, string> }>();

const breadcrumbs: BreadcrumbItem[] = [

    { title: 'Usuários', href: '/users' },
    { title: 'Novo', href: '/users/create' },
];
</script>

<template>
    <Head title="Novo funcionário" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Cadastrar funcionário</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Preencha os dados para criar um novo acesso ao sistema.</p>
            </div>
            <UserForm mode="create" :roles="roles" />
        </div>
    </AppLayout>
</template>
