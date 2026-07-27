<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { DiarioBordo, OcorrenciaItem } from '@/types/transporte';
import { router } from '@inertiajs/vue3';
import { AlertTriangle, Clock, Loader2, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    diario: DiarioBordo;
    ocorrencias: OcorrenciaItem[];
    tipos: Record<string, string>;
    bloqueado?: boolean;
}>();

const list = computed<OcorrenciaItem[]>(() => props.ocorrencias ?? []);

const showForm = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = reactive<{ toc_tipo: string; toc_hr: string; toc_descricao: string }>({
    toc_tipo: '',
    toc_hr: '',
    toc_descricao: '',
});

const descricaoEl = ref<HTMLTextAreaElement | null>(null);

// Ajusta a altura do textarea ao conteúdo enquanto digita.
const autosize = () => {
    const el = descricaoEl.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
};

const resetForm = () => {
    form.toc_tipo = '';
    form.toc_hr = '';
    form.toc_descricao = '';
    errors.value = {};
    showForm.value = false;
};

const openForm = () => {
    resetForm();
    showForm.value = true;
};

const submit = () => {
    processing.value = true;
    errors.value = {};
    router.post(`/transporte/diario-bordo/${props.diario.tdb_id}/ocorrencias`, { ...form }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => resetForm(),
        onError: (e: Record<string, string>) => { errors.value = e; },
        onFinish: () => { processing.value = false; },
    });
};

const remove = (o: OcorrenciaItem) => {
    if (!confirm('Remover esta ocorrência?')) return;
    router.delete(`/transporte/diario-bordo/${props.diario.tdb_id}/ocorrencias/${o.toc_id}`, {
        preserveScroll: true,
        preserveState: true,
    });
};

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground';
</script>

<template>
    <div class="grid gap-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Ocorrências da viagem</h3>
            <Button v-if="!showForm && !bloqueado" type="button" size="sm" variant="outline" @click="openForm">
                <Plus class="mr-1 size-4" /> Registrar ocorrência
            </Button>
        </div>

        <div v-if="showForm" class="rounded-xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm dark:border-amber-800 dark:bg-amber-900/20">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-sm font-medium">Nova ocorrência</h4>
                <button type="button" class="rounded p-1 hover:bg-muted" @click="resetForm"><X class="size-4" /></button>
            </div>
            <div class="grid gap-4 sm:grid-cols-12">
                <div class="grid gap-1.5 sm:col-span-4">
                    <FormLabel :required="true">Tipo</FormLabel>
                    <select v-model="form.toc_tipo" :class="[selectClass, errors.toc_tipo ? 'border-rose-500' : '']">
                        <option value="" disabled>Selecione...</option>
                        <option v-for="(label, key) in tipos" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <InputError :message="errors.toc_tipo" />
                </div>
                <div class="grid gap-1.5 sm:col-span-3">
                    <FormLabel>Hora</FormLabel>
                    <Input v-model="form.toc_hr" type="time" />
                    <InputError :message="errors.toc_hr" />
                </div>
                <div class="grid gap-1.5 sm:col-span-12">
                    <div class="flex items-center justify-between">
                        <FormLabel :required="true">Descrição</FormLabel>
                        <span class="text-xs tabular-nums text-muted-foreground">{{ form.toc_descricao.length }}/500</span>
                    </div>
                    <textarea
                        ref="descricaoEl"
                        v-model="form.toc_descricao"
                        maxlength="500"
                        :class="selectClass + ' min-h-[80px] resize-none overflow-hidden py-2'"
                        placeholder="Descreva a ocorrência..."
                        @input="autosize"
                    ></textarea>
                    <InputError :message="errors.toc_descricao" />
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="resetForm">Cancelar</Button>
                <Button type="button" size="sm" :disabled="processing" class="bg-amber-600 text-white hover:bg-amber-700" @click="submit">
                    <Loader2 v-if="processing" class="mr-1 size-4 animate-spin" /> Registrar
                </Button>
            </div>
        </div>

        <div v-if="list.length === 0 && !showForm" class="rounded-xl border-2 border-dashed bg-card py-10 text-center text-sm text-muted-foreground shadow-sm">
            Nenhuma ocorrência registrada nesta viagem.
        </div>

        <div v-for="o in list" :key="o.toc_id" class="flex items-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
            <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                <AlertTriangle class="size-4" />
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium">{{ tipos[o.toc_tipo] ?? o.toc_tipo }}</span>
                    <span v-if="o.toc_hr" class="inline-flex items-center gap-1 text-xs text-muted-foreground"><Clock class="size-3" /> {{ o.toc_hr }}</span>
                </div>
                <p class="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{{ o.toc_descricao }}</p>
            </div>
            <button v-if="!bloqueado" type="button" class="rounded p-1.5 hover:bg-muted" title="Remover" @click="remove(o)"><Trash2 class="size-4 text-rose-500" /></button>
        </div>
    </div>
</template>
