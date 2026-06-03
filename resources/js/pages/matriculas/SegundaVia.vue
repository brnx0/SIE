<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import type { AnoLetivo } from '@/types/parametro';
import type { AlunoResumo } from '@/types/matricula';
import { Head } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, FileText, Loader2, Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    anosLetivos: Pick<AnoLetivo, 'anl_id' | 'anl_ano'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Matrículas', href: '/matriculas' },
    { title: '2ª Via Comprovante', href: '/matriculas/segunda-via' },
];

const selectClass = 'flex h-10 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-indigo-500';

// ── Filtros ───────────────────────────────────────────────────────────────────
const fAnlId = ref<number | ''>(props.anosLetivos[0]?.anl_id ?? '');

// ── Busca de aluno ────────────────────────────────────────────────────────────
const alunoQuery      = ref('');
const alunoResultados = ref<AlunoResumo[]>([]);
const alunoSelecionado = ref<AlunoResumo | null>(null);
const loadingAlunos   = ref(false);
const showAlunoDrop   = ref(false);

let alunoTimer: ReturnType<typeof setTimeout> | null = null;

watch(alunoQuery, (v) => {
    if (alunoTimer) clearTimeout(alunoTimer);
    alunoResultados.value = [];
    showAlunoDrop.value = false;
    if (!v || v.length < 2) return;
    alunoTimer = setTimeout(async () => {
        loadingAlunos.value = true;
        try {
            const r = await fetch(`/api/alunos/search?q=${encodeURIComponent(v)}`);
            alunoResultados.value = await r.json();
            showAlunoDrop.value = true;
        } finally {
            loadingAlunos.value = false;
        }
    }, 300);
});

const selecionarAluno = (a: AlunoResumo) => {
    alunoSelecionado.value = a;
    alunoQuery.value = '';
    showAlunoDrop.value = false;
    alunoResultados.value = [];
    resultado.value = null;
    erro.value = null;
};

const limparAluno = () => {
    alunoSelecionado.value = null;
    resultado.value = null;
    erro.value = null;
};

const ocultarDropDepoisBlur = () => {
    window.setTimeout(() => { showAlunoDrop.value = false; }, 200);
};

// ── Busca matrícula + emissão ─────────────────────────────────────────────────
const resultado   = ref<{ tma_id: number } | null>(null);
const erro        = ref<string | null>(null);
const buscando    = ref(false);

const buscar = async () => {
    if (!fAnlId.value || !alunoSelecionado.value) return;
    buscando.value = true;
    resultado.value = null;
    erro.value = null;
    try {
        const params = new URLSearchParams({
            aln_id: String(alunoSelecionado.value.aln_id),
            anl_id: String(fAnlId.value),
        });
        const r = await fetch(`/api/matriculas/buscar-comprovante?${params}`);
        const json = await r.json();
        if (!r.ok) {
            erro.value = json.message ?? 'Matrícula não encontrada.';
        } else {
            resultado.value = json;
        }
    } catch {
        erro.value = 'Erro ao buscar matrícula.';
    } finally {
        buscando.value = false;
    }
};

const emitir = () => {
    if (!resultado.value) return;
    window.open(`/matriculas/${resultado.value.tma_id}/comprovante`, '_blank');
};

const formatarNomeAluno = (a: AlunoResumo) => {
    const mat = a.aln_nr_matricula ? `Mat. ${a.aln_nr_matricula} — ` : '';
    const nasc = a.aln_dt_nascimento
        ? ` · Nasc. ${new Date(a.aln_dt_nascimento + 'T00:00:00').toLocaleDateString('pt-BR')}`
        : '';
    return `${mat}${a.aln_nome}${nasc}`;
};
</script>

<template>
    <Head title="2ª Via Comprovante" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] max-w-xl flex-col gap-6 p-4 md:p-6">

            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">2ª Via — Comprovante de Matrícula</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Selecione o ano letivo e o aluno para emitir o comprovante.</p>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm flex flex-col gap-5">

                <!-- Ano Letivo -->
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model.number="fAnlId" :class="selectClass" @change="resultado = null; erro = null">
                        <option value="">Selecione...</option>
                        <option v-for="anl in anosLetivos" :key="anl.anl_id" :value="anl.anl_id">
                            {{ anl.anl_ano }}
                        </option>
                    </select>
                </div>

                <!-- Busca de aluno -->
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Aluno</FormLabel>

                    <!-- Aluno selecionado -->
                    <div v-if="alunoSelecionado" class="flex items-center gap-2 rounded-md border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm dark:border-indigo-700 dark:bg-indigo-900/20">
                        <CheckCircle2 class="size-4 shrink-0 text-indigo-600" />
                        <span class="flex-1 font-medium text-indigo-800 dark:text-indigo-200">
                            {{ formatarNomeAluno(alunoSelecionado) }}
                        </span>
                        <button type="button" @click="limparAluno" class="text-indigo-400 hover:text-indigo-700">
                            <X class="size-4" />
                        </button>
                    </div>

                    <!-- Campo de busca -->
                    <div v-else class="relative">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="alunoQuery"
                            type="text"
                            placeholder="Buscar por nome, matrícula ou CPF..."
                            class="flex h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            @blur="ocultarDropDepoisBlur"
                            @focus="showAlunoDrop = alunoResultados.length > 0"
                        />
                        <Loader2 v-if="loadingAlunos" class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />

                        <div v-if="showAlunoDrop && alunoResultados.length" class="absolute z-20 mt-1 w-full rounded-md border bg-popover shadow-lg">
                            <button
                                v-for="a in alunoResultados"
                                :key="a.aln_id"
                                type="button"
                                class="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-muted focus:bg-muted focus:outline-none"
                                @mousedown.prevent="selecionarAluno(a)"
                            >
                                <span class="font-medium">{{ a.aln_nome }}</span>
                                <span class="text-xs text-muted-foreground">
                                    <template v-if="a.aln_nr_matricula">Mat. {{ a.aln_nr_matricula }} · </template>
                                    <template v-if="a.aln_dt_nascimento">
                                        Nasc. {{ new Date(a.aln_dt_nascimento + 'T00:00:00').toLocaleDateString('pt-BR') }}
                                    </template>
                                </span>
                            </button>
                        </div>
                        <p v-else-if="showAlunoDrop && !loadingAlunos" class="absolute z-20 mt-1 w-full rounded-md border bg-popover px-3 py-2 text-sm text-muted-foreground shadow-lg">
                            Nenhum aluno encontrado.
                        </p>
                    </div>
                </div>

                <!-- Botão buscar -->
                <Button
                    type="button"
                    :disabled="!fAnlId || !alunoSelecionado || buscando"
                    class="bg-indigo-600 hover:bg-indigo-700 w-full"
                    @click="buscar"
                >
                    <Loader2 v-if="buscando" class="mr-2 size-4 animate-spin" />
                    <Search v-else class="mr-2 size-4" />
                    {{ buscando ? 'Buscando...' : 'Buscar Matrícula' }}
                </Button>

                <!-- Erro -->
                <div v-if="erro" class="flex items-center gap-3 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-700 dark:bg-rose-900/20 dark:text-rose-300">
                    <AlertCircle class="size-4 shrink-0" />
                    {{ erro }}
                </div>

                <!-- Resultado: matrícula encontrada -->
                <div v-if="resultado" class="flex flex-col gap-3">
                    <div class="flex items-center gap-3 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/20">
                        <CheckCircle2 class="size-4 shrink-0" />
                        Matrícula encontrada. Clique em emitir para abrir o comprovante.
                    </div>
                    <Button
                        type="button"
                        class="bg-fuchsia-600 hover:bg-fuchsia-700 w-full"
                        @click="emitir"
                    >
                        <FileText class="mr-2 size-4" />
                        Emitir 2ª Via
                    </Button>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
