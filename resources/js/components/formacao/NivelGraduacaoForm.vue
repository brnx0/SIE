<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useErrorFocus } from '@/composables/useErrorFocus';
import { usePermissao } from '@/composables/usePermissao';
import { useViewMode } from '@/composables/useViewMode';
import type { NivelGraduacao } from '@/types/formacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';

const props = defineProps<{
    mode: 'create' | 'edit';
    initial?: NivelGraduacao;
}>();

const { isReadOnly } = usePermissao();
const { viewMode, readonly } = useViewMode();
const { focusFirstError } = useErrorFocus();

const form = useForm({
    ngr_nome:     props.initial?.ngr_nome ?? '',
    ngr_fl_ativo: props.initial?.ngr_fl_ativo ?? true,
    continue_new: false as boolean,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const submit = (continueNew = false) => {
    if (readonly.value) return;
    form.clearErrors();
    if (!form.ngr_nome.trim()) {
        form.setError('ngr_nome', 'Informe o nome do nível de graduação.');
        focusFirstError(['ngr_nome']);
        return;
    }
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.ngr_fl_ativo = true;
                form.continue_new = false;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') form.post('/niveis-graduacao', opts);
    else if (props.initial) form.post(`/niveis-graduacao/${props.initial.ngr_id}`, opts);
};

const submitLabel = props.mode === 'create' ? 'Cadastrar nível' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/niveis-graduacao">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Voltar para listagem
                </Button>
            </Link>
            <div class="flex items-center gap-2">
                <span v-if="viewMode && !isReadOnly" class="text-xs font-medium text-muted-foreground">
                    Modo consulta
                </span>
                <Link v-if="viewMode && !isReadOnly" :href="`/niveis-graduacao/${initial?.ngr_id}/edit`">
                    <Button type="button" class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400">
                        Editar
                    </Button>
                </Link>
                <template v-if="!readonly">
                    <Button
                        v-if="mode === 'create'"
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        class="border-brand-600 text-brand-700 hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-900/30"
                        @click="submit(true)"
                    >
                        <LoaderCircle v-if="form.processing && form.continue_new" class="mr-2 size-4 animate-spin" />
                        <PlusCircle v-else class="mr-2 size-4" />
                        Salvar e novo
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="bg-brand-600 text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-400">
                        <LoaderCircle v-if="form.processing && !form.continue_new" class="mr-2 size-4 animate-spin" />
                        <Save v-else class="mr-2 size-4" />
                        {{ submitLabel }}
                    </Button>
                </template>
            </div>
        </div>

        <fieldset :disabled="readonly" class="contents">
            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-4 sm:items-start">
                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-4">
                    <Switch id="ngr_fl_ativo" v-model="form.ngr_fl_ativo" />
                    <div>
                        <p class="text-sm font-medium leading-none">Situação</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ form.ngr_fl_ativo ? 'Nível ativo — aparece no cadastro de cursos' : 'Nível inativo — não aparece no cadastro de cursos' }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            form.ngr_fl_ativo ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                        ]"
                    >
                        {{ form.ngr_fl_ativo ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="ngr_nome" :required="true">Nível de Graduação Acadêmica</FormLabel>
                    <Input id="ngr_nome" v-model="form.ngr_nome" maxlength="60" placeholder="Ex.: Licenciatura" autofocus />
                    <InputError :message="form.errors.ngr_nome" />
                </div>
            </div>
        </fieldset>
    </form>
</template>
