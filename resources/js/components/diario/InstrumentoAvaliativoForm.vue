<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { InstrumentoAvaliativo, InstrumentoAvaliativoFormData } from '@/types/instrumento-avaliativo';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: InstrumentoAvaliativo;
}>();

const form = useForm<InstrumentoAvaliativoFormData & { continue_new?: boolean }>({
    iav_nome:     props.initial?.iav_nome ?? '',
    iav_fl_ativo: props.initial?.iav_fl_ativo ?? true,
    continue_new: false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const submit = (continueNew = false) => {
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.iav_fl_ativo = true;
                form.continue_new = false;
            }
        },
    };
    if (props.mode === 'create') {
        form.post('/diario/instrumentos-avaliativos', opts);
    } else if (props.initial) {
        form.post(`/diario/instrumentos-avaliativos/${props.initial.iav_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar instrumento' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/diario/instrumentos-avaliativos">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Voltar para listagem
                </Button>
            </Link>
            <div class="flex items-center gap-2">
                <Button
                    v-if="mode === 'create'"
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    class="border-indigo-600 text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
                    @click="submit(true)"
                >
                    <LoaderCircle v-if="form.processing && form.continue_new" class="mr-2 size-4 animate-spin" />
                    <PlusCircle v-else class="mr-2 size-4" />
                    Salvar e novo
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                >
                    <LoaderCircle v-if="form.processing && !form.continue_new" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    {{ submitLabel }}
                </Button>
            </div>
        </div>

        <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
            <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3">
                <Switch id="iav_fl_ativo" v-model="form.iav_fl_ativo" />
                <div>
                    <p class="text-sm font-medium leading-none">Situação</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ form.iav_fl_ativo ? 'Instrumento ativo — aparece como opção no lançamento de notas' : 'Instrumento inativo — não aparece como opção no lançamento de notas' }}
                    </p>
                </div>
                <span
                    :class="[
                        'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        form.iav_fl_ativo
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                    ]"
                >
                    {{ form.iav_fl_ativo ? 'Ativo' : 'Inativo' }}
                </span>
            </div>

            <div class="grid gap-2">
                <FormLabel for="iav_nome" :required="true">Nome</FormLabel>
                <Input
                    id="iav_nome"
                    v-model="form.iav_nome"
                    maxlength="100"
                    autofocus
                    class="uppercase"
                    placeholder="Ex.: PROVA ESCRITA, TRABALHO EM GRUPO..."
                />
                <InputError :message="form.errors.iav_nome" />
            </div>
        </div>
    </form>
</template>
