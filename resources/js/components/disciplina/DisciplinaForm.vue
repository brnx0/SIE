<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AreaConhecimento, Disciplina, DisciplinaFormData } from '@/types/disciplina';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, Save, PlusCircle } from 'lucide-vue-next';

const props = defineProps<{
    mode: 'create' | 'edit';
    areas: Pick<AreaConhecimento, 'arc_id' | 'arc_nome'>[];
    initial?: Disciplina;
}>();

const form = useForm<DisciplinaFormData & { continue_new?: boolean }>({
    arc_id:            props.initial?.arc_id ?? null,
    dis_cod_ref:       props.initial?.dis_cod_ref ?? null,
    dis_nome_mec:      props.initial?.dis_nome_mec ?? '',
    dis_nome:          props.initial?.dis_nome ?? '',
    dis_sigla:         props.initial?.dis_sigla ?? '',
    dis_fl_ativo:      props.initial?.dis_fl_ativo ?? true,
    continue_new:      false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const submit = (continueNew = false) => {
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.dis_fl_ativo = true;
                form.continue_new = false;
            }
        },
    };
    if (props.mode === 'create') {
        form.post('/disciplinas', opts);
    } else if (props.initial) {
        form.post(`/disciplinas/${props.initial.dis_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar disciplina' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <!-- Botões topo -->
        <div class="flex items-center justify-between">
            <Link href="/disciplinas">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Cancelar
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

        <!-- Card principal -->
        <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-4 sm:items-start">

            <!-- Área do Conhecimento -->
            <div class="grid gap-2 sm:col-span-4">
                <FormLabel for="arc_id" :required="true">Área do Conhecimento</FormLabel>
                <select
                    id="arc_id"
                    v-model="form.arc_id"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                >
                    <option :value="null" disabled>Selecione uma área...</option>
                    <option v-for="area in areas" :key="area.arc_id" :value="area.arc_id">
                        {{ area.arc_nome }}
                    </option>
                </select>
                <InputError :message="form.errors.arc_id" />
            </div>

            <!-- Código de Referência -->
            <div class="grid gap-2">
                <FormLabel for="dis_cod_ref">Código de Referência</FormLabel>
                <Input
                    id="dis_cod_ref"
                    type="number"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    v-model.number="form.dis_cod_ref"
                    placeholder="Opcional"
                />
                <InputError :message="form.errors.dis_cod_ref" />
            </div>

            <!-- Nome MEC -->
            <div class="grid gap-2 sm:col-span-3">
                <FormLabel for="dis_nome_mec" :required="true">Nome (MEC)</FormLabel>
                <Input
                    id="dis_nome_mec"
                    v-model="form.dis_nome_mec"
                    maxlength="100"
                    autofocus
                    class="uppercase"
                />
                <InputError :message="form.errors.dis_nome_mec" />
            </div>

            <!-- Nome Reduzido -->
            <div class="grid gap-2 sm:col-span-3">
                <FormLabel for="dis_nome" :required="true">Nome Reduzido</FormLabel>
                <Input
                    id="dis_nome"
                    v-model="form.dis_nome"
                    maxlength="100"
                    class="uppercase"
                />
                <InputError :message="form.errors.dis_nome" />
            </div>

            <!-- Sigla -->
            <div class="grid gap-2">
                <FormLabel for="dis_sigla">Sigla</FormLabel>
                <Input
                    id="dis_sigla"
                    v-model="form.dis_sigla"
                    maxlength="20"
                    placeholder="Opcional"
                />
                <InputError :message="form.errors.dis_sigla" />
            </div>

            <!-- Separador -->
            <div class="sm:col-span-4 border-t" />

            <!-- Ativo -->
            <div class="flex items-center gap-3 sm:col-span-4">
                <Switch id="dis_fl_ativo" v-model="form.dis_fl_ativo" />
                <FormLabel for="dis_fl_ativo" class="font-normal">Ativo</FormLabel>
            </div>
        </div>
    </form>
</template>
