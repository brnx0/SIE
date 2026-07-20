<script setup lang="ts">
import DocVencimentoBadge from '@/components/transporte/DocVencimentoBadge.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { Veiculo } from '@/types/transporte';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';

const props = defineProps<{
    mode: 'create' | 'edit';
    tipos: Record<string, string>;
    initial?: Veiculo;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const form = useForm({
    vei_placa:            props.initial?.vei_placa ?? '',
    vei_renavam:          props.initial?.vei_renavam ?? '',
    vei_tipo:             props.initial?.vei_tipo ?? (null as string | null),
    vei_capacidade:       props.initial?.vei_capacidade ?? (null as number | null),
    vei_marca:            props.initial?.vei_marca ?? '',
    vei_modelo:           props.initial?.vei_modelo ?? '',
    vei_ano_fabricacao:   props.initial?.vei_ano_fabricacao ?? (null as number | null),
    vei_fl_acessivel:     props.initial?.vei_fl_acessivel ?? false,
    vei_fl_terceirizado:  props.initial?.vei_fl_terceirizado ?? false,
    vei_dt_licenciamento: props.initial?.vei_dt_licenciamento ?? '',
    vei_dt_seguro:        props.initial?.vei_dt_seguro ?? '',
    vei_dt_vistoria:      props.initial?.vei_dt_vistoria ?? '',
    vei_obs:              props.initial?.vei_obs ?? '',
    vei_fl_ativo:         props.initial?.vei_fl_ativo ?? true,
    continue_new:         false,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const submit = (continueNew = false) => {
    if (isReadOnly.value) return;
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.vei_fl_ativo = true;
                form.continue_new = false;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/transporte/veiculos', opts);
    } else if (props.initial) {
        form.post(`/transporte/veiculos/${props.initial.vei_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar veículo' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/transporte/veiculos">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Voltar para listagem
                </Button>
            </Link>
            <div v-if="!isReadOnly" class="flex items-center gap-2">
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

        <fieldset :disabled="isReadOnly" class="contents">
        <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-4 sm:items-start">
            <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-4">
                <Switch id="vei_fl_ativo" v-model="form.vei_fl_ativo" />
                <div>
                    <p class="text-sm font-medium leading-none">Situação</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ form.vei_fl_ativo ? 'Veículo ativo — aparece como opção nas rotas' : 'Veículo inativo — não aparece como opção nas rotas' }}
                    </p>
                </div>
                <span
                    :class="[
                        'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        form.vei_fl_ativo
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                    ]"
                >
                    {{ form.vei_fl_ativo ? 'Ativo' : 'Inativo' }}
                </span>
            </div>

            <div class="grid gap-2">
                <FormLabel for="vei_placa" :required="true">Placa</FormLabel>
                <Input id="vei_placa" v-model="form.vei_placa" maxlength="8" autofocus class="uppercase" placeholder="AAA0A00" />
                <InputError :message="form.errors.vei_placa" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="vei_tipo" :required="true">Tipo de veículo</FormLabel>
                <select
                    id="vei_tipo"
                    v-model="form.vei_tipo"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                >
                    <option :value="null" disabled>Selecione...</option>
                    <option v-for="(label, key) in tipos" :key="key" :value="key">{{ label }}</option>
                </select>
                <InputError :message="form.errors.vei_tipo" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="vei_capacidade" :required="true">Capacidade (assentos)</FormLabel>
                <Input id="vei_capacidade" type="number" min="1" max="200" step="1" inputmode="numeric" v-model.number="form.vei_capacidade" />
                <InputError :message="form.errors.vei_capacidade" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="vei_renavam">RENAVAM</FormLabel>
                <Input id="vei_renavam" v-model="form.vei_renavam" v-maska="'###########'" inputmode="numeric" maxlength="11" placeholder="Opcional" />
                <InputError :message="form.errors.vei_renavam" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="vei_marca">Marca</FormLabel>
                <Input id="vei_marca" v-model="form.vei_marca" maxlength="60" placeholder="Opcional" />
                <InputError :message="form.errors.vei_marca" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="vei_modelo">Modelo</FormLabel>
                <Input id="vei_modelo" v-model="form.vei_modelo" maxlength="60" placeholder="Opcional" />
                <InputError :message="form.errors.vei_modelo" />
            </div>

            <div class="grid gap-2">
                <FormLabel for="vei_ano_fabricacao">Ano de fabricação</FormLabel>
                <Input id="vei_ano_fabricacao" type="number" min="1950" :max="new Date().getFullYear() + 1" step="1" inputmode="numeric" v-model.number="form.vei_ano_fabricacao" placeholder="Opcional" />
                <InputError :message="form.errors.vei_ano_fabricacao" />
            </div>

            <div class="flex items-center gap-3 rounded-lg border bg-muted/30 px-4 py-3">
                <Switch id="vei_fl_acessivel" v-model="form.vei_fl_acessivel" />
                <p class="text-sm font-medium leading-none">Acessível</p>
            </div>

            <div class="flex items-center gap-3 rounded-lg border bg-muted/30 px-4 py-3">
                <Switch id="vei_fl_terceirizado" v-model="form.vei_fl_terceirizado" />
                <p class="text-sm font-medium leading-none">Terceirizado</p>
            </div>
        </div>

        <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-3 sm:items-start">
            <div class="sm:col-span-3">
                <h3 class="text-sm font-semibold">Documentação</h3>
                <p class="text-xs text-muted-foreground">Datas de validade — vencimentos aparecem em destaque na listagem e no relatório Situação da Frota.</p>
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <FormLabel for="vei_dt_licenciamento">Licenciamento</FormLabel>
                    <DocVencimentoBadge :date="form.vei_dt_licenciamento || null" label="Validade" />
                </div>
                <Input id="vei_dt_licenciamento" type="date" v-model="form.vei_dt_licenciamento" />
                <InputError :message="form.errors.vei_dt_licenciamento" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <FormLabel for="vei_dt_seguro">Seguro</FormLabel>
                    <DocVencimentoBadge :date="form.vei_dt_seguro || null" label="Validade" />
                </div>
                <Input id="vei_dt_seguro" type="date" v-model="form.vei_dt_seguro" />
                <InputError :message="form.errors.vei_dt_seguro" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <FormLabel for="vei_dt_vistoria">Vistoria</FormLabel>
                    <DocVencimentoBadge :date="form.vei_dt_vistoria || null" label="Validade" />
                </div>
                <Input id="vei_dt_vistoria" type="date" v-model="form.vei_dt_vistoria" />
                <InputError :message="form.errors.vei_dt_vistoria" />
            </div>

            <div class="grid gap-2 sm:col-span-3">
                <FormLabel for="vei_obs">Observações</FormLabel>
                <textarea
                    id="vei_obs"
                    v-model="form.vei_obs"
                    rows="3"
                    maxlength="2000"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    placeholder="Opcional"
                ></textarea>
                <InputError :message="form.errors.vei_obs" />
            </div>
        </div>
        </fieldset>
    </form>
</template>
