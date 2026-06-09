<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useToast } from '@/composables/useToast';
import { Loader2, Plus, RefreshCw, Trash2, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ turId: number }>();

interface AtividadeCatalogo { atv_id: number; atv_descricao: string }
interface AtividadeTurma   { tta_id: number; atv_id: number; atv_descricao: string }

const { push } = useToast();

const catalogo = ref<AtividadeCatalogo[]>([]);
const ofertadas = ref<AtividadeTurma[]>([]);
const loading = ref(true);

const showForm = ref(false);
const selecionado = ref<number | ''>('');
const salvando = ref(false);
const removendo = ref<number | null>(null);

const xsrf = () =>
    decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

const load = async () => {
    loading.value = true;
    try {
        const [cat, ofe] = await Promise.all([
            fetch('/api/atividades').then((r) => r.json()),
            fetch(`/api/turmas-atividade/${props.turId}/itens`).then((r) => r.json()),
        ]);
        catalogo.value = cat;
        ofertadas.value = ofe;
    } finally {
        loading.value = false;
    }
};

const idsOfertadas = computed(() => new Set(ofertadas.value.map((o) => o.atv_id)));
const disponiveis = computed(() =>
    catalogo.value.filter((c) => !idsOfertadas.value.has(c.atv_id)),
);

const openForm = () => { showForm.value = true; selecionado.value = ''; };
const closeForm = () => { showForm.value = false; selecionado.value = ''; };

const adicionar = async () => {
    if (!selecionado.value) return;
    salvando.value = true;
    try {
        const r = await fetch(`/api/turmas-atividade/${props.turId}/itens`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ atv_id: selecionado.value }),
        });

        if (!r.ok) {
            const json = await r.json().catch(() => ({}));
            const msg = json?.errors?.atv_id?.[0] ?? json?.message ?? 'Erro ao adicionar atividade.';
            push('error', msg);
            return;
        }

        push('success', 'Atividade adicionada.');
        closeForm();
        await load();
    } finally {
        salvando.value = false;
    }
};

const remover = async (item: AtividadeTurma) => {
    if (!confirm(`Remover "${item.atv_descricao}"?`)) return;
    removendo.value = item.tta_id;
    try {
        const r = await fetch(`/api/turmas-atividade/${props.turId}/itens/${item.tta_id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-XSRF-TOKEN': xsrf(), 'Accept': 'application/json' },
        });

        if (!r.ok) {
            push('error', 'Erro ao remover atividade.');
            return;
        }

        push('success', 'Atividade removida.');
        ofertadas.value = ofertadas.value.filter((o) => o.tta_id !== item.tta_id);
    } finally {
        removendo.value = null;
    }
};

onMounted(load);
</script>

<template>
    <div class="grid gap-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Atividades Oferecidas</h3>
            <div class="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" :disabled="loading" title="Atualizar" @click="load">
                    <RefreshCw :class="['mr-1 size-4', loading && 'animate-spin']" /> Atualizar
                </Button>
                <Button v-if="!showForm" type="button" size="sm" variant="outline" :disabled="disponiveis.length === 0" @click="openForm">
                    <Plus class="mr-1 size-4" /> Adicionar Atividade
                </Button>
            </div>
        </div>

        <div v-if="showForm" class="rounded-xl border border-teal-200 bg-teal-50/50 p-5 shadow-sm dark:border-teal-800 dark:bg-teal-900/20">
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-sm font-medium">Nova Atividade</h4>
                <button type="button" class="rounded p-1 hover:bg-muted" @click="closeForm">
                    <X class="size-4" />
                </button>
            </div>

            <select
                v-model.number="selecionado"
                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
            >
                <option value="">Selecione uma atividade...</option>
                <option v-for="a in disponiveis" :key="a.atv_id" :value="a.atv_id">{{ a.atv_descricao }}</option>
            </select>

            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="closeForm">Cancelar</Button>
                <Button type="button" size="sm" :disabled="salvando || !selecionado" class="bg-teal-600 text-white hover:bg-teal-700" @click="adicionar">
                    <Loader2 v-if="salvando" class="mr-1 size-4 animate-spin" />
                    Adicionar
                </Button>
            </div>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-12 text-muted-foreground">
            <Loader2 class="mr-2 size-5 animate-spin" /> Carregando...
        </div>

        <div v-else-if="ofertadas.length === 0" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
            Nenhuma atividade oferecida. Clique em "Adicionar Atividade" para começar.
        </div>

        <div v-else class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-teal-600 text-white">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">Atividade</th>
                        <th class="w-12 px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="(a, idx) in ofertadas" :key="a.tta_id" :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                        <td class="px-4 py-2.5 font-medium">{{ a.atv_descricao }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <button type="button" class="rounded p-1.5 hover:bg-muted" title="Remover" :disabled="removendo === a.tta_id" @click="remover(a)">
                                <Loader2 v-if="removendo === a.tta_id" class="size-4 animate-spin text-rose-500" />
                                <Trash2 v-else class="size-4 text-rose-500" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
