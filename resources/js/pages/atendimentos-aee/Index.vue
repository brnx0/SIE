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
import type { AtendimentoAee } from '@/types/parametro';
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, Loader2, Pencil, Plus, Trash2, X, XCircle } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    atendimentosAee: AtendimentoAee[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Atendimentos AEE', href: '/atendimentos-aee' },
];

const lista = computed(() => props.atendimentosAee ?? []);

const showForm = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const form = reactive({
    ate_descricao: '',
    ate_fl_ativo: true,
});

const openForm = () => {
    form.ate_descricao = '';
    form.ate_fl_ativo = true;
    errors.value = {};
    showForm.value = true;
};

const cancelForm = () => {
    showForm.value = false;
    errors.value = {};
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    router.post('/atendimentos-aee', { ...form }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showForm.value = false;
        },
        onError: (e) => {
            errors.value = e as Record<string, string>;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

const remove = (a: AtendimentoAee) => {
    if (!confirm(`Remover atendimento "${a.ate_descricao}"?`)) return;
    router.delete(`/atendimentos-aee/${a.ate_id}`, {
        preserveScroll: true,
        preserveState: true,
    });
};

const editingId = ref<number | null>(null);
const editProcessing = ref(false);
const editErrors = ref<Record<string, string>>({});
const editForm = reactive({ ate_descricao: '', ate_fl_ativo: true });

const startEdit = (a: AtendimentoAee) => {
    cancelForm();
    editingId.value = a.ate_id;
    editForm.ate_descricao = a.ate_descricao;
    editForm.ate_fl_ativo = a.ate_fl_ativo;
    editErrors.value = {};
};

const cancelEdit = () => {
    editingId.value = null;
    editErrors.value = {};
};

const saveEdit = (a: AtendimentoAee) => {
    editProcessing.value = true;
    editErrors.value = {};

    router.put(`/atendimentos-aee/${a.ate_id}`, { ...editForm }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingId.value = null;
        },
        onError: (e) => {
            editErrors.value = e as Record<string, string>;
        },
        onFinish: () => {
            editProcessing.value = false;
        },
    });
};

const toggleAtivo = (a: AtendimentoAee) => {
    router.put(`/atendimentos-aee/${a.ate_id}`, {
        ate_descricao: a.ate_descricao,
        ate_fl_ativo: !a.ate_fl_ativo,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="Atendimentos AEE" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-4 p-4 md:p-6">
            <div class="flex items-end justify-between gap-2">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Atendimentos AEE</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Cadastro dos tipos de atendimento ofertados em turmas de Atendimento Educacional Especializado.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <RefreshButton />
                    <Button v-if="!showForm" type="button" class="bg-indigo-600 hover:bg-indigo-700" @click="openForm">
                        <Plus class="mr-2 size-4" /> Novo Atendimento
                    </Button>
                </div>
            </div>

            <div v-if="showForm" class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-800 dark:bg-indigo-900/20">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-semibold">Novo Atendimento</h4>
                    <button type="button" class="rounded p-1 hover:bg-muted" @click="cancelForm">
                        <X class="size-4" />
                    </button>
                </div>

                <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Descrição</FormLabel>
                        <Input
                            v-model="form.ate_descricao"
                            maxlength="200"
                            placeholder="Ex.: Ensino do Sistema Braile"
                            :class="{ 'border-red-500': errors.ate_descricao }"
                        />
                        <InputError :message="errors.ate_descricao" />
                    </div>

                    <div class="flex items-end gap-3 pb-1">
                        <Switch id="novo_ate_fl_ativo" v-model="form.ate_fl_ativo" />
                        <Label for="novo_ate_fl_ativo" class="text-sm font-normal">Ativo</Label>
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
                            <th class="px-3 py-2 font-semibold">Descrição</th>
                            <th class="w-24 px-3 py-2 text-center font-semibold">Ativo</th>
                            <th class="w-32 px-3 py-2 text-right font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="lista.length === 0">
                            <td colspan="3" class="px-3 py-6 text-center text-muted-foreground">
                                Nenhum atendimento cadastrado.
                            </td>
                        </tr>

                        <template v-for="(a, idx) in lista" :key="a.ate_id">
                            <tr
                                v-if="editingId === a.ate_id"
                                class="bg-indigo-50/60 dark:bg-indigo-900/20"
                            >
                                <td class="px-3 py-2">
                                    <Input
                                        v-model="editForm.ate_descricao"
                                        maxlength="200"
                                        class="h-8 text-sm"
                                        :class="{ 'border-red-500': editErrors.ate_descricao }"
                                    />
                                    <InputError :message="editErrors.ate_descricao" />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <Switch :id="`edit_ativo_${a.ate_id}`" v-model="editForm.ate_fl_ativo" />
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button type="button" variant="ghost" size="sm" :disabled="editProcessing" @click="saveEdit(a)">
                                            <Loader2 v-if="editProcessing" class="size-4 animate-spin" />
                                            <span v-else class="text-xs">Salvar</span>
                                        </Button>
                                        <Button type="button" variant="ghost" size="sm" @click="cancelEdit">
                                            <X class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-else
                                :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'"
                            >
                                <td class="px-3 py-2" :class="{ 'text-muted-foreground line-through': !a.ate_fl_ativo }">
                                    {{ a.ate_descricao }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button
                                        type="button"
                                        class="rounded p-1 hover:bg-muted"
                                        :title="a.ate_fl_ativo ? 'Clique p/ inativar' : 'Clique p/ ativar'"
                                        @click="toggleAtivo(a)"
                                    >
                                        <CheckCircle2 v-if="a.ate_fl_ativo" class="mx-auto size-4 text-emerald-600" />
                                        <XCircle v-else class="mx-auto size-4 text-rose-500" />
                                    </button>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button type="button" variant="ghost" size="sm" @click="startEdit(a)" aria-label="Editar">
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                            @click="remove(a)"
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
