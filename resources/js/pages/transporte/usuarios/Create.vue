<script setup lang="ts">
import UsuarioExternoForm from '@/components/transporte/UsuarioExternoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Usuários do Transporte', href: '/transporte/usuarios' },
    { title: 'Novo usuário', href: '/transporte/usuarios/create' },
];
</script>

<template>
    <Head title="Novo usuário do transporte" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Novo usuário do transporte</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Cadastre um usuário externo com justificativa de utilização.</p>
            </div>
            <UsuarioExternoForm mode="create" />
        </div>
    </AppLayout>
</template>
