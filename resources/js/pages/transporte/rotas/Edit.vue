<script setup lang="ts">
import RotaForm from '@/components/transporte/RotaForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivo } from '@/types/parametro';
import type { PassageiroRota, PontoRota, Rota } from '@/types/transporte';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    rota: Rota;
    pontos: PontoRota[];
    passageiros: PassageiroRota[];
    anosLetivos: AnoLetivo[];
    turnos: Record<string, string>;
    zonas: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rotas', href: '/transporte/rotas' },
    { title: props.rota.rot_nome, href: `/transporte/rotas/${props.rota.rot_id}/edit` },
];
</script>

<template>
    <Head :title="`Editar ${rota.rot_nome}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-2 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Editar rota</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ rota.rot_nome }}</p>
            </div>
            <RotaForm
                mode="edit"
                :initial="rota"
                :pontos="pontos"
                :passageiros="passageiros"
                :anos-letivos="anosLetivos"
                :turnos="turnos"
                :zonas="zonas"
            />
        </div>
    </AppLayout>
</template>
