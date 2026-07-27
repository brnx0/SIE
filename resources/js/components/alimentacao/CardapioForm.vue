<script setup lang="ts">
import EscolaCombobox from '@/components/funcionario/EscolaCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissao } from '@/composables/usePermissao';
import { useErrorFocus } from '@/composables/useErrorFocus';
import type { Cardapio } from '@/types/alimentacao';
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, LoaderCircle, Save } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    mode: 'create' | 'edit';
    anosLetivos: { anl_id: number; anl_ano: number }[];
    initial?: Cardapio;
}>();

const { isReadOnly } = usePermissao();
const { focusFirstError } = useErrorFocus();

const form = useForm<{
    crd_esc_id: number | null;
    crd_anl_id: number | null;
    crd_nome: string;
    crd_dt_inicio: string;
    crd_dt_fim: string;
    crd_fl_ativo: boolean;
    _method?: string;
}>({
    crd_esc_id:    props.initial?.crd_esc_id ?? null,
    crd_anl_id:    props.initial?.crd_anl_id ?? props.anosLetivos[0]?.anl_id ?? null,
    crd_nome:      props.initial?.crd_nome ?? '',
    crd_dt_inicio: props.initial?.crd_dt_inicio?.slice(0, 10) ?? '',
    crd_dt_fim:    props.initial?.crd_dt_fim?.slice(0, 10) ?? '',
    crd_fl_ativo:  props.initial?.crd_fl_ativo ?? true,
    _method: props.mode === 'edit' ? 'put' : undefined,
});

// "Padrão da rede" (escola nula) é uma escolha, não ausência de escolha: um
// seletor explícito evita que limpar o combobox pareça campo em branco.
const escopo = ref<'rede' | 'escola'>(props.initial?.crd_esc_id ? 'escola' : 'rede');
watch(escopo, (e) => {
    if (e === 'rede') form.crd_esc_id = null;
});

const escolaInicial = props.initial?.escola
    ? { esc_id: props.initial.escola.esc_id, esc_nome: props.initial.escola.esc_nome }
    : null;

// Ano com 4 dígitos: sem isso o usuário digita "20255" e o input aceita.
const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

const submit = () => {
    if (isReadOnly.value) return;
    const opts = {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => focusFirstError(Object.keys(errors)),
    };
    if (props.mode === 'create') {
        form.post('/alimentacao/cardapios', opts);
    } else if (props.initial) {
        form.post(`/alimentacao/cardapios/${props.initial.crd_id}`, opts);
    }
};

const submitLabel = props.mode === 'create' ? 'Cadastrar cardápio' : 'Salvar alterações';
</script>

<template>
    <form @submit.prevent="submit" novalidate class="grid gap-6">
        <div class="flex items-center justify-between">
            <Link href="/alimentacao/cardapios">
                <Button type="button" variant="outline">
                    <ChevronLeft class="mr-1 size-4" /> Voltar para listagem
                </Button>
            </Link>
            <Button
                v-if="!isReadOnly"
                type="submit"
                :disabled="form.processing"
                class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
            >
                <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                <Save v-else class="mr-2 size-4" />
                {{ submitLabel }}
            </Button>
        </div>

        <fieldset :disabled="isReadOnly" class="contents">
            <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-6 sm:items-start">
                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 sm:col-span-6">
                    <Switch id="crd_fl_ativo" v-model="form.crd_fl_ativo" />
                    <div>
                        <p class="text-sm font-medium leading-none">Situação</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ form.crd_fl_ativo
                                ? 'Cardápio ativo — é o que a escola segue no período de vigência'
                                : 'Cardápio inativo — não é seguido por ninguém' }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            form.crd_fl_ativo
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                        ]"
                    >
                        {{ form.crd_fl_ativo ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>

                <div class="grid gap-2 sm:col-span-4">
                    <FormLabel for="crd_nome" :required="true">Nome do cardápio</FormLabel>
                    <Input id="crd_nome" v-model="form.crd_nome" maxlength="120" autofocus class="uppercase" placeholder="CARDAPIO 1O SEMESTRE" />
                    <InputError :message="form.errors.crd_nome" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="crd_anl_id" :required="true">Ano letivo</FormLabel>
                    <select id="crd_anl_id" v-model="form.crd_anl_id" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground">
                        <option :value="null" disabled>Selecione...</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">{{ a.anl_ano }}</option>
                    </select>
                    <InputError :message="form.errors.crd_anl_id" />
                </div>

                <div class="grid gap-2 sm:col-span-6">
                    <FormLabel>Aplica-se a</FormLabel>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-1 rounded-lg border bg-muted/40 p-1">
                            <button
                                type="button"
                                :class="['rounded-md px-3 py-1.5 text-xs font-medium transition-colors', escopo === 'rede' ? 'bg-indigo-600 text-white' : 'text-muted-foreground hover:bg-muted']"
                                @click="escopo = 'rede'"
                            >
                                Padrão da rede
                            </button>
                            <button
                                type="button"
                                :class="['rounded-md px-3 py-1.5 text-xs font-medium transition-colors', escopo === 'escola' ? 'bg-indigo-600 text-white' : 'text-muted-foreground hover:bg-muted']"
                                @click="escopo = 'escola'"
                            >
                                Escola específica
                            </button>
                        </div>
                        <div v-if="escopo === 'escola'" class="w-80">
                            <EscolaCombobox
                                v-model="form.crd_esc_id"
                                :initial="escolaInicial"
                                placeholder="Buscar escola..."
                                :invalid="!!form.errors.crd_esc_id"
                            />
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ escopo === 'rede'
                            ? 'Vale para toda escola que não tiver cardápio próprio — evita repetir o mesmo cardápio em cada uma.'
                            : 'Cardápio próprio da escola: tem precedência sobre o padrão da rede.' }}
                    </p>
                    <InputError :message="form.errors.crd_esc_id" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="crd_dt_inicio" :required="true">Início da vigência</FormLabel>
                    <Input id="crd_dt_inicio" type="date" v-model="form.crd_dt_inicio" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                    <InputError :message="form.errors.crd_dt_inicio" />
                </div>

                <div class="grid gap-2 sm:col-span-3">
                    <FormLabel for="crd_dt_fim" :required="true">Fim da vigência</FormLabel>
                    <Input id="crd_dt_fim" type="date" v-model="form.crd_dt_fim" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                    <InputError :message="form.errors.crd_dt_fim" />
                </div>
            </div>
        </fieldset>
    </form>
</template>
