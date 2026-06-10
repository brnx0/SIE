<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import MatricularAlunoAeeModal from '@/components/turma-aee/MatricularAlunoAeeModal.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Loader2, Search, UserPlus, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface AnoLetivo { anl_id: number; anl_ano: number }
interface Escola { esc_id: number; esc_nome: string }
interface TurmaAee {
    tur_id: number;
    tur_nome: string;
    tur_turno: string | null;
    tur_capacidade: number | null;
    tur_situacao: string;
    total_matriculados: number;
    vagas_disponiveis: number | null;
    escola: { esc_id: number; esc_nome: string } | null;
}

const props = defineProps<{
    anosLetivos: AnoLetivo[];
    escolas: Escola[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Matrículas AEE', href: '/matriculas-aee' }];

const fAnlId = ref<number | ''>(props.anosLetivos[0]?.anl_id ?? '');
const fEscId = ref<number | ''>('');
const search = ref('');

const turmas  = ref<TurmaAee[]>([]);
const loading = ref(false);

const modalOpen = ref(false);
const turmaSelecionada = ref<TurmaAee | null>(null);

const carregarTurmas = async () => {
    turmas.value = [];
    if (!fAnlId.value) return;
    loading.value = true;
    try {
        const p = new URLSearchParams();
        p.set('anl_id', String(fAnlId.value));
        if (fEscId.value) p.set('esc_id', String(fEscId.value));
        p.set('modalidade', 'AEE');
        const r = await fetch(`/api/matriculas/turmas?${p}`);
        if (r.ok) turmas.value = await r.json();
    } finally {
        loading.value = false;
    }
};

watch([fAnlId, fEscId], carregarTurmas, { immediate: true });

const turmasFiltradas = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q
        ? turmas.value.filter(t =>
            t.tur_nome.toLowerCase().includes(q)
            || t.escola?.esc_nome?.toLowerCase().includes(q),
        )
        : turmas.value;
});

const anlAno = computed(() => props.anosLetivos.find(a => a.anl_id === Number(fAnlId.value))?.anl_ano ?? 0);

const abrirModal = (t: TurmaAee) => {
    turmaSelecionada.value = t;
    modalOpen.value = true;
};

const aposMatricular = async () => {
    await carregarTurmas();
};
</script>

<template>
    <Head title="Matrículas AEE" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold flex items-center gap-2">
                        <Users class="size-5 text-indigo-600" /> Matrículas AEE
                    </h1>
                    <p class="text-xs text-muted-foreground">
                        Selecione a turma AEE e matricule alunos com PCD/TGD/Altas Habilidades.
                    </p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="mb-4 grid gap-4 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-3">
                <div class="grid gap-1">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <select v-model="fAnlId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">{{ a.anl_ano }}</option>
                    </select>
                </div>
                <div class="grid gap-1">
                    <FormLabel>Escola</FormLabel>
                    <select v-model="fEscId" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <option v-for="e in escolas" :key="e.esc_id" :value="e.esc_id">{{ e.esc_nome }}</option>
                    </select>
                </div>
                <div class="grid gap-1">
                    <FormLabel>Filtrar turma</FormLabel>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Nome da turma ou escola..."
                            class="h-10 w-full rounded-md border bg-background pl-9 pr-3 text-sm"
                        />
                    </div>
                </div>
            </div>

            <!-- Lista turmas AEE -->
            <div v-if="loading" class="grid place-items-center rounded-xl border bg-card py-16 text-muted-foreground shadow-sm">
                <Loader2 class="size-6 animate-spin" />
            </div>
            <div v-else-if="!turmasFiltradas.length" class="rounded-xl border-2 border-dashed bg-card py-16 text-center text-sm text-muted-foreground shadow-sm">
                Nenhuma turma AEE encontrada para os filtros selecionados.
            </div>
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="t in turmasFiltradas"
                    :key="t.tur_id"
                    class="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="flex items-start justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-xs text-muted-foreground">{{ t.escola?.esc_nome ?? '—' }}</p>
                            <h3 class="truncate text-base font-semibold">Turma {{ t.tur_nome }}</h3>
                        </div>
                        <span class="inline-flex rounded-full bg-fuchsia-100 px-2 py-0.5 text-xs font-semibold text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-300">
                            AEE
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div class="rounded-md bg-muted/50 p-2 text-center">
                            <div class="text-muted-foreground">Turno</div>
                            <div class="font-medium capitalize">{{ t.tur_turno?.toLowerCase() ?? '—' }}</div>
                        </div>
                        <div class="rounded-md bg-muted/50 p-2 text-center">
                            <div class="text-muted-foreground">Matriculados</div>
                            <div class="font-medium">{{ t.total_matriculados }}</div>
                        </div>
                        <div class="rounded-md bg-muted/50 p-2 text-center">
                            <div class="text-muted-foreground">Vagas</div>
                            <div class="font-medium">{{ t.vagas_disponiveis ?? '—' }}</div>
                        </div>
                    </div>
                    <Button
                        size="sm"
                        class="bg-indigo-600 text-white hover:bg-indigo-700"
                        :disabled="t.tur_situacao !== 'ABERTA'"
                        @click="abrirModal(t)"
                    >
                        <UserPlus class="mr-1 size-4" /> Matricular Aluno
                    </Button>
                </div>
            </div>
        </div>

        <MatricularAlunoAeeModal
            v-if="turmaSelecionada"
            v-model:open="modalOpen"
            :tur-id="turmaSelecionada.tur_id"
            :tur-esc-id="turmaSelecionada.escola?.esc_id ?? 0"
            :tur-esc-nome="turmaSelecionada.escola?.esc_nome ?? ''"
            :anl-ano="anlAno"
            @matriculado="aposMatricular"
        />
    </AppLayout>
</template>
