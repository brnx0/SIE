<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FormLabel from '@/components/common/FormLabel.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { FileText, Loader2, Search } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

interface Escola { esc_id: number; esc_nome: string }
interface Turma { tur_id: number; tur_nome: string; tur_situacao: string; tur_modalidade?: string }
interface AlunoResultado { tma_id: number; aln_id: number; aln_nome: string }

const props = defineProps<{
    anoLetivo: { anl_id: number; anl_ano: number } | null;
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Declaração de Matrícula', href: '/relatorios/declaracao-matricula' },
];

const escId = ref<number | ''>(props.userEscola?.esc_id ?? '');
const turId = ref<number | ''>('');
const alnId = ref<number | null>(null);
const turmas = ref<Turma[]>([]);
const alunos = ref<AlunoResultado[]>([]);
const loadingTurmas = ref(false);
const loadingAlunos = ref(false);
const gerando = ref(false);

const buscaAluno = ref('');

async function loadTurmas() {
    turmas.value = [];
    turId.value = '';
    alunos.value = [];
    alnId.value = null;
    if (!escId.value || !props.anoLetivo) return;
    loadingTurmas.value = true;
    try {
        const p = new URLSearchParams({ esc_id: String(escId.value), anl_id: String(props.anoLetivo.anl_id) });
        const r = await fetch(`/api/matriculas/turmas?${p}`);
        if (r.ok) {
            const lista: any[] = await r.json();
            turmas.value = lista
                .filter(t => t.tur_situacao === 'ABERTA')
                .filter(t => (t.tur_modalidade ?? 'REGULAR') === 'REGULAR');
        }
    } finally {
        loadingTurmas.value = false;
    }
}

async function loadAlunos() {
    alunos.value = [];
    alnId.value = null;
    if (!turId.value) return;
    loadingAlunos.value = true;
    try {
        const r = await fetch(`/api/turmas/${turId.value}/alunos`);
        if (r.ok) {
            const lista: any[] = await r.json();
            alunos.value = lista
                .filter((a: any) => a.tma_situacao === 'ATIVA')
                .map((a: any) => ({ tma_id: a.tma_id, aln_id: a.aln_id, aln_nome: a.aln_nome }));
        }
    } finally {
        loadingAlunos.value = false;
    }
}

onMounted(loadTurmas);
watch(escId, loadTurmas);
watch(turId, loadAlunos);

const alunosFiltrados = () => {
    if (!buscaAluno.value) return alunos.value;
    const q = buscaAluno.value.toLowerCase();
    return alunos.value.filter(a => a.aln_nome.toLowerCase().includes(q));
};

function gerar() {
    if (!escId.value) return;
    gerando.value = true;
    const payload: Record<string, any> = { esc_id: Number(escId.value) };
    if (turId.value) payload.tur_id = Number(turId.value);
    if (alnId.value) payload.aln_id = Number(alnId.value);

    router.get('/relatorios/declaracao-matricula/gerar', payload, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Declaração de Matrícula" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <FileText class="size-5 text-indigo-600" /> Declaração de Matrícula
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Emite declaração para alunos com matrícula regular ativa no ano letivo em exercício.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <FormLabel>Ano Letivo</FormLabel>
                    <div class="rounded-md border bg-muted/30 px-3 py-2 text-sm">
                        {{ anoLetivo?.anl_ano ?? '—' }} <span class="text-xs text-muted-foreground">(em exercício)</span>
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Escola</FormLabel>
                    <select v-model="escId" :disabled="!isAdmin && !!userEscola" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="e in escolas" :key="e.esc_id" :value="e.esc_id">{{ e.esc_nome }}</option>
                    </select>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Turma (opcional)</FormLabel>
                    <select v-model="turId" :disabled="!turmas.length" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Todas as turmas regulares</option>
                        <option v-for="t in turmas" :key="t.tur_id" :value="t.tur_id">{{ t.tur_nome }}</option>
                    </select>
                    <p v-if="loadingTurmas" class="text-xs text-muted-foreground">Carregando turmas...</p>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Aluno (opcional)</FormLabel>
                    <div v-if="turId" class="grid gap-1.5">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="buscaAluno" placeholder="Filtrar aluno..." class="pl-9" :disabled="!alunos.length" />
                        </div>
                        <select v-model="alnId" :disabled="!alunos.length" class="rounded-md border bg-background px-3 py-2 text-sm">
                            <option :value="null">Todos da turma</option>
                            <option v-for="a in alunosFiltrados()" :key="a.aln_id" :value="a.aln_id">{{ a.aln_nome }}</option>
                        </select>
                        <p v-if="loadingAlunos" class="text-xs text-muted-foreground">Carregando alunos...</p>
                    </div>
                    <p v-else class="text-xs text-muted-foreground">Selecione uma turma para filtrar aluno específico.</p>
                </div>

                <div class="sm:col-span-2 flex justify-end">
                    <Button :disabled="!escId || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Declaração
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
