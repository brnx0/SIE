<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { apiFetch } from '@/lib/apiFetch';
import type { PassageiroRota, PontoRota, Rota } from '@/types/transporte';
import { router } from '@inertiajs/vue3';
import { AlertTriangle, GraduationCap, Loader2, Plus, RefreshCw, Search, Trash2, UserPlus, Users, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    rota: Rota;
    pontos: PontoRota[];
    passageiros: PassageiroRota[];
    capacidade: number | null;
}>();

const list = computed<PassageiroRota[]>(() => props.passageiros ?? []);
const pontosList = computed<PontoRota[]>(() =>
    [...(props.pontos ?? [])].sort((a, b) => a.pto_ordem - b.pto_ordem),
);

const total = computed(() => list.value.length);
const excede = computed(
    () => props.capacidade != null && props.capacidade > 0 && total.value > props.capacidade,
);

// ── Formulário de vínculo ──────────────────────────────────────────────
const showForm = ref(false);
const origem = ref<'aluno' | 'externo'>('aluno');
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const avisoOutraRota = ref<string | null>(null);

const form = reactive<{
    pas_aln_id: number | null;
    pas_tue_id: number | null;
    pas_pto_id: number | null;
    pas_dt_inicio: string;
    pas_dt_fim: string;
    pas_obs: string;
}>({
    pas_aln_id: null,
    pas_tue_id: null,
    pas_pto_id: null,
    pas_dt_inicio: '',
    pas_dt_fim: '',
    pas_obs: '',
});

const selecionadoLabel = ref('');

const resetForm = () => {
    form.pas_aln_id = null;
    form.pas_tue_id = null;
    form.pas_pto_id = null;
    form.pas_dt_inicio = '';
    form.pas_dt_fim = '';
    form.pas_obs = '';
    selecionadoLabel.value = '';
    origem.value = 'aluno';
    errors.value = {};
    avisoOutraRota.value = null;
    resultados.value = [];
    query.value = '';
    showForm.value = false;
};

const openForm = () => {
    resetForm();
    showForm.value = true;
};

// ── Busca (aluno OU usuário externo) ───────────────────────────────────
const query = ref('');
const loading = ref(false);
const resultados = ref<Array<{ id: number; nome: string; extra?: string }>>([]);
let debounce: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;

const trocarOrigem = (v: 'aluno' | 'externo') => {
    origem.value = v;
    form.pas_aln_id = null;
    form.pas_tue_id = null;
    selecionadoLabel.value = '';
    query.value = '';
    resultados.value = [];
    avisoOutraRota.value = null;
};

const buscar = () => {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(async () => {
        const q = query.value.trim();
        if (q.length < 2) { resultados.value = []; return; }
        if (controller) controller.abort();
        controller = new AbortController();
        loading.value = true;
        try {
            const url = origem.value === 'aluno'
                ? `/api/alunos/search?q=${encodeURIComponent(q)}&anl_id=${props.rota.rot_anl_id}`
                : `/api/transporte/usuarios/search?q=${encodeURIComponent(q)}`;
            const res = await apiFetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
            if (!res.ok) { resultados.value = []; return; }
            const data = await res.json();
            resultados.value = origem.value === 'aluno'
                ? data.map((a: any) => ({ id: a.aln_id, nome: a.aln_nome, extra: a.aln_nr_matricula ? `Matrícula ${a.aln_nr_matricula}` : undefined }))
                : data.map((u: any) => ({ id: u.tue_id, nome: u.tue_nome }));
        } catch (e: unknown) {
            if ((e as Error).name !== 'AbortError') resultados.value = [];
        } finally {
            loading.value = false;
        }
    }, 300);
};

const escolher = (r: { id: number; nome: string }) => {
    if (origem.value === 'aluno') form.pas_aln_id = r.id;
    else form.pas_tue_id = r.id;
    selecionadoLabel.value = r.nome;
    resultados.value = [];
    query.value = '';
};

const limparSelecao = () => {
    form.pas_aln_id = null;
    form.pas_tue_id = null;
    selecionadoLabel.value = '';
};

const submit = (confirmarOutraRota = false) => {
    processing.value = true;
    errors.value = {};

    const payload: Record<string, any> = {
        pas_aln_id: origem.value === 'aluno' ? form.pas_aln_id : null,
        pas_tue_id: origem.value === 'externo' ? form.pas_tue_id : null,
        pas_pto_id: form.pas_pto_id,
        pas_dt_inicio: form.pas_dt_inicio || null,
        pas_dt_fim: form.pas_dt_fim || null,
        pas_obs: form.pas_obs || null,
    };
    if (confirmarOutraRota) payload.confirm_outra_rota = true;

    router.post(`/transporte/rotas/${props.rota.rot_id}/passageiros`, payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => resetForm(),
        onError: (e: Record<string, string>) => {
            if (e.passageiro_outra_rota) {
                avisoOutraRota.value = e.passageiro_outra_rota;
                errors.value = {};
            } else {
                errors.value = e;
                avisoOutraRota.value = null;
            }
        },
        onFinish: () => { processing.value = false; },
    });
};

const remove = (p: PassageiroRota) => {
    const nome = p.aluno?.aln_nome ?? p.usuario_externo?.tue_nome ?? 'passageiro';
    if (!confirm(`Remover "${nome}" desta rota?`)) return;
    router.delete(`/transporte/rotas/${props.rota.rot_id}/passageiros/${p.pas_id}`, {
        preserveScroll: true,
        preserveState: true,
    });
};

const refreshing = ref(false);
const refresh = () => {
    refreshing.value = true;
    router.reload({
        only: ['pontos', 'passageiros', 'flash'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { refreshing.value = false; },
    });
};

const podeSalvar = computed(() =>
    !!form.pas_pto_id && (origem.value === 'aluno' ? !!form.pas_aln_id : !!form.pas_tue_id),
);
</script>

<template>
    <div class="grid gap-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h3 class="text-sm font-semibold">Passageiros</h3>
                <span :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold', excede ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300']">
                    <Users class="size-3" />
                    {{ total }}<template v-if="capacidade != null"> / {{ capacidade }}</template>
                </span>
                <span v-if="excede" class="text-xs text-rose-600">Capacidade do veículo excedida.</span>
            </div>
            <div class="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" :disabled="refreshing" title="Atualizar" @click="refresh">
                    <RefreshCw :class="['mr-1 size-4', refreshing && 'animate-spin']" /> Atualizar
                </Button>
                <Button v-if="!showForm && pontosList.length > 0" type="button" size="sm" variant="outline" @click="openForm">
                    <Plus class="mr-1 size-4" /> Vincular passageiro
                </Button>
            </div>
        </div>

        <!-- Sem pontos: bloqueia -->
        <div v-if="pontosList.length === 0" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
            Cadastre ao menos um ponto de parada na aba <strong>Pontos de Parada</strong> antes de vincular passageiros.
        </div>

        <!-- Form -->
        <div v-if="showForm" class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-5 shadow-sm dark:border-indigo-800 dark:bg-indigo-900/20">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-sm font-medium">Novo passageiro</h4>
                <button type="button" class="rounded p-1 hover:bg-muted" @click="resetForm"><X class="size-4" /></button>
            </div>

            <!-- Origem -->
            <div class="mb-4 inline-flex rounded-lg border bg-background p-1 text-sm">
                <button type="button" :class="['flex items-center gap-1.5 rounded-md px-3 py-1.5', origem === 'aluno' ? 'bg-indigo-600 text-white' : 'text-muted-foreground']" @click="trocarOrigem('aluno')">
                    <GraduationCap class="size-4" /> Aluno da rede
                </button>
                <button type="button" :class="['flex items-center gap-1.5 rounded-md px-3 py-1.5', origem === 'externo' ? 'bg-indigo-600 text-white' : 'text-muted-foreground']" @click="trocarOrigem('externo')">
                    <UserPlus class="size-4" /> Usuário externo
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-12">
                <!-- Busca -->
                <div class="relative grid gap-1.5 sm:col-span-6">
                    <FormLabel :required="true">{{ origem === 'aluno' ? 'Aluno' : 'Usuário externo' }}</FormLabel>
                    <div v-if="selecionadoLabel" class="flex h-10 items-center justify-between rounded-md border border-input bg-background px-3 text-sm">
                        <span class="truncate">{{ selecionadoLabel }}</span>
                        <button type="button" class="rounded p-0.5 hover:bg-muted" @click="limparSelecao"><X class="size-4" /></button>
                    </div>
                    <template v-else>
                        <div class="flex items-center rounded-md border border-input bg-background px-3">
                            <Search class="size-4 text-muted-foreground" />
                            <input v-model="query" type="text" class="h-10 w-full bg-transparent px-2 text-sm focus:outline-none" :placeholder="origem === 'aluno' ? 'Nome, matrícula ou CPF...' : 'Nome do usuário externo...'" @input="buscar" />
                            <Loader2 v-if="loading" class="size-4 animate-spin text-muted-foreground" />
                        </div>
                        <ul v-if="resultados.length > 0" class="absolute top-full z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-popover py-1 text-sm shadow-lg">
                            <li v-for="r in resultados" :key="r.id" class="flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-muted" @mousedown.prevent="escolher(r)">
                                <span class="flex-1">{{ r.nome }}</span>
                                <span v-if="r.extra" class="text-xs text-muted-foreground">{{ r.extra }}</span>
                            </li>
                        </ul>
                    </template>
                </div>

                <!-- Ponto de embarque -->
                <div class="grid gap-1.5 sm:col-span-6">
                    <FormLabel :required="true">Ponto de embarque</FormLabel>
                    <select v-model.number="form.pas_pto_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground" :class="{ 'border-rose-500': errors.pas_pto_id }">
                        <option :value="null" disabled>Selecione o ponto...</option>
                        <option v-for="(p, i) in pontosList" :key="p.pto_id" :value="p.pto_id">{{ i + 1 }}. {{ p.pto_nome }}</option>
                    </select>
                    <InputError :message="errors.pas_pto_id" />
                </div>

                <div class="grid gap-1.5 sm:col-span-3">
                    <FormLabel>Início (opcional)</FormLabel>
                    <Input v-model="form.pas_dt_inicio" type="date" />
                    <InputError :message="errors.pas_dt_inicio" />
                </div>
                <div class="grid gap-1.5 sm:col-span-3">
                    <FormLabel>Término (opcional)</FormLabel>
                    <Input v-model="form.pas_dt_fim" type="date" />
                    <InputError :message="errors.pas_dt_fim" />
                </div>
                <div class="grid gap-1.5 sm:col-span-6">
                    <FormLabel>Observação</FormLabel>
                    <Input v-model="form.pas_obs" maxlength="200" placeholder="Opcional" />
                    <InputError :message="errors.pas_obs" />
                </div>
            </div>

            <InputError :message="errors.pas_aln_id" />
            <InputError :message="errors.pas_tue_id" />

            <!-- Aviso N5: aluno em outra rota do mesmo turno -->
            <div v-if="avisoOutraRota" class="mt-4 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                <div class="flex-1">
                    <p class="font-semibold">Aluno já vinculado a outra rota do mesmo turno</p>
                    <p class="mt-1 text-xs">Rota(s): {{ avisoOutraRota }}. Deseja vincular mesmo assim?</p>
                    <div class="mt-3 flex gap-2">
                        <Button type="button" size="sm" variant="outline" @click="avisoOutraRota = null">Cancelar</Button>
                        <Button type="button" size="sm" :disabled="processing" class="bg-amber-600 text-white hover:bg-amber-700" @click="submit(true)">
                            <Loader2 v-if="processing" class="mr-1 size-4 animate-spin" /> Vincular mesmo assim
                        </Button>
                    </div>
                </div>
            </div>

            <div v-if="!avisoOutraRota" class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="resetForm">Cancelar</Button>
                <Button type="button" size="sm" :disabled="processing || !podeSalvar" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="submit(false)">
                    <Loader2 v-if="processing" class="mr-1 size-4 animate-spin" /> Vincular
                </Button>
            </div>
        </div>

        <!-- Estado vazio -->
        <div v-if="list.length === 0 && !showForm && pontosList.length > 0" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
            Nenhum passageiro vinculado.
        </div>

        <!-- Lista -->
        <div v-if="list.length > 0" class="rounded-xl border bg-card shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/30 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3">Passageiro</th>
                        <th class="px-4 py-3">Escola / Turma / Instituição</th>
                        <th class="px-4 py-3">Ponto de embarque</th>
                        <th class="w-12 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in list" :key="p.pas_id" class="border-b last:border-0 hover:bg-muted/20">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ p.aluno?.aln_nome ?? p.usuario_externo?.tue_nome ?? '—' }}</div>
                            <span :class="['mt-0.5 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase', p.aluno ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/40 dark:text-fuchsia-300']">
                                {{ p.aluno ? 'Aluno da rede' : 'Externo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            <template v-if="p.aluno">
                                <div v-if="p.aluno.escola">{{ p.aluno.escola }}</div>
                                <div v-if="p.aluno.turma">Turma {{ p.aluno.turma }}</div>
                                <div v-if="!p.aluno.escola && !p.aluno.turma" class="text-amber-600">Sem matrícula ativa neste ano</div>
                            </template>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ p.ponto?.pto_nome ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <button type="button" class="rounded p-1.5 hover:bg-muted" title="Remover" @click="remove(p)"><Trash2 class="size-4 text-rose-500" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
