<script setup lang="ts">
import EscolaCombobox from '@/components/funcionario/EscolaCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Escola } from '@/types/funcionario';
import type { AnoLetivo, Conceito, MediaEscola, MediaEscolaFormData } from '@/types/parametro';
import { router } from '@inertiajs/vue3';
import { CopyPlus, LoaderCircle, Pencil, Plus, Save, School, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    mediasEscola: MediaEscola[];
    anosLetivos: AnoLetivo[];
    conceitos: Conceito[];
}>();

const conceitosOrdenados = computed(() => [...props.conceitos].sort((a, b) => Number(a.cnc_limite_inferior) - Number(b.cnc_limite_inferior)));
const labelConceito = (c: Conceito) => `${c.cnc_sigla} — ${c.cnc_descricao}`;

// Anos: em exercício primeiro, depois ano desc
const anosOrdenados = computed(() =>
    [...props.anosLetivos].sort((a, b) => {
        if (a.anl_fl_em_exercicio !== b.anl_fl_em_exercicio) return a.anl_fl_em_exercicio ? -1 : 1;
        return b.anl_ano - a.anl_ano;
    }),
);

const anoAtual = props.anosLetivos.find((a) => a.anl_fl_em_exercicio);
const selectedAnlId = ref<number | null>(anoAtual?.anl_id ?? props.anosLetivos[0]?.anl_id ?? null);

const anoSelecionado = computed(() => props.anosLetivos.find((a) => a.anl_id === selectedAnlId.value) ?? null);
const mediaGeral = computed(() => {
    const m = anoSelecionado.value?.anl_media_geral;
    return m != null ? Number(m) : null;
});
const conceitoGeral = computed(() => props.conceitos.find((c) => c.cnc_id === anoSelecionado.value?.anl_cnc_id_geral) ?? null);
const anoSeguinte = computed(() => (anoSelecionado.value ? anoSelecionado.value.anl_ano + 1 : null));

const siglaConceito = (id: number | null) => props.conceitos.find((c) => c.cnc_id === id)?.cnc_sigla ?? '—';

const mediasDoAno = computed(() => props.mediasEscola.filter((m) => m.mde_anl_id === selectedAnlId.value));

// ===== Formulário inline =====
const showForm = ref(false);
const editing = ref<MediaEscola | null>(null);
const escolaInicial = ref<Escola | null>(null);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const emptyForm = (): MediaEscolaFormData => ({
    mde_anl_id: selectedAnlId.value,
    mde_esc_id: null,
    mde_media: '',
    mde_cnc_id: null,
});

const form = reactive<MediaEscolaFormData>(emptyForm());

const openCreate = () => {
    editing.value = null;
    escolaInicial.value = null;
    Object.assign(form, emptyForm());
    errors.value = {};
    showForm.value = true;
};

const openEdit = (m: MediaEscola) => {
    editing.value = m;
    escolaInicial.value = m.escola ? { esc_id: m.escola.esc_id, esc_nome: m.escola.esc_nome } as Escola : null;
    form.mde_anl_id = m.mde_anl_id;
    form.mde_esc_id = m.mde_esc_id;
    form.mde_media = Number(m.mde_media);
    form.mde_cnc_id = m.mde_cnc_id ?? null;
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
        mde_anl_id: form.mde_anl_id,
        mde_esc_id: form.mde_esc_id,
        mde_media: form.mde_media,
        mde_cnc_id: form.mde_cnc_id,
    };

    const opts = {
        preserveScroll: true,
        preserveState: true,
        onError: (errs: Record<string, string>) => { errors.value = errs; },
        onFinish: () => { processing.value = false; },
    };

    if (editing.value) {
        data._method = 'put';
        router.post(`/parametros/medias-escola/${editing.value.mde_id}`, data, {
            ...opts,
            onSuccess: () => { showForm.value = false; editing.value = null; },
        });
    } else {
        router.post('/parametros/medias-escola', data, {
            ...opts,
            onSuccess: () => { showForm.value = false; },
        });
    }
};

const remove = (m: MediaEscola) => {
    if (!confirm(`Remover a média específica de ${m.escola?.esc_nome ?? 'escola'}?`)) return;
    router.delete(`/parametros/medias-escola/${m.mde_id}`, { preserveScroll: true, preserveState: true });
};

const replicar = () => {
    if (!selectedAnlId.value) return;
    if (!confirm(`Replicar as médias por escola de ${anoSelecionado.value?.anl_ano} para o ano ${anoSeguinte.value}?`)) return;
    router.post('/parametros/medias-escola/replicar', { origem_anl_id: selectedAnlId.value }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const onAnoChange = () => {
    showForm.value = false;
    form.mde_anl_id = selectedAnlId.value;
};

const difereDaGeral = (m: MediaEscola) => mediaGeral.value != null && Number(m.mde_media) !== mediaGeral.value;
</script>

<template>
    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
        <!-- Cabeçalho: ano + média geral + ações -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <School class="size-4 text-indigo-600" />
                <h3 class="text-sm font-semibold">Média por Escola</h3>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <RefreshButton />
                <select
                    v-model="selectedAnlId"
                    class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
                    @change="onAnoChange"
                >
                    <option v-for="anl in anosOrdenados" :key="anl.anl_id" :value="anl.anl_id">
                        {{ anl.anl_ano }}{{ anl.anl_fl_em_exercicio ? ' (em exercício)' : '' }}
                    </option>
                </select>
                <Button
                    v-if="mediasDoAno.length > 0"
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="replicar"
                >
                    <CopyPlus class="mr-2 size-4" /> Replicar p/ {{ anoSeguinte }}
                </Button>
                <Button
                    v-if="!showForm && selectedAnlId"
                    type="button"
                    size="sm"
                    class="bg-indigo-600 hover:bg-indigo-700"
                    @click="openCreate"
                >
                    <Plus class="mr-2 size-4" /> Adicionar Escola
                </Button>
            </div>
        </div>

        <!-- Média geral de referência -->
        <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span class="rounded bg-indigo-100 px-2 py-0.5 font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                Média geral do ano: {{ mediaGeral != null ? mediaGeral.toFixed(1) : '— (defina no Ano Letivo)' }}
            </span>
            <span class="rounded bg-fuchsia-100 px-2 py-0.5 font-medium text-fuchsia-700 dark:bg-fuchsia-900/40 dark:text-fuchsia-300">
                Conceito de aprovação: {{ conceitoGeral ? conceitoGeral.cnc_sigla : '—' }}
            </span>
            <span>Cadastre aqui apenas as escolas com média diferente da geral.</span>
        </div>

        <!-- Formulário inline -->
        <div v-if="showForm" class="rounded-lg border bg-background p-4">
            <h4 class="mb-4 text-sm font-semibold">
                {{ editing ? 'Editar média da escola' : 'Cadastrar média de escola' }}
            </h4>
            <div class="grid gap-4 sm:grid-cols-12">
                <div class="grid gap-1.5 sm:col-span-6">
                    <FormLabel for="mde_esc_id" :required="true">Escola</FormLabel>
                    <EscolaCombobox
                        v-model="form.mde_esc_id"
                        :initial="escolaInicial"
                        :invalid="!!errors.mde_esc_id"
                        placeholder="Buscar escola..."
                    />
                    <InputError :message="errors.mde_esc_id" />
                </div>
                <div class="grid gap-1.5 sm:col-span-3">
                    <FormLabel for="mde_media" :required="true">Média numérica</FormLabel>
                    <Input
                        id="mde_media"
                        v-model.number="form.mde_media"
                        type="number"
                        min="0"
                        max="10"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="Ex.: 7"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.mde_media }"
                    />
                    <InputError :message="errors.mde_media" />
                </div>
                <div class="grid gap-1.5 sm:col-span-3">
                    <FormLabel for="mde_cnc_id">Média conceitual</FormLabel>
                    <select
                        id="mde_cnc_id"
                        v-model="form.mde_cnc_id"
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.mde_cnc_id }"
                    >
                        <option :value="null">— Sem conceito —</option>
                        <option v-for="c in conceitosOrdenados" :key="c.cnc_id" :value="c.cnc_id">{{ labelConceito(c) }}</option>
                    </select>
                    <InputError :message="errors.mde_cnc_id" />
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
                        <th class="px-3 py-2 font-semibold">Escola</th>
                        <th class="px-3 py-2 text-center font-semibold">Média</th>
                        <th class="px-3 py-2 text-center font-semibold">Conceito</th>
                        <th class="px-3 py-2 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="mediasDoAno.length === 0">
                        <td colspan="4" class="px-3 py-6 text-center text-muted-foreground">
                            Nenhuma escola com média específica neste ano letivo.
                        </td>
                    </tr>
                    <tr
                        v-for="(m, idx) in mediasDoAno"
                        :key="m.mde_id"
                        :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                    >
                        <td class="px-3 py-2 font-medium">{{ m.escola?.esc_nome ?? '—' }}</td>
                        <td class="px-3 py-2 text-center">
                            <span :class="difereDaGeral(m) ? 'font-semibold text-amber-700 dark:text-amber-300' : ''">
                                {{ Number(m.mde_media).toFixed(1) }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-center">{{ m.conceito?.cnc_sigla ?? siglaConceito(m.mde_cnc_id) }}</td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <Button type="button" variant="ghost" size="sm" @click="openEdit(m)" aria-label="Editar">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                    @click="remove(m)"
                                    aria-label="Remover"
                                >
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
