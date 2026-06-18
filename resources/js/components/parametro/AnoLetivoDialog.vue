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
    anl_dt_fim: '',
    anl_dt_corte: '',
    anl_dt_censo: '',
    anl_fl_em_exercicio: false,
    anl_fl_progressao_parcial: false,
    anl_fl_aprovacao_conselho_freq: false,
    anl_frequencia_minima: '',
    anl_media_geral: '',
    anl_conceito_modo: 'faixa',
});

const reset = () => {
    if (props.initial) {
        form.anl_ano = props.initial.anl_ano;
        form.anl_dt_inicio_ano = props.initial.anl_dt_inicio_ano?.slice(0, 10) ?? '';
        form.anl_dt_fim = props.initial.anl_dt_fim?.slice(0, 10) ?? '';
        form.anl_dt_corte = props.initial.anl_dt_corte?.slice(0, 10) ?? '';
        form.anl_dt_censo = props.initial.anl_dt_censo?.slice(0, 10) ?? '';
        form.anl_fl_em_exercicio = props.initial.anl_fl_em_exercicio;
        form.anl_fl_progressao_parcial = props.initial.anl_fl_progressao_parcial;
        form.anl_fl_aprovacao_conselho_freq = props.initial.anl_fl_aprovacao_conselho_freq;
        form.anl_frequencia_minima = props.initial.anl_frequencia_minima != null ? Number(props.initial.anl_frequencia_minima) : '';
        form.anl_media_geral = props.initial.anl_media_geral != null ? Number(props.initial.anl_media_geral) : '';
        form.anl_conceito_modo = props.initial.anl_conceito_modo ?? 'faixa';
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
        preserveState: true,
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

const clampAno4Digitos = (field: keyof AnoLetivoFormData) => (e: Event) => {
    const input = e.target as HTMLInputElement;
    const v = input.value;
    if (!v) return;
    const m = v.match(/^(\d+)-(\d{2})-(\d{2})$/);
    if (!m) return;
    if (m[1].length > 4) {
        const fixed = `${m[1].slice(0, 4)}-${m[2]}-${m[3]}`;
        input.value = fixed;
        (form as any)[field] = fixed;
    }
};
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
                    <Input id="anl_dt_censo" type="date" min="1900-01-01" max="2999-12-31" v-model="form.anl_dt_censo" @input="clampAno4Digitos('anl_dt_censo')" />
                    <InputError :message="form.errors.anl_dt_censo" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_dt_inicio_ano'" :required="true">Início do Ano</FormLabel>
                    <Input id="anl_dt_inicio_ano" type="date" min="1900-01-01" max="2999-12-31" v-model="form.anl_dt_inicio_ano" :required="true" @input="clampAno4Digitos('anl_dt_inicio_ano')" />
                    <InputError :message="form.errors.anl_dt_inicio_ano" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_dt_fim'" :required="true">Fim do Ano</FormLabel>
                    <Input id="anl_dt_fim" type="date" min="1900-01-01" max="2999-12-31" v-model="form.anl_dt_fim" :required="true" @input="clampAno4Digitos('anl_dt_fim')" />
                    <InputError :message="form.errors.anl_dt_fim" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_dt_corte'" :required="true">Data de Corte</FormLabel>
                    <Input id="anl_dt_corte" type="date" min="1900-01-01" max="2999-12-31" v-model="form.anl_dt_corte" :required="true" @input="clampAno4Digitos('anl_dt_corte')" />
                    <InputError :message="form.errors.anl_dt_corte" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_frequencia_minima'" :required="true">Frequência Mínima (%)</FormLabel>
                    <Input
                        id="anl_frequencia_minima"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="Ex.: 75"
                        v-model.number="form.anl_frequencia_minima"
                        :required="true"
                    />
                    <InputError :message="form.errors.anl_frequencia_minima" />
                </div>

                <div class="grid gap-2 sm:col-span-1">
                    <FormLabel :for="'anl_media_geral'" :required="true">Média Geral</FormLabel>
                    <Input
                        id="anl_media_geral"
                        type="number"
                        min="0"
                        max="10"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="Ex.: 6"
                        v-model.number="form.anl_media_geral"
                        :required="true"
                    />
                    <InputError :message="form.errors.anl_media_geral" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel :for="'anl_conceito_modo'" :required="true">Lançamento do Conceito</FormLabel>
                    <select
                        id="anl_conceito_modo"
                        v-model="form.anl_conceito_modo"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="faixa">Por nota numérica (converte pela faixa)</option>
                        <option value="conceito">Por conceito direto (I/S/H)</option>
                    </select>
                    <p class="text-xs text-muted-foreground">Define como a avaliação conceitual é lançada neste ano letivo.</p>
                    <InputError :message="form.errors.anl_conceito_modo" />
                </div>

                <div class="flex items-center gap-3 sm:col-span-2">
                    <Switch id="anl_fl_em_exercicio" v-model="form.anl_fl_em_exercicio" />
                    <Label for="anl_fl_em_exercicio" class="text-sm font-normal">Ano em Exercício</Label>
                </div>
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
