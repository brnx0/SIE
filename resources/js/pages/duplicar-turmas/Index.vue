<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, Copy, CopyPlus, Loader2, Search, TriangleAlert } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number; anl_fl_em_exercicio: boolean }
interface Escola { esc_id: number; esc_nome: string }
interface TurmaLinha { tur_id: number; turma: string; serie: string | null; segmento: string | null; duplicada: boolean }

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Duplicar Turmas', href: '/duplicar-turmas' }];

const anoDefault = props.anosLetivos.find((a) => a.anl_fl_em_exercicio) ?? props.anosLetivos[0];
const anlId = ref<number | ''>(anoDefault?.anl_id ?? '');
const escId = ref<number | ''>(props.userEscola?.esc_id ?? '');

const carregando = ref(false);
const carregado = ref(false);
const erro = ref<string | null>(null);
const aviso = ref<string | null>(null);
const turmas = ref<TurmaLinha[]>([]);
const temProxAno = ref(true);
const proxAno = ref<number | null>(null);

const busca = ref('');
const turmasFiltradas = computed(() => {
    const q = busca.value.trim().toLowerCase();
    if (!q) return turmas.value;
    return turmas.value.filter((t) => `${t.serie ?? ''} ${t.turma} ${t.segmento ?? ''}`.toLowerCase().includes(q));
});
const disponiveis = computed(() => turmas.value.filter((t) => !t.duplicada).length);

const csrf = (): Record<string, string> => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
};
const postJson = (url: string, body: unknown) =>
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrf() }, credentials: 'same-origin', body: JSON.stringify(body) });

const buscarDados = async () => {
    const url = new URL('/duplicar-turmas/dados', window.location.origin);
    url.searchParams.set('anl_id', String(anlId.value));
    url.searchParams.set('esc_id', String(escId.value));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) {
        const j = await r.json();
        turmas.value = j.turmas ?? [];
        temProxAno.value = !!j.tem_prox_ano;
        proxAno.value = j.prox_ano ?? null;
        carregado.value = true;
    } else {
        erro.value = `Não foi possível listar as turmas (erro ${r.status}).`;
    }
};

const listar = async () => {
    if (!anlId.value || !escId.value) return;
    carregando.value = true;
    erro.value = null;
    aviso.value = null;
    try {
        await buscarDados();
    } catch {
        erro.value = 'Falha de conexão ao listar as turmas.';
    } finally {
        carregando.value = false;
    }
};

// ===== Confirmação =====
const confirmState = reactive<{ open: boolean; title: string; message: string; confirmLabel: string }>({ open: false, title: '', message: '', confirmLabel: 'Duplicar' });
let confirmResolve: ((v: boolean) => void) | null = null;
const confirmar = (title: string, message: string, confirmLabel = 'Duplicar'): Promise<boolean> => {
    confirmState.title = title;
    confirmState.message = message;
    confirmState.confirmLabel = confirmLabel;
    confirmState.open = true;
    return new Promise((resolve) => { confirmResolve = resolve; });
};
const onConfirmOk = () => { confirmState.open = false; confirmResolve?.(true); confirmResolve = null; };
const onConfirmCancel = () => { confirmResolve?.(false); confirmResolve = null; };

const processandoId = ref<number | null>(null);
const processandoTodas = ref(false);

const duplicarUma = async (t: TurmaLinha) => {
    if (t.duplicada || !temProxAno.value) return;
    const ok = await confirmar('Duplicar turma', `${t.serie ? t.serie + ' — ' : ''}Turma ${t.turma}\n\nSerá criada uma cópia em ${proxAno.value}.`, 'Duplicar');
    if (!ok) return;
    processandoId.value = t.tur_id;
    erro.value = null;
    try {
        const r = await postJson('/duplicar-turmas/duplicar', { tur_id: t.tur_id });
        if (r.ok) { t.duplicada = true; aviso.value = `Turma ${t.turma} duplicada para ${proxAno.value}.`; }
        else { const j = await r.json().catch(() => ({})); erro.value = j.message ?? 'Não foi possível duplicar.'; }
    } catch {
        erro.value = 'Falha de conexão ao duplicar.';
    } finally {
        processandoId.value = null;
    }
};

const duplicarTodas = async () => {
    if (!disponiveis.value || !temProxAno.value) return;
    const ok = await confirmar('Duplicar todas as turmas', `${disponiveis.value} turma(s) disponível(eis) serão duplicadas para ${proxAno.value}.`, 'Duplicar todas');
    if (!ok) return;
    processandoTodas.value = true;
    erro.value = null;
    aviso.value = null;
    try {
        const r = await postJson('/duplicar-turmas/duplicar-todas', { anl_id: anlId.value, esc_id: escId.value });
        if (r.ok) { const j = await r.json(); aviso.value = `${j.criadas ?? 0} turma(s) duplicada(s) para ${proxAno.value}.`; await buscarDados(); }
        else { const j = await r.json().catch(() => ({})); erro.value = j.message ?? 'Não foi possível duplicar.'; }
    } catch {
        erro.value = 'Falha de conexão ao duplicar.';
    } finally {
        processandoTodas.value = false;
    }
};
</script>

<template>
    <Head title="Duplicar Turmas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <CopyPlus class="size-5 text-indigo-600" /> Duplicar Turmas
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Crie as turmas do ano seguinte a partir das turmas <strong>encerradas</strong> — sem precisar recriá-las manualmente. Cada turma só pode ser duplicada uma vez.
            </p>

            <!-- Filtros -->
            <div class="mb-4 grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo (origem)</FormLabel>
                    <select v-model="anlId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">
                            {{ a.anl_ano }}<span v-if="a.anl_fl_em_exercicio"> (em exercício)</span>
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <select v-model="escId" :disabled="!isAdmin && !!userEscola" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Selecione...</option>
                        <option v-for="e in escolas" :key="e.esc_id" :value="e.esc_id">{{ e.esc_nome }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <Button :disabled="!anlId || !escId || carregando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="listar">
                        <Loader2 v-if="carregando" class="mr-1 size-4 animate-spin" />
                        Listar Turmas
                    </Button>
                </div>
            </div>

            <div v-if="erro" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">{{ erro }}</div>
            <div v-if="aviso" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ aviso }}</div>

            <template v-if="carregado">
                <div v-if="!temProxAno" class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    <span>O ano letivo seguinte ainda não existe. Cadastre-o em Parâmetros → Ano Letivo para poder duplicar.</span>
                </div>

                <div v-if="!turmas.length" class="rounded-xl border bg-card py-12 text-center text-muted-foreground">
                    Nenhuma turma encerrada encontrada para esta escola/ano. Só turmas fechadas podem ser duplicadas.
                </div>

                <template v-else>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                            <span class="font-medium text-muted-foreground">Legenda:</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-indigo-500"></span> Disponível</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full bg-emerald-500"></span> Já duplicada</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative w-full max-w-xs sm:w-56">
                                <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <input v-model="busca" type="text" placeholder="Buscar turma..." class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            </div>
                            <Button
                                :disabled="!disponiveis || !temProxAno || processandoTodas"
                                :title="!temProxAno ? 'O ano seguinte não existe' : (!disponiveis ? 'Nenhuma turma disponível' : `Duplicar ${disponiveis} turma(s) para ${proxAno}`)"
                                class="gap-1.5 bg-indigo-600 text-white hover:bg-indigo-700"
                                @click="duplicarTodas"
                            >
                                <Loader2 v-if="processandoTodas" class="size-4 animate-spin" />
                                <CopyPlus v-else class="size-4" />
                                Duplicar todas{{ disponiveis ? ` (${disponiveis})` : '' }}
                            </Button>
                        </div>
                    </div>

                    <div v-if="!turmasFiltradas.length" class="rounded-xl border bg-card py-10 text-center text-sm text-muted-foreground">Nenhuma turma encontrada para "{{ busca }}".</div>

                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="t in turmasFiltradas"
                            :key="t.tur_id"
                            :class="[
                                'flex items-center justify-between gap-3 rounded-xl border p-4 shadow-sm transition',
                                t.duplicada
                                    ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30'
                                    : 'border-slate-200 bg-card dark:border-slate-800',
                            ]"
                        >
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ t.serie ? `${t.serie} — ` : '' }}Turma {{ t.turma }}</div>
                                <div class="mt-0.5 flex items-center gap-2 text-xs">
                                    <span v-if="t.segmento" class="text-muted-foreground">{{ t.segmento }}</span>
                                    <span aria-hidden="true" v-if="t.segmento">·</span>
                                    <span v-if="t.duplicada" class="inline-flex items-center gap-1 font-medium text-emerald-700 dark:text-emerald-300"><CheckCircle2 class="size-3.5" /> Já duplicada</span>
                                    <span v-else class="inline-flex items-center gap-1 font-medium text-indigo-600 dark:text-indigo-300"><Copy class="size-3.5" /> Disponível</span>
                                </div>
                            </div>
                            <Button
                                v-if="!t.duplicada"
                                size="sm"
                                variant="outline"
                                :disabled="!temProxAno || processandoId === t.tur_id"
                                class="shrink-0 gap-1"
                                @click="duplicarUma(t)"
                            >
                                <Loader2 v-if="processandoId === t.tur_id" class="size-3.5 animate-spin" />
                                <Copy v-else class="size-3.5" />
                                Duplicar
                            </Button>
                            <span v-else class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-500 text-white">
                                <CheckCircle2 class="size-5" />
                            </span>
                        </div>
                    </div>
                </template>
            </template>

            <div v-else class="rounded-xl border bg-card px-6 py-10 text-center text-sm text-muted-foreground shadow-sm">
                Selecione o ano letivo de origem e a escola para listar as turmas encerradas.
            </div>
        </div>

        <ConfirmDialog
            :open="confirmState.open"
            :title="confirmState.title"
            :message="confirmState.message"
            variant="success"
            :confirm-label="confirmState.confirmLabel"
            @update:open="confirmState.open = $event"
            @confirm="onConfirmOk"
            @cancel="onConfirmCancel"
        />
    </AppLayout>
</template>
