<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Loader2, Table } from 'lucide-vue-next';
import { ref } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sumário de Matrículas', href: '/relatorios/sumario-matriculas' },
];

const anoDefault = props.anosLetivos.find(a => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

const anlId = ref<number | ''>(anoDefault?.anl_id ?? '');
const gerando = ref(false);

function gerar() {
    if (!anlId.value) return;
    gerando.value = true;
    router.get('/relatorios/sumario-matriculas/gerar', {
        anl_id: Number(anlId.value),
    }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Sumário de Matrículas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <Table class="size-5 text-indigo-600" /> Sumário de Matrículas
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Total de matrículas regulares por escola e série.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid gap-4">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model="anlId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">
                            {{ a.anl_ano }} <span v-if="a.anl_fl_em_exercicio">(em exercício)</span>
                        </option>
                    </select>
                </div>

                <div class="flex justify-end">
                    <Button :disabled="!anlId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
