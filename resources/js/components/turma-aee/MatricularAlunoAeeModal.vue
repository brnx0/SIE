<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import { AlertTriangle, CheckCircle2, GraduationCap, Loader2, School, Search, UserPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const { push: pushToast } = useToast();

interface Saude {
    als_fl_pcd: boolean;
    als_fl_altas_habilidades: boolean;
    als_deficiencias: string[];
    als_transtornos_globais: string[];
    als_transtornos_aprendizagem: string[];
    als_cid: string | null;
}

interface MatRegular {
    tma_id: number;
    esc_id: number | null;
    esc_nome: string | null;
    ser_nome: string | null;
    tur_nome: string | null;
    modalidade: string | null;
}

interface Elegivel {
    aln_id: number;
    aln_nome: string;
    aln_nr_matricula: number | null;
    aln_foto_url: string | null;
    matricula_regular: MatRegular | null;
    saude: Saude | null;
}

const props = defineProps<{
    open: boolean;
    turId: number;
    turEscId: number;
    turEscNome: string;
    anlAno: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', v: boolean): void;
    (e: 'matriculado'): void;
}>();

const search = ref('');
const lista = ref<Elegivel[]>([]);
const loading = ref(false);
const processing = ref(false);
const selecionado = ref<Elegivel | null>(null);
const erro = ref<string | null>(null);

let timer: ReturnType<typeof setTimeout> | null = null;

const fetchElegiveis = async () => {
    loading.value = true;
    try {
        const p = new URLSearchParams();
        if (search.value) p.set('search', search.value);
        const r = await fetch(`/api/turmas-aee/${props.turId}/alunos/elegiveis?${p}`);
        lista.value = r.ok ? await r.json() : [];
    } finally {
        loading.value = false;
    }
};

watch(() => props.open, (v) => {
    if (v) {
        selecionado.value = null;
        search.value = '';
        erro.value = null;
        fetchElegiveis();
    }
});

watch(search, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(fetchElegiveis, 300);
});

const cross = computed(() =>
    selecionado.value?.matricula_regular?.esc_id != null
    && selecionado.value.matricula_regular.esc_id !== props.turEscId,
);

const initials = (nome: string) =>
    nome.trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').toUpperCase();

const fechar = () => emit('update:open', false);

const confirmar = async () => {
    if (!selecionado.value) return;
    processing.value = true;
    erro.value = null;
    try {
        const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
        const r = await fetch(`/api/turmas-aee/${props.turId}/alunos`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ aln_id: selecionado.value.aln_id }),
        });
        if (!r.ok) {
            const body = await r.json().catch(() => ({}));
            erro.value = body?.errors?.aln_id?.[0] ?? body?.message ?? 'Erro ao matricular.';
            pushToast('error', erro.value ?? 'Erro ao matricular.');
            return;
        }
        pushToast('success', `${selecionado.value.aln_nome} matriculado(a) no AEE com sucesso.`);
        emit('matriculado');
        fechar();
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
        @click.self="fechar"
    >
        <div class="grid w-full max-w-4xl gap-4 rounded-xl border bg-card p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Matricular Aluno no AEE</h2>
                    <p class="text-xs text-muted-foreground">
                        Apenas alunos com PCD/TGD/AH marcado e matriculados em {{ anlAno }}.
                    </p>
                </div>
                <button class="rounded p-1 hover:bg-muted" @click="fechar"><X class="size-5" /></button>
            </div>

            <!-- Busca -->
            <div class="relative">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Filtrar por nome..." class="pl-9" />
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Lista -->
                <div class="rounded-lg border bg-background">
                    <div class="border-b px-3 py-2 text-xs font-semibold text-muted-foreground">
                        Alunos elegíveis ({{ lista.length }})
                    </div>
                    <div v-if="loading" class="grid place-items-center py-12 text-muted-foreground">
                        <Loader2 class="size-5 animate-spin" />
                    </div>
                    <div v-else-if="!lista.length" class="px-3 py-10 text-center text-sm text-muted-foreground">
                        Nenhum aluno elegível.
                    </div>
                    <ul v-else class="max-h-96 divide-y overflow-y-auto">
                        <li
                            v-for="a in lista"
                            :key="a.aln_id"
                            class="flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-muted"
                            :class="{ 'bg-indigo-50/60 dark:bg-indigo-900/20': selecionado?.aln_id === a.aln_id }"
                            @click="selecionado = a"
                        >
                            <div class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <img v-if="a.aln_foto_url" :src="a.aln_foto_url" class="size-full object-cover" />
                                <span v-else>{{ initials(a.aln_nome) }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ a.aln_nome }}</p>
                                <p class="truncate text-xs text-muted-foreground">
                                    Mat. {{ a.aln_nr_matricula ?? '—' }}
                                    <span v-if="a.matricula_regular?.esc_nome"> • {{ a.matricula_regular.esc_nome }}</span>
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Detalhe -->
                <div class="rounded-lg border bg-background p-4">
                    <p v-if="!selecionado" class="py-10 text-center text-sm text-muted-foreground">
                        Selecione um aluno para ver detalhes.
                    </p>
                    <div v-else class="grid gap-4">
                        <div class="flex items-center gap-3">
                            <div class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-full bg-indigo-100 font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <img v-if="selecionado.aln_foto_url" :src="selecionado.aln_foto_url" class="size-full object-cover" />
                                <span v-else>{{ initials(selecionado.aln_nome) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ selecionado.aln_nome }}</p>
                                <p class="text-xs text-muted-foreground">Matrícula nº {{ selecionado.aln_nr_matricula ?? '—' }}</p>
                            </div>
                        </div>

                        <!-- Critérios -->
                        <div class="grid gap-1.5 text-sm">
                            <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-300">
                                <CheckCircle2 class="size-4" /> PCD / TGD / Altas Habilidades
                            </div>
                            <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-300">
                                <CheckCircle2 class="size-4" /> Matriculado em {{ anlAno }}
                            </div>
                        </div>

                        <!-- Matricula regular -->
                        <div v-if="selecionado.matricula_regular" class="rounded-md border bg-muted/40 p-3 text-sm">
                            <div class="mb-1 flex items-center gap-2 text-xs font-semibold text-muted-foreground">
                                <GraduationCap class="size-4" /> Vínculo Regular
                            </div>
                            <p><span class="text-muted-foreground">Escola:</span> {{ selecionado.matricula_regular.esc_nome ?? '—' }}</p>
                            <p><span class="text-muted-foreground">Série:</span> {{ selecionado.matricula_regular.ser_nome ?? '—' }} • Turma {{ selecionado.matricula_regular.tur_nome ?? '—' }}</p>
                        </div>

                        <!-- Cross-escola -->
                        <div
                            v-if="cross"
                            class="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50/70 p-3 text-sm text-amber-900 dark:bg-amber-900/20 dark:text-amber-200"
                        >
                            <School class="mt-0.5 size-4 shrink-0" />
                            <div>
                                <p class="font-medium">Atendimento em outra escola</p>
                                <p class="text-xs">
                                    Regular em <b>{{ selecionado.matricula_regular?.esc_nome }}</b>, AEE em <b>{{ turEscNome }}</b>.
                                </p>
                            </div>
                        </div>

                        <!-- Saúde -->
                        <div v-if="selecionado.saude" class="rounded-md border bg-muted/40 p-3 text-xs">
                            <div class="mb-1 font-semibold text-muted-foreground">Quadro de Saúde</div>
                            <p v-if="selecionado.saude.als_deficiencias.length">
                                <span class="text-muted-foreground">Deficiências:</span> {{ selecionado.saude.als_deficiencias.join(', ') }}
                            </p>
                            <p v-if="selecionado.saude.als_transtornos_globais.length">
                                <span class="text-muted-foreground">TGD:</span> {{ selecionado.saude.als_transtornos_globais.join(', ') }}
                            </p>
                            <p v-if="selecionado.saude.als_transtornos_aprendizagem.length">
                                <span class="text-muted-foreground">Transt. Aprendizagem:</span> {{ selecionado.saude.als_transtornos_aprendizagem.join(', ') }}
                            </p>
                            <p v-if="selecionado.saude.als_fl_altas_habilidades">
                                <span class="text-muted-foreground">Altas Habilidades:</span> Sim
                            </p>
                            <p v-if="selecionado.saude.als_cid">
                                <span class="text-muted-foreground">CID:</span> {{ selecionado.saude.als_cid }}
                            </p>
                        </div>

                        <div v-if="erro" class="flex items-start gap-2 rounded-md border border-rose-300 bg-rose-50 p-2 text-xs text-rose-700">
                            <AlertTriangle class="mt-0.5 size-4 shrink-0" /> {{ erro }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t pt-4">
                <Button variant="outline" :disabled="processing" @click="fechar">Cancelar</Button>
                <Button
                    class="bg-indigo-600 text-white hover:bg-indigo-700"
                    :disabled="!selecionado || processing"
                    @click="confirmar"
                >
                    <Loader2 v-if="processing" class="mr-1 size-4 animate-spin" />
                    <UserPlus v-else class="mr-1 size-4" />
                    Confirmar Matrícula
                </Button>
            </div>
        </div>
    </div>
</template>
