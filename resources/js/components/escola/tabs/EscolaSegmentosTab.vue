<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import type { AnoLetivoOption, EscolaSegmento, EscolaSegmentoFormData, SerieOption } from '@/types/escola_segmento';
import type { Segmento } from '@/types/segmento';
import { router, useForm } from '@inertiajs/vue3';
import { BookOpen, Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    escId: number;
    escolaSegmentos: EscolaSegmento[];
    segmentos: Pick<Segmento, 'seg_id' | 'seg_nome_reduzido'>[];
    anosLetivos: AnoLetivoOption[];
}>();

const showForm = ref(false);
const editingId = ref<number | null>(null);
const series = ref<SerieOption[]>([]);
const loadingSeries = ref(false);
const pendingSeriesIds = ref<{ inicio: number | null; fim: number | null } | null>(null);

const form = useForm<EscolaSegmentoFormData>({
    seg_id: null,
    anl_id_inicio: null,
    anl_id_fim: null,
    ser_id_inicio: null,
    ser_id_fim: null,
    esg_fl_ativo: true,
    esg_motivo: '',
});

watch(() => form.seg_id, async (segId) => {
    form.ser_id_inicio = null;
    form.ser_id_fim = null;
    series.value = [];

    if (!segId) return;

    loadingSeries.value = true;
    try {
        const res = await fetch(`/api/series?seg_id=${segId}`);
        series.value = await res.json();

        if (pendingSeriesIds.value) {
            form.ser_id_inicio = pendingSeriesIds.value.inicio;
            form.ser_id_fim = pendingSeriesIds.value.fim;
            pendingSeriesIds.value = null;
        }
    } finally {
        loadingSeries.value = false;
    }
});

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    series.value = [];
    showForm.value = true;
};

const openEdit = (esg: EscolaSegmento) => {
    editingId.value = esg.esg_id;
    form.anl_id_inicio = esg.anl_id_inicio;
    form.anl_id_fim = esg.anl_id_fim;
    form.esg_fl_ativo = esg.esg_fl_ativo;
    form.esg_motivo = esg.esg_motivo ?? '';
    pendingSeriesIds.value = { inicio: esg.ser_id_inicio, fim: esg.ser_id_fim };
    form.seg_id = esg.seg_id; // dispara watch → carrega séries → aplica pendingSeriesIds
    showForm.value = true;
};

const cancel = () => {
    showForm.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    series.value = [];
};

const submit = () => {
    const opts = { preserveScroll: true, preserveState: true, onSuccess: () => cancel() };
    if (editingId.value) {
        form.transform((d) => ({ ...d, _method: 'put' }))
            .post(`/escolas/${props.escId}/segmentos/${editingId.value}`, opts);
    } else {
        form.post(`/escolas/${props.escId}/segmentos`, opts);
    }
};

const remove = (esg: EscolaSegmento) => {
    if (!confirm(`Remover "${esg.segmento?.seg_nome_reduzido}" da escola?`)) return;
    router.delete(`/escolas/${props.escId}/segmentos/${esg.esg_id}`, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <div class="grid gap-4">
        <!-- Cabeçalho -->
        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Segmentos ofertados por esta escola. O registro é válido do ano letivo inicial
                até o ano letivo final (vazio = sem encerramento previsto).
            </p>
            <div class="flex items-center gap-2">
                <RefreshButton />
                <Button
                    type="button"
                    @click="openCreate"
                    class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                >
                    <Plus class="mr-2 size-4" /> Adicionar segmento
                </Button>
            </div>
        </div>

        <!-- Tabela de registros -->
        <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3">Segmento</th>
                        <th class="px-4 py-3 text-center">Ano Letivo Início</th>
                        <th class="px-4 py-3 text-center">Ano Letivo Fim</th>
                        <th class="px-4 py-3">Ano Escolar Inicial</th>
                        <th class="px-4 py-3">Ano Escolar Final</th>
                        <th class="px-4 py-3 text-center">Ativo</th>
                        <th class="px-4 py-3">Motivo</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="escolaSegmentos.length === 0">
                        <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">
                            Nenhum segmento cadastrado para esta escola.
                        </td>
                    </tr>
                    <tr
                        v-for="esg in escolaSegmentos"
                        :key="esg.esg_id"
                        class="transition-colors hover:bg-muted/30"
                        :class="{ 'bg-indigo-50/40 dark:bg-indigo-900/10': editingId === esg.esg_id }"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="grid size-8 place-items-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                    <BookOpen class="size-3.5" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ esg.segmento?.seg_nome_reduzido ?? '—' }}</span>
                                    <span class="text-xs text-muted-foreground line-clamp-1">{{ esg.segmento?.seg_nome_completo }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center tabular-nums font-medium">
                            {{ esg.ano_letivo_inicio?.anl_ano ?? esg.anl_id_inicio }}
                        </td>
                        <td class="px-4 py-3 text-center tabular-nums text-muted-foreground">
                            {{ esg.ano_letivo_fim?.anl_ano ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ esg.serie_inicio?.ser_nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ esg.serie_fim?.ser_nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <Check v-if="esg.esg_fl_ativo && !esg.anl_id_fim" class="mx-auto size-4 text-emerald-600" />
                            <X v-else class="mx-auto size-4 text-slate-400" />
                        </td>
                        <td class="px-4 py-3 max-w-[180px]">
                            <span class="line-clamp-2 text-xs text-muted-foreground">{{ esg.esg_motivo ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <Button type="button" variant="ghost" size="sm" @click="openEdit(esg)">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                    @click="remove(esg)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Formulário inline -->
        <div v-if="showForm" class="rounded-xl border bg-card p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold text-slate-800 dark:text-slate-200">
                {{ editingId ? 'Editar segmento' : 'Adicionar segmento' }}
            </h3>

            <div class="grid gap-4 sm:grid-cols-4">
                <!-- Segmento -->
                <div class="grid gap-2 sm:col-span-2">
                    <FormLabel for="esg_seg_id" :required="true">Segmento</FormLabel>
                    <select
                        id="esg_seg_id"
                        v-model="form.seg_id"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    >
                        <option :value="null" disabled>Selecione...</option>
                        <option v-for="seg in segmentos" :key="seg.seg_id" :value="seg.seg_id">
                            {{ seg.seg_nome_reduzido }}
                        </option>
                    </select>
                    <InputError :message="form.errors.seg_id" />
                </div>

                <!-- Ativo -->
                <div class="flex items-center gap-3 self-end pb-1 sm:col-span-2">
                    <Switch id="esg_fl_ativo" v-model="form.esg_fl_ativo" />
                    <FormLabel for="esg_fl_ativo" class="font-normal">Segmento ativo na escola</FormLabel>
                </div>

                <!-- Ano Letivo Início -->
                <div class="grid gap-2">
                    <FormLabel for="esg_anl_inicio" :required="true">Ano Letivo Início</FormLabel>
                    <select
                        id="esg_anl_inicio"
                        v-model="form.anl_id_inicio"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    >
                        <option :value="null" disabled>Selecione...</option>
                        <option v-for="anl in anosLetivos" :key="anl.anl_id" :value="anl.anl_id">
                            {{ anl.anl_ano }}{{ anl.anl_fl_em_exercicio ? ' (em exercício)' : '' }}
                        </option>
                    </select>
                    <InputError :message="form.errors.anl_id_inicio" />
                </div>

                <!-- Ano Letivo Fim -->
                <div class="grid gap-2">
                    <FormLabel for="esg_anl_fim">Ano Letivo Fim</FormLabel>
                    <select
                        id="esg_anl_fim"
                        v-model="form.anl_id_fim"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    >
                        <option :value="null">Sem encerramento</option>
                        <option v-for="anl in anosLetivos" :key="anl.anl_id" :value="anl.anl_id">
                            {{ anl.anl_ano }}
                        </option>
                    </select>
                    <InputError :message="form.errors.anl_id_fim" />
                </div>

                <!-- Ano Escolar Inicial -->
                <div class="grid gap-2">
                    <FormLabel for="esg_ser_inicio" :required="true">Ano Escolar Inicial</FormLabel>
                    <select
                        id="esg_ser_inicio"
                        v-model="form.ser_id_inicio"
                        :disabled="!form.seg_id || loadingSeries"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-background dark:text-foreground"
                    >
                        <option :value="null" disabled>
                            {{ !form.seg_id ? 'Selecione o segmento...' : loadingSeries ? 'Carregando...' : 'Selecione...' }}
                        </option>
                        <option v-for="ser in series" :key="ser.ser_id" :value="ser.ser_id">
                            {{ ser.ser_nome }}
                        </option>
                    </select>
                    <InputError :message="form.errors.ser_id_inicio" />
                </div>

                <!-- Ano Escolar Final -->
                <div class="grid gap-2">
                    <FormLabel for="esg_ser_fim" :required="true">Ano Escolar Final</FormLabel>
                    <select
                        id="esg_ser_fim"
                        v-model="form.ser_id_fim"
                        :disabled="!form.seg_id || loadingSeries"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-background dark:text-foreground"
                    >
                        <option :value="null" disabled>
                            {{ !form.seg_id ? 'Selecione o segmento...' : loadingSeries ? 'Carregando...' : 'Selecione...' }}
                        </option>
                        <option v-for="ser in series" :key="ser.ser_id" :value="ser.ser_id">
                            {{ ser.ser_nome }}
                        </option>
                    </select>
                    <InputError :message="form.errors.ser_id_fim" />
                </div>

                <!-- Motivo -->
                <div class="grid gap-2 sm:col-span-4">
                    <FormLabel for="esg_motivo">Motivo</FormLabel>
                    <textarea
                        id="esg_motivo"
                        v-model="form.esg_motivo"
                        rows="2"
                        placeholder="Opcional — justificativa para inclusão ou encerramento do segmento..."
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <InputError :message="form.errors.esg_motivo" />
                </div>

                <!-- Ações do formulário -->
                <div class="flex gap-2 sm:col-span-4">
                    <Button
                        type="button"
                        @click="submit"
                        :disabled="form.processing"
                        class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    >
                        {{ editingId ? 'Salvar alterações' : 'Adicionar' }}
                    </Button>
                    <Button type="button" variant="outline" @click="cancel">Cancelar</Button>
                </div>
            </div>
        </div>
    </div>
</template>
