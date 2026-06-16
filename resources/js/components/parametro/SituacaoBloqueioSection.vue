<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import type { SituacaoBloqueio, TurmaAlunoSituacaoResumo } from '@/types/parametro';
import { router } from '@inertiajs/vue3';
import { Ban, LoaderCircle, Plus, Save, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    situacoesBloqueio: SituacaoBloqueio[];
    situacoes: TurmaAlunoSituacaoResumo[];
}>();

const showForm = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const selectedTasCod = ref<number | null>(null);

// Situações ainda não adicionadas à lista de bloqueio
const disponiveis = computed(() => {
    const usados = new Set(props.situacoesBloqueio.map((s) => s.sba_tas_cod));
    return props.situacoes.filter((s) => !usados.has(s.tas_cod));
});

const openCreate = () => {
    selectedTasCod.value = null;
    errors.value = {};
    showForm.value = true;
};

const cancel = () => {
    showForm.value = false;
    errors.value = {};
};

const save = () => {
    processing.value = true;
    errors.value = {};
    router.post('/parametros/situacoes-bloqueio', { sba_tas_cod: selectedTasCod.value }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { showForm.value = false; selectedTasCod.value = null; },
        onError: (errs) => { errors.value = errs; },
        onFinish: () => { processing.value = false; },
    });
};

const remove = (s: SituacaoBloqueio) => {
    if (!confirm(`Remover "${s.situacao?.tas_descricao ?? 'situação'}" da lista de bloqueio?`)) return;
    router.delete(`/parametros/situacoes-bloqueio/${s.sba_id}`, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Ban class="size-4 text-indigo-600" />
                <h3 class="text-sm font-semibold">Situações que Bloqueiam Registros</h3>
            </div>
            <div class="flex items-center gap-3">
                <RefreshButton />
                <Button v-if="!showForm && disponiveis.length > 0" type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" @click="openCreate">
                    <Plus class="mr-2 size-4" /> Adicionar Situação
                </Button>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            Alunos com qualquer destas situações ficam bloqueados para novos registros (matrícula, lançamentos etc.).
        </p>

        <!-- Formulário inline -->
        <div v-if="showForm" class="rounded-lg border bg-background p-4">
            <div class="grid gap-1.5 sm:max-w-md">
                <FormLabel for="sba_tas_cod" :required="true">Situação</FormLabel>
                <select
                    id="sba_tas_cod"
                    v-model="selectedTasCod"
                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
                    :class="{ 'border-red-500 ring-1 ring-red-500': errors.sba_tas_cod }"
                >
                    <option :value="null">Selecione...</option>
                    <option v-for="s in disponiveis" :key="s.tas_cod" :value="s.tas_cod">{{ s.tas_descricao }}</option>
                </select>
                <InputError :message="errors.sba_tas_cod" />
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="cancel">
                    <X class="mr-2 size-4" /> Cancelar
                </Button>
                <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" :disabled="processing || !selectedTasCod" @click="save">
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
                        <th class="px-3 py-2 font-semibold">Código</th>
                        <th class="px-3 py-2 font-semibold">Situação</th>
                        <th class="px-3 py-2 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="situacoesBloqueio.length === 0">
                        <td colspan="3" class="px-3 py-6 text-center text-muted-foreground">Nenhuma situação de bloqueio cadastrada.</td>
                    </tr>
                    <tr v-for="(s, idx) in situacoesBloqueio" :key="s.sba_id"
                        :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                        <td class="px-3 py-2 font-medium">{{ s.sba_tas_cod }}</td>
                        <td class="px-3 py-2">{{ s.situacao?.tas_descricao ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">
                            <Button type="button" variant="ghost" size="sm"
                                class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                @click="remove(s)" aria-label="Remover">
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
