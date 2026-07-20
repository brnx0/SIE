<script setup lang="ts">
import UsuarioExternoForm from '@/components/transporte/UsuarioExternoForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { UsuarioTransporte } from '@/types/transporte';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    usuario: UsuarioTransporte;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Usuários do Transporte', href: '/transporte/usuarios' },
    { title: props.usuario.tue_nome, href: `/transporte/usuarios/${props.usuario.tue_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${usuario.tue_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar usuário do transporte</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ usuario.tue_nome }}</p>
            </div>
            <UsuarioExternoForm mode="edit" :initial="usuario" />
        </div>
    </AppLayout>
</template>
