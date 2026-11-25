<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useErrorFocus } from '@/composables/useErrorFocus';
import { usePermissao } from '@/composables/usePermissao';
import { useViewMode } from '@/composables/useViewMode';
import type { AreaInep, CursoFormacaoSuperiorCadastro, NivelGraduacao } from '@/types/formacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, PlusCircle, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    niveis: Pick<NivelGraduacao, 'ngr_id' | 'ngr_nome' | 'ngr_fl_ativo'>[];
    areas: AreaInep[];
    initial?: CursoFormacaoSuperiorCadastro;
}>();

const { isReadOnly } = usePermissao();
const { viewMode, readonly } = useViewMode();
const { focusFirstError } = useErrorFocus();

const form = useForm({
    cfs_cd_area:        props.initial?.cfs_cd_area ?? '',
    cfs_area_detalhada: props.initial?.cfs_area_detalhada ?? '',
    cfs_codigo:         props.initial?.cfs_codigo ?? '',
    cfs_nome:           props.initial?.cfs_nome ?? '',
    cfs_ngr_id:         (props.initial?.cfs_ngr_id ?? null) as number | null,
    cfs_fl_ativo:       props.initial?.cfs_fl_ativo ?? true,
    continue_new:       false as boolean,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

const areaPorCodigo = computed(() => new Map(props.areas.map((a) => [a.codigo, a.nome])));
const areaConhecida = computed(() => areaPorCodigo.value.get(form.cfs_cd_area) ?? null);

// Só dígitos no código da área; ao digitar um código já existente, preenche o nome.
watch(() => form.cfs_cd_area, (v, antigo) => {
    const limpo = (v ?? '').replace(/\D/g, '').slice(0, 4);
    if (limpo !== v) { form.cfs_cd_area = limpo; return; }
    const nome = areaPorCodigo.value.get(limpo);
    if (nome) form.cfs_area_detalhada = nome;
    else if (antigo && areaPorCodigo.value.get(antigo) === form.cfs_area_detalhada) form.cfs_area_detalhada = '';
    // Código do curso começa pelo da área: sugere o prefixo enquanto vazio/desatualizado.
    if (limpo.length === 4 && (!form.cfs_codigo || form.cfs_codigo === (antigo ?? ''))) form.cfs_codigo = limpo;
});

watch(() => form.cfs_codigo, (v) => {
    const limpo = (v ?? '').toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 8);
    if (limpo !== v) form.cfs_codigo = limpo;
});

function validarLocal(): Record<string, string> {
    const e: Record<string, string> = {};
    if (!form.cfs_cd_area) e.cfs_cd_area = 'Informe o código INEP da área.';
    else if (!/^\d{4}$/.test(form.cfs_cd_area)) e.cfs_cd_area = 'O código INEP da área deve ter 4 dígitos (ex.: 0111).';
    if (!form.cfs_area_detalhada.trim()) e.cfs_area_detalhada = 'Informe o nome da área.';
    if (!form.cfs_codigo) e.cfs_codigo = 'Informe o código INEP do curso.';
    else if (!/^\d{4}[A-Z]\d{3}$/.test(form.cfs_codigo)) e.cfs_codigo = 'O código INEP do curso deve ter 8 caracteres: 4 dígitos, 1 letra e 3 dígitos (ex.: 0111C012).';
    else if (/^\d{4}$/.test(form.cfs_cd_area) && !form.cfs_codigo.startsWith(form.cfs_cd_area)) e.cfs_codigo = `O código do curso deve começar pelo código da área (${form.cfs_cd_area}).`;
    if (!form.cfs_nome.trim()) e.cfs_nome = 'Informe o nome do curso.';
    if (!form.cfs_ngr_id) e.cfs_ngr_id = 'Selecione o nível de graduação acadêmica.';
    return e;
}

const submit = (continueNew = false) => {
    if (readonly.value) return;
    form.clearErrors();
    const erros = validarLocal();
    if (Object.keys(erros).length) {
        form.setError(erros as any);
        focusFirstError(Object.keys(erros));
        return;
    }
    form.continue_new = continueNew;
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            if (continueNew && props.mode === 'create') {
                form.reset();
                form.cfs_fl_ativo = true;
                form.continue_new = false;
            }
        },
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') form.post('/cursos-formacao-superior', opts);
    else if (props.initial) form.post(`/cursos-formacao-superior/${props.initial.cfs_id}`, opts);
};

const submitLabel = props.mode === 'create' ? 'Cadastrar curso' : 'Salvar alterações';
const selectClass = 'h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:bg-background dark:text-foreground';
</script>

<template>
    <form @submit.prevent="submit(false)" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/cursos-formacao-superior">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Voltar para listagem
                </Button>
            </Link>
            <div class="flex items-center gap-2">
                <span v-if="viewMode && !isReadOnly" class="text-xs font-medium text-muted-foreground">
                    Modo consulta
                </span>
                <Link v-if="viewMode && !isReadOnly" :href="`/cursos-formacao-superior/${initial?.cfs_id}/edit`">
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
                    <Switch id="cfs_fl_ativo" v-model="form.cfs_fl_ativo" />
                    <div>
                        <p class="text-sm font-medium leading-none">Situação</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ form.cfs_fl_ativo ? 'Curso ativo — aparece como opção na formação dos profissionais' : 'Curso inativo — não aparece como opção na formação dos profissionais' }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            form.cfs_fl_ativo ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                        ]"
                    >
                        {{ form.cfs_fl_ativo ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>

                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:col-span-4">Área de conhecimento</p>

                <div class="grid gap-2">
                    <FormLabel for="cfs_cd_area" :required="true">Código INEP da Área</FormLabel>
                    <Input id="cfs_cd_area" v-model="form.cfs_cd_area" inputmode="numeric" maxlength="4" placeholder="Ex.: 0111" class="font-mono" autofocus />
                    <InputError :message="form.errors.cfs_cd_area" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="cfs_area_detalhada" :required="true">Nome da Área</FormLabel>
                    <Input id="cfs_area_detalhada" v-model="form.cfs_area_detalhada" maxlength="180" :readonly="!!areaConhecida" :class="areaConhecida ? 'bg-muted/50' : ''" />
                    <p v-if="areaConhecida" class="text-xs text-muted-foreground">Área já cadastrada para o código {{ form.cfs_cd_area }}.</p>
                    <InputError :message="form.errors.cfs_area_detalhada" />
                </div>

                <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:col-span-4">Curso</p>

                <div class="grid gap-2">
                    <FormLabel for="cfs_codigo" :required="true">Código INEP do Curso</FormLabel>
                    <Input id="cfs_codigo" v-model="form.cfs_codigo" maxlength="8" placeholder="Ex.: 0111C012" class="font-mono uppercase" />
                    <InputError :message="form.errors.cfs_codigo" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="cfs_nome" :required="true">Nome do Curso</FormLabel>
                    <Input id="cfs_nome" v-model="form.cfs_nome" maxlength="255" />
                    <InputError :message="form.errors.cfs_nome" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="cfs_ngr_id" :required="true">Nível de Graduação Acadêmica</FormLabel>
                    <select id="cfs_ngr_id" v-model="form.cfs_ngr_id" :class="selectClass">
                        <option :value="null" disabled>Selecione...</option>
                        <option v-for="n in niveis" :key="n.ngr_id" :value="n.ngr_id">
                            {{ n.ngr_nome }}{{ n.ngr_fl_ativo ? '' : ' (inativo)' }}
                        </option>
                    </select>
                    <p v-if="!niveis.length" class="text-xs text-amber-600">
                        Nenhum nível cadastrado. Cadastre em
                        <Link href="/niveis-graduacao" class="underline">Nível de Graduação Acadêmica</Link>.
                    </p>
                    <InputError :message="form.errors.cfs_ngr_id" />
                </div>
            </div>
        </fieldset>
    </form>
</template>
