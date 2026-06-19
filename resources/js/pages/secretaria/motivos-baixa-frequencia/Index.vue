<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import Switch from '@/components/common/Switch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { MotivoBaixaFrequencia } from '@/types/parametro';
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, Loader2, Pencil, Plus, Trash2, X, XCircle } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    motivos: MotivoBaixaFrequencia[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Motivo Baixa Frequência', href: '/secretaria/motivos-baixa-frequencia' },
];

const lista = computed(() => props.motivos ?? []);

const showForm = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = reactive({ mbf_descricao: '', mbf_fl_abona: true, mbf_fl_ativo: true });

const openForm = () => {
    form.mbf_descricao = '';
    form.mbf_fl_abona = true;
    form.mbf_fl_ativo = true;
    errors.value = {};
    showForm.value = true;
};

const cancelForm = () => { showForm.value = false; errors.value = {}; };

const submit = () => {
    processing.value = true;
    errors.value = {};

    router.post('/secretaria/motivos-baixa-frequencia', { ...form }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { showForm.value = false; },
        onError: (e) => { errors.value = e as Record<string, string>; },
        onFinish: () => { processing.value = false; },
    });
};

const remove = (m: MotivoBaixaFrequencia) => {
    if (!confirm(`Remover o motivo "${m.mbf_descricao}"?`)) return;
    router.delete(`/secretaria/motivos-baixa-frequencia/${m.mbf_id}`, { preserveScroll: true, preserveState: true });
};

const editingId = ref<number | null>(null);
const editProcessing = ref(false);
const editErrors = ref<Record<string, string>>({});
const editForm = reactive({ mbf_descricao: '', mbf_fl_abona: true, mbf_fl_ativo: true });

const startEdit = (m: MotivoBaixaFrequencia) => {
    cancelForm();
    editingId.value = m.mbf_id;
    editForm.mbf_descricao = m.mbf_descricao;
    editForm.mbf_fl_abona = m.mbf_fl_abona;
    editForm.mbf_fl_ativo = m.mbf_fl_ativo;
    editErrors.value = {};
};

const cancelEdit = () => { editingId.value = null; editErrors.value = {}; };

const saveEdit = (m: MotivoBaixaFrequencia) => {
    editProcessing.value = true;
    editErrors.value = {};

    router.put(`/secretaria/motivos-baixa-frequencia/${m.mbf_id}`, { ...editForm }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { editingId.value = null; },
        onError: (e) => { editErrors.value = e as Record<string, string>; },
        onFinish: () => { editProcessing.value = false; },
    });
};

const toggleAtivo = (m: MotivoBaixaFrequencia) => {
    router.put(`/secretaria/motivos-baixa-frequencia/${m.mbf_id}`, {
        mbf_descricao: m.mbf_descricao,
        mbf_fl_ativo: !m.mbf_fl_ativo,
    }, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <Head title="Motivo Baixa Frequência" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-4 p-4 md:p-6">
            <div class="flex items-end justify-between gap-2">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Motivo de Baixa Frequência</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Cadastro dos motivos de justificativa de falta / baixa frequência.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <RefreshButton />
                    <Button v-if="!showForm" type="button" class="bg-indigo-600 hover:bg-indigo-700" @click="openForm">
                        <Plus class="mr-2 size-4" /> Novo Motivo
                    </Button>
                </div>
            </div>

            <div v-if="showForm" class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-800 dark:bg-indigo-900/20">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-semibold">Novo Motivo</h4>
                    <button type="button" class="rounded p-1 hover:bg-muted" @click="cancelForm">
                        <X class="size-4" />
                    </button>
                </div>

                <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Descrição</FormLabel>
                        <Input
                            v-model="form.mbf_descricao"
                            maxlength="255"
                            placeholder="Ex.: Doença/óbito na família"
                            :class="{ 'border-red-500': errors.mbf_descricao }"
                        />
                        <InputError :message="errors.mbf_descricao" />
                    </div>

                    <div class="flex items-end gap-4 pb-1">
                        <div class="flex items-center gap-2">
                            <Switch id="novo_mbf_fl_abona" v-model="form.mbf_fl_abona" />
                            <Label for="novo_mbf_fl_abona" class="text-sm font-normal">Abona falta</Label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Switch id="novo_mbf_fl_ativo" v-model="form.mbf_fl_ativo" />
                            <Label for="novo_mbf_fl_ativo" class="text-sm font-normal">Ativo</Label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" @click="cancelForm">Cancelar</Button>
                    <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" :disabled="processing" @click="submit">
                        <Loader2 v-if="processing" class="mr-2 size-4 animate-spin" />
                        Cadastrar
                    </Button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-indigo-600 text-white">
                        <tr>
                            <th class="w-20 px-3 py-2 text-center font-semibold">Código</th>
                            <th class="px-3 py-2 font-semibold">Descrição</th>
                            <th class="w-24 px-3 py-2 text-center font-semibold">Abona falta</th>
                            <th class="w-24 px-3 py-2 text-center font-semibold">Ativo</th>
                            <th class="w-32 px-3 py-2 text-right font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="lista.length === 0">
                            <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">
                                Nenhum motivo cadastrado.
                            </td>
                        </tr>

                        <template v-for="(m, idx) in lista" :key="m.mbf_id">
                            <tr v-if="editingId === m.mbf_id" class="bg-indigo-50/60 dark:bg-indigo-900/20">
                                <td class="px-3 py-2 text-center font-medium tabular-nums text-muted-foreground">{{ m.mbf_id }}</td>
                                <td class="px-3 py-2">
                                    <Input v-model="editForm.mbf_descricao" maxlength="255" class="h-8 text-sm" :class="{ 'border-red-500': editErrors.mbf_descricao }" />
                                    <InputError :message="editErrors.mbf_descricao" />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <Switch :id="`edit_mbf_abona_${m.mbf_id}`" v-model="editForm.mbf_fl_abona" />
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <Switch :id="`edit_mbf_ativo_${m.mbf_id}`" v-model="editForm.mbf_fl_ativo" />
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button type="button" variant="ghost" size="sm" :disabled="editProcessing" @click="saveEdit(m)">
                                            <Loader2 v-if="editProcessing" class="size-4 animate-spin" />
                                            <span v-else class="text-xs">Salvar</span>
                                        </Button>
                                        <Button type="button" variant="ghost" size="sm" @click="cancelEdit">
                                            <X class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-else :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                                <td class="px-3 py-2 text-center font-medium tabular-nums" :class="{ 'text-muted-foreground': !m.mbf_fl_ativo }">
                                    {{ m.mbf_id }}
                                </td>
                                <td class="px-3 py-2" :class="{ 'text-muted-foreground line-through': !m.mbf_fl_ativo }">
                                    {{ m.mbf_descricao }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span :class="['inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold', m.mbf_fl_abona ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300']">
                                        {{ m.mbf_fl_abona ? 'Sim' : 'Não' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button
                                        type="button"
                                        class="rounded p-1 hover:bg-muted"
                                        :title="m.mbf_fl_ativo ? 'Clique p/ inativar' : 'Clique p/ ativar'"
                                        @click="toggleAtivo(m)"
                                    >
                                        <CheckCircle2 v-if="m.mbf_fl_ativo" class="mx-auto size-4 text-emerald-600" />
                                        <XCircle v-else class="mx-auto size-4 text-rose-500" />
                                    </button>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button type="button" variant="ghost" size="sm" @click="startEdit(m)" aria-label="Editar">
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
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
