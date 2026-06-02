<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AnoLetivo, AnoLetivoFormData } from '@/types/parametro';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    initial?: AnoLetivo | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'saved'): void;
}>();

const isEdit = computed(() => !!props.initial);

const form = useForm<AnoLetivoFormData>({
    anl_ano: '',
    anl_dt_inicio_ano: '',
    anl_dt_inicio_1sem: '',
    anl_dt_inicio_2sem: '',
    anl_dt_fim: '',
    anl_dt_corte: '',
    anl_dt_censo: '',
    anl_fl_em_exercicio: false,
    anl_fl_progressao_parcial: false,
    anl_fl_aprovacao_conselho_freq: false,
});

const reset = () => {
    if (props.initial) {
        form.anl_ano = props.initial.anl_ano;
        form.anl_dt_inicio_ano = props.initial.anl_dt_inicio_ano?.slice(0, 10) ?? '';
        form.anl_dt_inicio_1sem = props.initial.anl_dt_inicio_1sem?.slice(0, 10) ?? '';
        form.anl_dt_inicio_2sem = props.initial.anl_dt_inicio_2sem?.slice(0, 10) ?? '';
        form.anl_dt_fim = props.initial.anl_dt_fim?.slice(0, 10) ?? '';
        form.anl_dt_corte = props.initial.anl_dt_corte?.slice(0, 10) ?? '';
        form.anl_dt_censo = props.initial.anl_dt_censo?.slice(0, 10) ?? '';
        form.anl_fl_em_exercicio = props.initial.anl_fl_em_exercicio;
        form.anl_fl_progressao_parcial = props.initial.anl_fl_progressao_parcial;
        form.anl_fl_aprovacao_conselho_freq = props.initial.anl_fl_aprovacao_conselho_freq;
    } else {
        form.reset();
        form.clearErrors();
    }
};

watch(
    () => props.open,
    (v) => {
        if (v) reset();
    },
);

const submit = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            emit('update:open', false);
        },
    };

    if (isEdit.value && props.initial) {
        form.put(`/parametros/anos-letivos/${props.initial.anl_id}`, opts);
    } else {
        form.post('/parametros/anos-letivos', opts);
    }
};

const close = () => emit('update:open', false);
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ isEdit ? 'Editar Ano Letivo' : 'Novo Ano Letivo' }}</DialogTitle>
                <DialogDescription>
                    Defina o ano e as datas do calendário escolar. As datas devem estar dentro do ano informado.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" novalidate class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_ano'" :required="true">Ano</FormLabel>
                    <Input
                        id="anl_ano"
                        type="number"
                        min="1980"
                        max="2999"
                        step="1"
                        inputmode="numeric"
                        v-model.number="form.anl_ano"
                        :required="true"
                    />
                    <InputError :message="form.errors.anl_ano" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_dt_censo'">Data do Censo</FormLabel>
                    <Input id="anl_dt_censo" type="date" v-model="form.anl_dt_censo" />
                    <InputError :message="form.errors.anl_dt_censo" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_dt_inicio_ano'" :required="true">Início do Ano</FormLabel>
                    <Input id="anl_dt_inicio_ano" type="date" v-model="form.anl_dt_inicio_ano" :required="true" />
                    <InputError :message="form.errors.anl_dt_inicio_ano" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_dt_inicio_1sem'" :required="true">Data Início 1º Semestre</FormLabel>
                    <Input id="anl_dt_inicio_1sem" type="date" v-model="form.anl_dt_inicio_1sem" :required="true" />
                    <InputError :message="form.errors.anl_dt_inicio_1sem" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_dt_inicio_2sem'" :required="true">Data Início 2º Semestre</FormLabel>
                    <Input id="anl_dt_inicio_2sem" type="date" v-model="form.anl_dt_inicio_2sem" :required="true" />
                    <InputError :message="form.errors.anl_dt_inicio_2sem" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_dt_fim'" :required="true">Fim do Ano</FormLabel>
                    <Input id="anl_dt_fim" type="date" v-model="form.anl_dt_fim" :required="true" />
                    <InputError :message="form.errors.anl_dt_fim" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_dt_corte'" :required="true">Data de Corte</FormLabel>
                    <Input id="anl_dt_corte" type="date" v-model="form.anl_dt_corte" :required="true" />
                    <InputError :message="form.errors.anl_dt_corte" />
                </div>

                <div class="flex items-center gap-3 sm:col-span-2">
                    <Switch id="anl_fl_em_exercicio" v-model="form.anl_fl_em_exercicio" />
                    <Label for="anl_fl_em_exercicio" class="text-sm font-normal">Ano em Exercício</Label>
                </div>
                <p v-if="form.anl_fl_em_exercicio" class="-mt-2 text-xs text-amber-600 sm:col-span-2 dark:text-amber-400">
                    Apenas um registro pode estar em exercício. Os demais serão desmarcados ao salvar.
                </p>

                <div class="flex items-center gap-3 sm:col-span-1">
                    <Switch id="anl_fl_progressao_parcial" v-model="form.anl_fl_progressao_parcial" />
                    <Label for="anl_fl_progressao_parcial" class="text-sm font-normal">Possui Progressão Parcial</Label>
                </div>

                <div class="flex items-center gap-3 sm:col-span-1">
                    <Switch id="anl_fl_aprovacao_conselho_freq" v-model="form.anl_fl_aprovacao_conselho_freq" />
                    <Label for="anl_fl_aprovacao_conselho_freq" class="text-sm font-normal">Aprovação por Conselho por Frequência</Label>
                </div>

                <DialogFooter class="sm:col-span-2">
                    <Button type="button" variant="outline" @click="close">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700">
                        <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        <Save v-else class="mr-2 size-4" />
                        {{ isEdit ? 'Salvar' : 'Cadastrar' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
