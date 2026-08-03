<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Accessibility, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Alunos com Deficiência', href: '/relatorios/alunos-deficiencia' },
];

const anoDefault = props.anosLetivos.find(a => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];

// Mantém os filtros ao voltar do resultado (gerar outro sem refazer tudo).
const { restaurar, salvar } = useFiltrosRelatorio('alunos-deficiencia', {
    anl_id: null as number | null,
    esc_id: null as number | null,
});
const salvos = restaurar();

const anlId = ref<number | ''>(salvos.anl_id ?? anoDefault?.anl_id ?? '');
const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null);
const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const gerando = ref(false);

function gerar() {
    if (!anlId.value) return;
    salvar({ anl_id: Number(anlId.value), esc_id: escId.value });
    gerando.value = true;
    const payload: Record<string, any> = { anl_id: Number(anlId.value) };
    if (escId.value) payload.esc_id = Number(escId.value);

    router.get('/relatorios/alunos-deficiencia/gerar', payload, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Alunos com Deficiência" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <Accessibility class="size-5 text-indigo-600" /> Alunos com Deficiência
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Lista nominal de alunos PCD com matrícula regular ativa.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model="anlId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">
                            {{ a.anl_ano }} <span v-if="a.anl_fl_em_exercicio">(em exercício)</span>
                        </option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Escola (opcional)</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" :disabled="!isAdmin && !!userEscola" placeholder="Todas as escolas" />
                </div>

                <div class="sm:col-span-2 flex justify-end">
                    <Button :disabled="!anlId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
