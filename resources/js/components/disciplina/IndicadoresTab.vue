<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import type { DisciplinaIndicador } from '@/types/disciplina';
import { router } from '@inertiajs/vue3';
import { CheckCircle2, LoaderCircle, Pencil, Plus, Save, Trash2, X, XCircle } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

const props = defineProps<{
    disId: number;
    indicadores: DisciplinaIndicador[];
}>();

const showForm  = ref(false);
const editing   = ref<DisciplinaIndicador | null>(null);
const formData  = reactive({ ind_descricao: '', ind_fl_ativo: true });
const errors    = ref<Record<string, string>>({});
const processing = ref(false);

const abrirCriar = () => {
    editing.value = null;
    formData.ind_descricao = '';
    formData.ind_fl_ativo  = true;
    errors.value = {};
    showForm.value = true;
};

const abrirEditar = (ind: DisciplinaIndicador) => {
    editing.value = ind;
    formData.ind_descricao = ind.ind_descricao;
    formData.ind_fl_ativo  = ind.ind_fl_ativo;
    errors.value = {};
    showForm.value = true;
};

const cancelar = () => {
    showForm.value = false;
    editing.value  = null;
    errors.value   = {};
};

const salvar = () => {
    processing.value = true;
    errors.value     = {};

    const onOk  = () => { showForm.value = false; editing.value = null; };
    const onErr = (errs: Record<string, string>) => { errors.value = errs; };
    const onEnd = () => { processing.value = false; };

    if (editing.value) {
        router.put(
            `/disciplinas/${props.disId}/indicadores/${editing.value.ind_id}`,
            { ...formData },
            { preserveScroll: true, onSuccess: onOk, onError: onErr, onFinish: onEnd },
        );
    } else {
        router.post(
            `/disciplinas/${props.disId}/indicadores`,
            { ...formData },
            { preserveScroll: true, onSuccess: onOk, onError: onErr, onFinish: onEnd },
        );
    }
};

const remover = (ind: DisciplinaIndicador) => {
    if (!confirm(`Remover indicador "${ind.ind_descricao.substring(0, 60)}${ind.ind_descricao.length > 60 ? '…' : ''}"?`)) return;
    router.delete(`/disciplinas/${props.disId}/indicadores/${ind.ind_id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">
                {{ indicadores.length }} indicador{{ indicadores.length !== 1 ? 'es' : '' }} cadastrado{{ indicadores.length !== 1 ? 's' : '' }}
            </p>
            <Button
                v-if="!showForm"
                type="button"
                size="sm"
                class="bg-indigo-600 hover:bg-indigo-700"
                @click="abrirCriar"
            >
                <Plus class="mr-2 size-4" /> Adicionar Indicador
            </Button>
        </div>

        <!-- Formulário inline -->
        <div v-if="showForm" class="rounded-lg border bg-background p-4">
            <h4 class="mb-4 text-sm font-semibold">
                {{ editing ? 'Editar Indicador' : 'Novo Indicador' }}
            </h4>

            <div class="grid gap-4">
                <div class="grid gap-1.5">
                    <FormLabel for="ind_descricao" :required="true">Descrição</FormLabel>
                    <textarea
                        id="ind_descricao"
                        v-model="formData.ind_descricao"
                        maxlength="1000"
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-background dark:text-foreground resize-none"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.ind_descricao }"
                        placeholder="Descreva o indicador..."
                    />
                    <InputError :message="errors.ind_descricao" />
                </div>

                <div class="flex items-center gap-3">
                    <Switch id="ind_fl_ativo_form" v-model="formData.ind_fl_ativo" />
                    <label for="ind_fl_ativo_form" class="text-sm">Ativo</label>
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="cancelar">
                    <X class="mr-2 size-4" /> Cancelar
                </Button>
                <Button
                    type="button"
                    size="sm"
                    class="bg-indigo-600 hover:bg-indigo-700"
                    :disabled="processing || !formData.ind_descricao.trim()"
                    @click="salvar"
                >
                    <LoaderCircle v-if="processing" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    Salvar
                </Button>
            </div>
        </div>

        <!-- Tabela -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Descrição</th>
                        <th class="px-3 py-2 text-center font-semibold w-24">Ativo</th>
                        <th class="px-3 py-2 text-right font-semibold w-28">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="indicadores.length === 0">
                        <td colspan="3" class="px-3 py-6 text-center text-muted-foreground">
                            Nenhum indicador cadastrado.
                        </td>
                    </tr>
                    <tr
                        v-for="(ind, idx) in indicadores"
                        :key="ind.ind_id"
                        :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                    >
                        <td class="px-3 py-2 whitespace-pre-wrap">{{ ind.ind_descricao }}</td>
                        <td class="px-3 py-2 text-center">
                            <CheckCircle2 v-if="ind.ind_fl_ativo" class="mx-auto size-4 text-emerald-600" />
                            <XCircle v-else class="mx-auto size-4 text-rose-400" />
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <Button type="button" variant="ghost" size="sm" @click="abrirEditar(ind)" aria-label="Editar">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                    @click="remover(ind)"
                                    aria-label="Remover"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
