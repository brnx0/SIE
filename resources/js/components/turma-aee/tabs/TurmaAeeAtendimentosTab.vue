<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useToast } from '@/composables/useToast';
import { Loader2, Plus, RefreshCw, Trash2, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ turId: number }>();

interface AtendimentoCatalogo { ate_id: number; ate_descricao: string }
interface AtendimentoTurma   { tat_id: number; ate_id: number; ate_descricao: string }

const { push } = useToast();

const catalogo = ref<AtendimentoCatalogo[]>([]);
const ofertados = ref<AtendimentoTurma[]>([]);
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
            fetch('/api/atendimentos-aee').then((r) => r.json()),
            fetch(`/api/turmas-aee/${props.turId}/atendimentos`).then((r) => r.json()),
        ]);
        catalogo.value = cat;
        ofertados.value = ofe;
    } finally {
        loading.value = false;
    }
};

const idsOfertados = computed(() => new Set(ofertados.value.map((o) => o.ate_id)));
const disponiveis = computed(() =>
    catalogo.value.filter((c) => !idsOfertados.value.has(c.ate_id)),
);

const openForm = () => {
    showForm.value = true;
    selecionado.value = '';
};

const closeForm = () => {
    showForm.value = false;
    selecionado.value = '';
};

const adicionar = async () => {
    if (!selecionado.value) return;
    salvando.value = true;
    try {
        const r = await fetch(`/api/turmas-aee/${props.turId}/atendimentos`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ ate_id: selecionado.value }),
        });

        if (!r.ok) {
            const json = await r.json().catch(() => ({}));
            const msg = json?.errors?.ate_id?.[0] ?? json?.message ?? 'Erro ao adicionar atendimento.';
            push('error', msg);
            return;
        }

        push('success', 'Atendimento adicionado.');
        closeForm();
        await load();
    } finally {
        salvando.value = false;
    }
};

const remover = async (item: AtendimentoTurma) => {
    if (!confirm(`Remover "${item.ate_descricao}"?`)) return;
    removendo.value = item.tat_id;
    try {
        const r = await fetch(`/api/turmas-aee/${props.turId}/atendimentos/${item.tat_id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-XSRF-TOKEN': xsrf(), 'Accept': 'application/json' },
        });

        if (!r.ok) {
            push('error', 'Erro ao remover atendimento.');
            return;
        }

        push('success', 'Atendimento removido.');
        ofertados.value = ofertados.value.filter((o) => o.tat_id !== item.tat_id);
    } finally {
        removendo.value = null;
    }
};

onMounted(load);
</script>

<template>
    <div class="grid gap-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Atendimentos Oferecidos</h3>
            <div class="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" :disabled="loading" title="Atualizar" @click="load">
                    <RefreshCw :class="['mr-1 size-4', loading && 'animate-spin']" /> Atualizar
                </Button>
                <Button v-if="!showForm" type="button" size="sm" variant="outline" :disabled="disponiveis.length === 0" @click="openForm">
                    <Plus class="mr-1 size-4" /> Adicionar Atendimento
                </Button>
            </div>
        </div>

        <!-- Formulário -->
        <div v-if="showForm" class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-5 shadow-sm dark:border-indigo-800 dark:bg-indigo-900/20">
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-sm font-medium">Novo Atendimento</h4>
                <button type="button" class="rounded p-1 hover:bg-muted" @click="closeForm">
                    <X class="size-4" />
                </button>
            </div>

            <select
                v-model.number="selecionado"
                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
            >
                <option value="">Selecione um atendimento...</option>
                <option v-for="a in disponiveis" :key="a.ate_id" :value="a.ate_id">{{ a.ate_descricao }}</option>
            </select>

            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="closeForm">Cancelar</Button>
                <Button type="button" size="sm" :disabled="salvando || !selecionado" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="adicionar">
                    <Loader2 v-if="salvando" class="mr-1 size-4 animate-spin" />
                    Adicionar
                </Button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-12 text-muted-foreground">
            <Loader2 class="mr-2 size-5 animate-spin" /> Carregando...
        </div>

        <!-- Vazio -->
        <div v-else-if="ofertados.length === 0" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
            Nenhum atendimento oferecido. Clique em "Adicionar Atendimento" para começar.
        </div>

        <!-- Lista -->
        <div v-else class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-indigo-600 text-white">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">Atendimento</th>
                        <th class="w-12 px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="(a, idx) in ofertados" :key="a.tat_id" :class="idx % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                        <td class="px-4 py-2.5 font-medium">{{ a.ate_descricao }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <button type="button" class="rounded p-1.5 hover:bg-muted" title="Remover" :disabled="removendo === a.tat_id" @click="remover(a)">
                                <Loader2 v-if="removendo === a.tat_id" class="size-4 animate-spin text-rose-500" />
                                <Trash2 v-else class="size-4 text-rose-500" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
