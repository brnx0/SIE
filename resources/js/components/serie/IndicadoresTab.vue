<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { SerieIndicador, SerieParaReplicar } from '@/types/serie';
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Copy, Loader2, LoaderCircle, Pencil, Plus, Save, Trash2, X, XCircle } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    serId: number;
    indicadores: SerieIndicador[];
    seriesParaReplicar: SerieParaReplicar[];
}>();

const list = computed<SerieIndicador[]>(() => props.indicadores ?? []);

const showForm = ref(false);
const editing = ref<SerieIndicador | null>(null);
const formData = reactive({ ind_descricao: '', ind_fl_ativo: true });
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const abrirCriar = () => {
    editing.value = null;
    formData.ind_descricao = '';
    formData.ind_fl_ativo = true;
    errors.value = {};
    showForm.value = true;
};

const abrirEditar = (ind: SerieIndicador) => {
    editing.value = ind;
    formData.ind_descricao = ind.ind_descricao;
    formData.ind_fl_ativo = ind.ind_fl_ativo;
    errors.value = {};
    showForm.value = true;
};

const cancelar = () => {
    showForm.value = false;
    editing.value = null;
    errors.value = {};
};

const salvar = () => {
    processing.value = true;
    errors.value = {};

    const onOk = () => { showForm.value = false; editing.value = null; };
    const onErr = (errs: Record<string, string>) => { errors.value = errs; };
    const onEnd = () => { processing.value = false; };

    if (editing.value) {
        router.put(
            `/series/${props.serId}/indicadores/${editing.value.ind_id}`,
            { ...formData },
            { preserveScroll: true, preserveState: true, onSuccess: onOk, onError: onErr, onFinish: onEnd },
        );
    } else {
        router.post(
            `/series/${props.serId}/indicadores`,
            { ...formData },
            { preserveScroll: true, preserveState: true, onSuccess: onOk, onError: onErr, onFinish: onEnd },
        );
    }
};

const remover = (ind: SerieIndicador) => {
    if (!confirm(`Remover indicador "${ind.ind_descricao.substring(0, 60)}${ind.ind_descricao.length > 60 ? '…' : ''}"?`)) return;
    router.delete(`/series/${props.serId}/indicadores/${ind.ind_id}`, {
        preserveScroll: true,
        preserveState: true,
    });
};

const toggleAtivo = (ind: SerieIndicador) => {
    router.put(`/series/${props.serId}/indicadores/${ind.ind_id}`, {
        ind_descricao: ind.ind_descricao,
        ind_fl_ativo: !ind.ind_fl_ativo,
    }, { preserveScroll: true, preserveState: true });
};

// --- Replicar ---
const replicarOpen = ref(false);
const replicarOrigemId = ref<number | ''>('');
const replicarSubstituir = ref(false);
const replicarPreview = ref<SerieIndicador[]>([]);
const replicarLoadingPreview = ref(false);
const replicarProcessando = ref(false);
const replicarErrors = ref<Record<string, string>>({});

const abrirReplicar = () => {
    replicarOrigemId.value = '';
    replicarSubstituir.value = false;
    replicarPreview.value = [];
    replicarErrors.value = {};
    replicarOpen.value = true;
};

const fecharReplicar = () => {
    replicarOpen.value = false;
};

watch(replicarOrigemId, async (id) => {
    replicarPreview.value = [];
    replicarErrors.value = {};
    if (!id) return;
    replicarLoadingPreview.value = true;
    try {
        const r = await fetch(`/api/series/${id}/indicadores`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (r.ok) {
            const all = (await r.json()) as SerieIndicador[];
            replicarPreview.value = all.filter((i) => i.ind_fl_ativo);
        }
    } finally {
        replicarLoadingPreview.value = false;
    }
});

const replicar = () => {
    if (!replicarOrigemId.value) return;
    replicarProcessando.value = true;
    replicarErrors.value = {};

    router.post(`/series/${props.serId}/indicadores/replicar`, {
        ser_id_origem: Number(replicarOrigemId.value),
        substituir: replicarSubstituir.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { replicarOpen.value = false; },
        onError: (errs) => { replicarErrors.value = errs as Record<string, string>; },
        onFinish: () => { replicarProcessando.value = false; },
    });
};

const podeReplicar = computed(() => (props.seriesParaReplicar ?? []).length > 0);
</script>

<template>
    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">
                {{ list.length }} indicador{{ list.length !== 1 ? 'es' : '' }} cadastrado{{ list.length !== 1 ? 's' : '' }}
            </p>
            <div class="flex items-center gap-2">
                <RefreshButton />
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="!podeReplicar"
                    :title="podeReplicar ? 'Replicar indicadores de outra série' : 'Nenhuma outra série possui indicadores cadastrados'"
                    @click="abrirReplicar"
                >
                    <Copy class="mr-2 size-4" /> Replicar de outra série
                </Button>
                <Button
                    v-if="!showForm"
                    type="button"
                    size="sm"
                    class="bg-indigo-600 hover:bg-indigo-700"
                    @click="abrirCriar"
                >
                    <Plus class="mr-2 size-4" /> Adicionar Indicador
                </Button>
            </div>
        </div>

        <div v-if="showForm" class="rounded-lg border bg-background p-4">
            <h4 class="mb-4 text-sm font-semibold">
                {{ editing ? 'Editar Indicador' : 'Novo Indicador' }}
            </h4>

            <div class="grid gap-4">
                <div class="grid gap-1.5">
                    <FormLabel for="ind_descricao" :required="true">Descrição</FormLabel>
                    <textarea
                        id="ind_descricao"
                        v-model="formData.ind_descricao"
                        maxlength="1000"
                        rows="3"
                        class="flex w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-background dark:text-foreground"
                        :class="{ 'border-red-500 ring-1 ring-red-500': errors.ind_descricao }"
                        placeholder="Descreva o indicador..."
                    />
                    <InputError :message="errors.ind_descricao" />
                </div>

                <div class="flex items-center gap-3">
                    <Switch id="ind_fl_ativo_form" v-model="formData.ind_fl_ativo" />
                    <Label for="ind_fl_ativo_form" class="text-sm">Ativo</Label>
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="cancelar">
                    <X class="mr-2 size-4" /> Cancelar
                </Button>
                <Button
                    type="button"
                    size="sm"
                    class="bg-indigo-600 hover:bg-indigo-700"
                    :disabled="processing || !formData.ind_descricao.trim()"
                    @click="salvar"
                >
                    <LoaderCircle v-if="processing" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    Salvar
                </Button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Descrição</th>
                        <th class="w-24 px-3 py-2 text-center font-semibold">Ativo</th>
                        <th class="w-28 px-3 py-2 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="list.length === 0">
                        <td colspan="3" class="px-3 py-6 text-center text-muted-foreground">
                            Nenhum indicador cadastrado.
                        </td>
                    </tr>
                    <tr
                        v-for="(ind, idx) in list"
                        :key="ind.ind_id"
                        :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                    >
                        <td class="whitespace-pre-wrap px-3 py-2" :class="{ 'text-muted-foreground line-through': !ind.ind_fl_ativo }">
                            {{ ind.ind_descricao }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            <button
                                type="button"
                                class="rounded p-1 hover:bg-muted"
                                :title="ind.ind_fl_ativo ? 'Clique p/ inativar' : 'Clique p/ ativar'"
                                @click="toggleAtivo(ind)"
                            >
                                <CheckCircle2 v-if="ind.ind_fl_ativo" class="mx-auto size-4 text-emerald-600" />
                                <XCircle v-else class="mx-auto size-4 text-rose-400" />
                            </button>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex justify-end gap-1">
                                <Button type="button" variant="ghost" size="sm" @click="abrirEditar(ind)" aria-label="Editar">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                    @click="remover(ind)"
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

        <!-- Dialog Replicar -->
        <Dialog :open="replicarOpen" @update:open="(v) => (replicarOpen = v)">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Replicar Indicadores</DialogTitle>
                    <DialogDescription>
                        Copia indicadores ativos de outra série para esta. Duplicatas (mesma descrição) são ignoradas.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Série de Origem</FormLabel>
                        <select
                            v-model.number="replicarOrigemId"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            :class="{ 'border-red-500': replicarErrors.ser_id_origem }"
                        >
                            <option value="">Selecione...</option>
                            <option v-for="s in seriesParaReplicar" :key="s.ser_id" :value="s.ser_id">
                                {{ s.seg_nome ? `[${s.seg_nome}] ` : '' }}{{ s.ser_nome }}
                            </option>
                        </select>
                        <InputError :message="replicarErrors.ser_id_origem" />
                    </div>

                    <div class="flex items-center gap-3 rounded-md border bg-muted/30 px-3 py-2">
                        <Switch id="replicar_substituir" v-model="replicarSubstituir" />
                        <Label for="replicar_substituir" class="text-sm">
                            Substituir indicadores existentes (remove os atuais antes de copiar)
                        </Label>
                    </div>

                    <div v-if="replicarOrigemId" class="rounded-md border">
                        <div class="border-b bg-muted/30 px-3 py-2 text-xs font-semibold text-muted-foreground">
                            Preview ({{ replicarPreview.length }} indicador{{ replicarPreview.length !== 1 ? 'es' : '' }} ativo{{ replicarPreview.length !== 1 ? 's' : '' }})
                        </div>
                        <div v-if="replicarLoadingPreview" class="px-3 py-6 text-center text-sm text-muted-foreground">
                            <Loader2 class="mx-auto size-4 animate-spin" /> Carregando...
                        </div>
                        <div v-else-if="replicarPreview.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
                            Série de origem não possui indicadores ativos.
                        </div>
                        <ul v-else class="max-h-64 divide-y overflow-y-auto text-sm">
                            <li v-for="(i, idx) in replicarPreview" :key="i.ind_id" class="px-3 py-2">
                                <span class="mr-2 text-xs text-muted-foreground">{{ idx + 1 }}.</span>{{ i.ind_descricao }}
                            </li>
                        </ul>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="fecharReplicar">Cancelar</Button>
                    <Button
                        type="button"
                        :disabled="!replicarOrigemId || replicarProcessando || replicarPreview.length === 0"
                        class="bg-indigo-600 hover:bg-indigo-700"
                        @click="replicar"
                    >
                        <LoaderCircle v-if="replicarProcessando" class="mr-2 size-4 animate-spin" />
                        <Copy v-else class="mr-2 size-4" />
                        Replicar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
