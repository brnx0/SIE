<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { CensoEscolarResumo } from '@/types/censo';
import { router, useForm } from '@inertiajs/vue3';
import { ClipboardList, FilePlus, Pencil } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    escId: number;
    censo: Pick<CensoEscolarResumo, 'cen_id' | 'cen_updated_at'> | null;
}>();

const storeForm = useForm({});

function parseLocalDate(dt: string | null | undefined): Date | null {
    if (!dt) return null;
    const d = new Date(dt);
    return isNaN(d.getTime()) ? null : d;
}

const atualizadoEm = computed(() => {
    const d = parseLocalDate(props.censo?.cen_updated_at);
    return d ? d.toLocaleDateString('pt-BR') : null;
});

function iniciarCenso() {
    storeForm.post(`/escolas/${props.escId}/censo`);
}

function abrirEdicao() {
    if (props.censo) {
        router.visit(`/escolas/${props.escId}/censo/${props.censo.cen_id}/edit`);
    }
}
</script>

<template>
    <div class="rounded-xl border bg-card p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-1">
                <h3 class="flex items-center gap-2 text-base font-semibold text-slate-800 dark:text-slate-100">
                    <ClipboardList class="size-5 text-indigo-500" />
                    Censo Escolar
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Registro único da escola. Mantenha os dados e atualize sempre que houver mudança.
                </p>
                <p v-if="atualizadoEm" class="text-xs text-slate-400 dark:text-slate-500">
                    Última atualização: {{ atualizadoEm }}
                </p>
            </div>

            <div>
                <Button
                    v-if="!censo"
                    type="button"
                    :disabled="storeForm.processing"
                    class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    @click="iniciarCenso"
                >
                    <FilePlus class="mr-2 size-4" />
                    Iniciar preenchimento
                </Button>

                <Button
                    v-else
                    type="button"
                    class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    @click="abrirEdicao"
                >
                    <Pencil class="mr-2 size-4" />
                    Editar censo
                </Button>
            </div>
        </div>
    </div>
</template>
