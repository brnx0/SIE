<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Conceito, ConceitoFormData } from '@/types/parametro';
import { router } from '@inertiajs/vue3';
import { Award, LoaderCircle, Pencil, Plus, Save, Trash2, X } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

defineProps<{
    conceitos: Conceito[];
}>();

const showForm = ref(false);
const editing = ref<Conceito | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const emptyForm = (): ConceitoFormData => ({
    cnc_sigla: '',
    cnc_descricao: '',
    cnc_limite_inferior: '',
    cnc_limite_superior: '',
    cnc_peso: '',
});

const form = reactive<ConceitoFormData>(emptyForm());

const openCreate = () => {
    editing.value = null;
    Object.assign(form, emptyForm());
    errors.value = {};
    showForm.value = true;
};

const openEdit = (c: Conceito) => {
    editing.value = c;
    form.cnc_sigla = c.cnc_sigla;
    form.cnc_descricao = c.cnc_descricao;
    form.cnc_limite_inferior = Number(c.cnc_limite_inferior);
    form.cnc_limite_superior = Number(c.cnc_limite_superior);
    form.cnc_peso = Number(c.cnc_peso);
    errors.value = {};
    showForm.value = true;
};

const cancel = () => {
    showForm.value = false;
    editing.value = null;
    errors.value = {};
};

const save = () => {
    processing.value = true;
    errors.value = {};

    const data: Record<string, any> = {
        cnc_sigla: form.cnc_sigla,
        cnc_descricao: form.cnc_descricao,
        cnc_limite_inferior: form.cnc_limite_inferior,
        cnc_limite_superior: form.cnc_limite_superior,
        cnc_peso: form.cnc_peso,
    };

    const opts = {
        preserveScroll: true,
        preserveState: true,
        onError: (errs: Record<string, string>) => { errors.value = errs; },
        onFinish: () => { processing.value = false; },
    };

    if (editing.value) {
        data._method = 'put';
        router.post(`/parametros/conceitos/${editing.value.cnc_id}`, data, {
            ...opts,
            onSuccess: () => { showForm.value = false; editing.value = null; },
        });
    } else {
        router.post('/parametros/conceitos', data, {
            ...opts,
            onSuccess: () => { showForm.value = false; },
        });
    }
};

const remove = (c: Conceito) => {
    if (!confirm(`Remover o conceito ${c.cnc_sigla}?`)) return;
    router.delete(`/parametros/conceitos/${c.cnc_id}`, { preserveScroll: true, preserveState: true });
};

const fmtNum = (n: number | string) => Number(n).toFixed(2);
</script>

<template>
    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Award class="size-4 text-indigo-600" />
                <h3 class="text-sm font-semibold">Cadastro de Conceito</h3>
            </div>
            <div class="flex items-center gap-3">
                <RefreshButton />
                <Button v-if="!showForm" type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" @click="openCreate">
                    <Plus class="mr-2 size-4" /> Adicionar Conceito
                </Button>
            </div>
        </div>

        <!-- Formulário inline -->
        <div v-if="showForm" class="rounded-lg border bg-background p-4">
            <h4 class="mb-4 text-sm font-semibold">{{ editing ? 'Editar conceito' : 'Cadastrar conceito' }}</h4>
            <div class="grid gap-4 sm:grid-cols-12">
                <div class="grid gap-1.5 sm:col-span-2">
                    <FormLabel for="cnc_sigla" :required="true">Sigla</FormLabel>
                    <Input id="cnc_sigla" v-model="form.cnc_sigla" maxlength="10" placeholder="Ex.: A"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.cnc_sigla }" />
                    <InputError :message="errors.cnc_sigla" />
                </div>
                <div class="grid gap-1.5 sm:col-span-4">
                    <FormLabel for="cnc_descricao" :required="true">Descrição</FormLabel>
                    <Input id="cnc_descricao" v-model="form.cnc_descricao" maxlength="100" placeholder="Ex.: Excelente"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.cnc_descricao }" />
                    <InputError :message="errors.cnc_descricao" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <FormLabel for="cnc_peso" :required="true">Peso</FormLabel>
                    <Input id="cnc_peso" v-model.number="form.cnc_peso" type="number" min="1" max="99" step="1"
                        inputmode="numeric" placeholder="Ex.: 1"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.cnc_peso }" />
                    <InputError :message="errors.cnc_peso" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <FormLabel for="cnc_limite_inferior" :required="true">Limite Inferior</FormLabel>
                    <Input id="cnc_limite_inferior" v-model.number="form.cnc_limite_inferior" type="number" min="0" step="0.01"
                        inputmode="decimal" :class="{ 'border-red-500 ring-1 ring-red-500': errors.cnc_limite_inferior }" />
                    <InputError :message="errors.cnc_limite_inferior" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <FormLabel for="cnc_limite_superior" :required="true">Limite Superior</FormLabel>
                    <Input id="cnc_limite_superior" v-model.number="form.cnc_limite_superior" type="number" min="0" step="0.01"
                        inputmode="decimal" :class="{ 'border-red-500 ring-1 ring-red-500': errors.cnc_limite_superior }" />
                    <InputError :message="errors.cnc_limite_superior" />
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="cancel">
                    <X class="mr-2 size-4" /> Cancelar
                </Button>
                <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" :disabled="processing" @click="save">
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
                        <th class="px-3 py-2 font-semibold">Sigla</th>
                        <th class="px-3 py-2 font-semibold">Descrição</th>
                        <th class="px-3 py-2 text-center font-semibold">Limite Inferior</th>
                        <th class="px-3 py-2 text-center font-semibold">Limite Superior</th>
                        <th class="px-3 py-2 text-center font-semibold">Peso</th>
                        <th class="px-3 py-2 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="conceitos.length === 0">
                        <td colspan="6" class="px-3 py-6 text-center text-muted-foreground">Nenhum conceito cadastrado.</td>
                    </tr>
                    <tr v-for="(c, idx) in conceitos" :key="c.cnc_id"
                        :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                        <td class="px-3 py-2 font-semibold">{{ c.cnc_sigla }}</td>
                        <td class="px-3 py-2">{{ c.cnc_descricao }}</td>
                        <td class="px-3 py-2 text-center">{{ fmtNum(c.cnc_limite_inferior) }}</td>
                        <td class="px-3 py-2 text-center">{{ fmtNum(c.cnc_limite_superior) }}</td>
                        <td class="px-3 py-2 text-center font-semibold">{{ c.cnc_peso }}</td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <Button type="button" variant="ghost" size="sm" @click="openEdit(c)" aria-label="Editar">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button type="button" variant="ghost" size="sm"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                    @click="remove(c)" aria-label="Remover">
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
