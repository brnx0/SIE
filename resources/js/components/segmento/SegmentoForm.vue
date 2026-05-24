<script setup lang="ts">
import CharCounter from '@/components/common/CharCounter.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Segmento, SegmentoFormData } from '@/types/segmento';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: Segmento;
}>();

const form = useForm<SegmentoFormData>({
    seg_cd_inep:            props.initial?.seg_cd_inep ?? '',
    seg_nome_reduzido:      props.initial?.seg_nome_reduzido ?? '',
    seg_nome_completo:      props.initial?.seg_nome_completo ?? '',
    seg_qt_anos_escolares:  props.initial?.seg_qt_anos_escolares ?? null,
    seg_ordem:              props.initial?.seg_ordem ?? null,
    seg_dt_abertura:        props.initial?.seg_dt_abertura ?? '',
    seg_dt_encerramento:    props.initial?.seg_dt_encerramento ?? '',
    seg_fl_prereq:          props.initial?.seg_fl_prereq ?? false,
    seg_ds_prereq:          props.initial?.seg_ds_prereq ?? '',
    seg_observacoes:        props.initial?.seg_observacoes ?? '',
    seg_fl_ativo:           props.initial?.seg_fl_ativo ?? true,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

watch(() => form.seg_dt_abertura, (abertura) => {
    if (form.seg_dt_encerramento && abertura > form.seg_dt_encerramento) {
        form.seg_dt_encerramento = '';
    }
});

watch(() => form.seg_fl_prereq, (v) => {
    if (!v) form.seg_ds_prereq = '';
});

const minEncerramento = computed(() => form.seg_dt_abertura || undefined);

const submit = () => {
    const opts = { preserveScroll: true };
    if (props.mode === 'create') {
        form.post('/segmentos', opts);
    } else if (props.initial) {
        form.post(`/segmentos/${props.initial.seg_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar segmento' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit" novalidate class="grid gap-6">
        <!-- Botões topo -->
        <div class="flex items-center justify-between">
            <Link href="/segmentos">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Cancelar
                </Button>
            </Link>
            <Button
                type="submit"
                :disabled="form.processing"
                class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
            >
                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                <Save v-else class="mr-2 size-4" />
                {{ submitLabel }}
            </Button>
        </div>

        <!-- Card principal -->
        <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-4 sm:items-start">

            <!-- Código INEP -->
            <div class="grid gap-2">
                <FormLabel for="seg_cd_inep">Código INEP</FormLabel>
                <Input
                    id="seg_cd_inep"
                    v-model="form.seg_cd_inep"
                    maxlength="20"
                    placeholder="Opcional"
                />
                <InputError :message="form.errors.seg_cd_inep" />
            </div>

            <!-- Nome Reduzido -->
            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="seg_nome_reduzido" :required="true">Nome Reduzido do Segmento</FormLabel>
                <Input
                    id="seg_nome_reduzido"
                    v-model="form.seg_nome_reduzido"
                    maxlength="60"
                    autofocus
                />
                <div class="flex justify-between gap-2">
                    <InputError :message="form.errors.seg_nome_reduzido" />
                    <CharCounter :value="form.seg_nome_reduzido" :max="60" />
                </div>
            </div>

            <!-- Data Abertura -->
            <div class="grid gap-2">
                <FormLabel for="seg_dt_abertura" :required="true">Data da Abertura</FormLabel>
                <input
                    id="seg_dt_abertura"
                    type="date"
                    v-model="form.seg_dt_abertura"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                />
                <InputError :message="form.errors.seg_dt_abertura" />
            </div>

            <!-- Nome Completo -->
            <div class="grid gap-2 sm:col-span-4">
                <FormLabel for="seg_nome_completo" :required="true">Nome Completo do Segmento</FormLabel>
                <Input
                    id="seg_nome_completo"
                    v-model="form.seg_nome_completo"
                    maxlength="150"
                />
                <div class="flex justify-between gap-2">
                    <InputError :message="form.errors.seg_nome_completo" />
                    <CharCounter :value="form.seg_nome_completo" :max="150" />
                </div>
            </div>

            <!-- Qtd Anos Escolares -->
            <div class="grid gap-2">
                <FormLabel for="seg_qt_anos_escolares" :required="true">Qtd. de Anos Escolares</FormLabel>
                <Input
                    id="seg_qt_anos_escolares"
                    type="number"
                    min="1"
                    max="99"
                    step="1"
                    inputmode="numeric"
                    v-model.number="form.seg_qt_anos_escolares"
                />
                <InputError :message="form.errors.seg_qt_anos_escolares" />
            </div>

            <!-- Ordem -->
            <div class="grid gap-2">
                <FormLabel for="seg_ordem" :required="true">Ordem</FormLabel>
                <Input
                    id="seg_ordem"
                    type="number"
                    min="1"
                    max="999"
                    step="1"
                    inputmode="numeric"
                    v-model.number="form.seg_ordem"
                />
                <InputError :message="form.errors.seg_ordem" />
            </div>

            <!-- Data Encerramento -->
            <div class="grid gap-2">
                <FormLabel for="seg_dt_encerramento">Data de Encerramento</FormLabel>
                <input
                    id="seg_dt_encerramento"
                    type="date"
                    v-model="form.seg_dt_encerramento"
                    :min="minEncerramento"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                />
                <InputError :message="form.errors.seg_dt_encerramento" />
            </div>

            <!-- Ativo -->
            <div class="flex items-center gap-3 self-end pb-1">
                <Switch id="seg_fl_ativo" v-model="form.seg_fl_ativo" />
                <FormLabel for="seg_fl_ativo" class="font-normal">Ativo</FormLabel>
            </div>

            <!-- Separador -->
            <div class="sm:col-span-4 border-t" />

            <!-- Pré-requisito toggle -->
            <div class="flex items-center gap-3 sm:col-span-4">
                <Switch id="seg_fl_prereq" v-model="form.seg_fl_prereq" />
                <FormLabel for="seg_fl_prereq" class="font-normal">
                    Pré-requisito para ingressão
                </FormLabel>
            </div>

            <!-- Pré-requisito texto + Observações -->
            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="seg_ds_prereq" :required="form.seg_fl_prereq">Descrição do pré-requisito</FormLabel>
                <textarea
                    id="seg_ds_prereq"
                    v-model="form.seg_ds_prereq"
                    :disabled="!form.seg_fl_prereq"
                    rows="4"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Descreva o pré-requisito..."
                />
                <InputError :message="form.errors.seg_ds_prereq" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <FormLabel for="seg_observacoes">Observações</FormLabel>
                <textarea
                    id="seg_observacoes"
                    v-model="form.seg_observacoes"
                    rows="4"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Observações gerais..."
                />
                <InputError :message="form.errors.seg_observacoes" />
            </div>
        </div>
    </form>
</template>
