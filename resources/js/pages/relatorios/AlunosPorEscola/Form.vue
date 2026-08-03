<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Users, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }

const props = defineProps<{
    anoExercicio: AnoLetivo | null;
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alunos por Escola', href: '/relatorios/alunos-por-escola' },
];

const escId = ref<number | null>(props.userEscola?.esc_id ?? null);
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const gerando = ref(false);

function gerar() {
    if (!escId.value) return;
    gerando.value = true;
    router.get('/relatorios/alunos-por-escola/gerar', { esc_id: Number(escId.value) }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Alunos por Escola" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <Users class="size-5 text-indigo-600" /> Alunos por Escola
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Lista alfabética dos alunos com matrícula ativa, no ano letivo em exercício.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel>Ano Letivo (em exercício)</FormLabel>
                    <div class="rounded-md border bg-muted/40 px-3 py-2 text-sm">
                        {{ anoExercicio?.anl_ano ?? 'Nenhum ano em exercício definido' }}
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" :disabled="!isAdmin && !!userEscola" placeholder="Selecione a escola..." />
                </div>

                <div class="sm:col-span-2 flex justify-end">
                    <Button :disabled="!escId || !anoExercicio || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
