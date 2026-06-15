<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import PdfPreviewModal from '@/components/common/PdfPreviewModal.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { PlanoStatus } from '@/types/diario';
import { router, usePage } from '@inertiajs/vue3';
import { Printer, Save, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ planoId: number }>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'saved'): void }>();

const carregando = ref(true);
const erro = ref<string | null>(null);

const plano = ref<any>(null);
const indicadores = ref<{ ind_id: number; ind_descricao: string }[]>([]);

const statusOriginal = ref<PlanoStatus>('pendente');
const statusNovo = ref<PlanoStatus | ''>('');
const obs = ref<string>('');
const enviando = ref(false);
const erros = ref<Record<string, string>>({});

const carregar = async () => {
    carregando.value = true;
    erro.value = null;
    try {
        const r = await fetch(`/api/coordenador/planos/${props.planoId}`, { headers: { Accept: 'application/json' } });
        if (!r.ok) {
            erro.value = 'Não foi possível carregar o plano.';
            return;
        }
        const json = await r.json();
        plano.value = json.plano;
        indicadores.value = json.indicadores ?? [];
        statusOriginal.value = json.plano.dpa_status;
        statusNovo.value = json.plano.dpa_status;
        obs.value = json.plano.dpa_obs_coordenador ?? '';
    } finally {
        carregando.value = false;
    }
};

onMounted(carregar);

const page = usePage();
const meuFunId = computed<number | null>(() => (page.props as any).auth?.user?.fun_id ?? null);
const ehMeuPlano = computed(() => meuFunId.value !== null && plano.value && Number(plano.value.dpa_fun_id) === Number(meuFunId.value));

const podeRevisar = computed(() => statusOriginal.value === 'pendente' && !ehMeuPlano.value);
const statusMudou = computed(() => statusNovo.value !== statusOriginal.value);
const podeSalvar = computed(() => podeRevisar.value && statusMudou.value && obs.value.trim().length > 0 && !enviando.value);

const opcoesStatus: { value: PlanoStatus; label: string }[] = [
    { value: 'aprovado', label: 'Aprovar' },
    { value: 'correcao', label: 'Devolver para correção' },
    { value: 'reprovado', label: 'Reprovar' },
];

const statusBadge = (s: PlanoStatus) => ({
    pendente: 'bg-amber-100 text-amber-800 ring-1 ring-amber-300',
    aprovado: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300',
    reprovado: 'bg-rose-100 text-rose-800 ring-1 ring-rose-300',
    correcao: 'bg-sky-100 text-sky-800 ring-1 ring-sky-300',
}[s]);
const statusLabel = (s: PlanoStatus) => ({
    pendente: 'Pendente',
    aprovado: 'Aprovado',
    reprovado: 'Reprovado',
    correcao: 'Em correção',
}[s]);

const fmtDateTime = (d?: string | null) => {
    if (!d) return '—';
    const dt = new Date(d);
    if (isNaN(dt.getTime())) return '—';
    return dt.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
};
const fmtDate = (d: string) => {
    if (!d) return '—';
    const [y, m, day] = d.substring(0, 10).split('-');
    return `${day}/${m}/${y}`;
};

const salvar = () => {
    if (!podeSalvar.value) return;
    enviando.value = true;
    erros.value = {};
    router.put(`/coordenador/planos/${props.planoId}/status`, {
        dpa_status: statusNovo.value,
        dpa_obs_coordenador: obs.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errs) => { erros.value = errs as any; },
        onFinish: () => { enviando.value = false; },
        onSuccess: () => { emit('saved'); },
    });
};

const previewUrl = ref<string | null>(null);
const imprimir = () => {
    previewUrl.value = `/coordenador/planos/${props.planoId}/pdf`;
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
        <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-card shadow-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between border-b bg-muted/40 px-5 py-3">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold">Revisão de Plano de Aula</h2>
                    <span v-if="plano" :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', statusBadge(plano.dpa_status)]">
                        {{ statusLabel(plano.dpa_status) }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <Button v-if="plano" variant="outline" size="sm" @click="imprimir">
                        <Printer class="mr-2 size-4" /> Imprimir
                    </Button>
                    <Button variant="ghost" size="sm" @click="emit('close')">
                        <X class="size-5" />
                    </Button>
                </div>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-5">
                <div v-if="carregando" class="py-12 text-center text-muted-foreground">Carregando...</div>
                <div v-else-if="erro" class="py-12 text-center text-rose-600">{{ erro }}</div>
                <div v-else-if="plano" class="flex flex-col gap-5">
                    <!-- Identificação -->
                    <section class="rounded-lg border bg-background p-4">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Identificação</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3 text-sm">
                            <div>
                                <div class="text-xs text-muted-foreground">Professor</div>
                                <div class="font-medium">{{ plano.funcionario?.fun_nome ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Escola</div>
                                <div>{{ plano.escola?.esc_nome ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Turma</div>
                                <div>{{ plano.turma?.serie?.ser_nome ?? '' }} / {{ plano.turma?.tur_nome ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Componente</div>
                                <div>{{ plano.disciplina?.dis_nome ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Unidade</div>
                                <div>{{ plano.unidade?.uni_numero }}º {{ (plano.unidade?.uni_tipo || '').toLowerCase() }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Período</div>
                                <div class="tabular-nums">{{ fmtDate(plano.dpa_dt_inicio) }} – {{ fmtDate(plano.dpa_dt_fim) }}</div>
                            </div>
                            <div class="md:col-span-3">
                                <div class="text-xs text-muted-foreground">Tema</div>
                                <div class="font-semibold">{{ plano.dpa_tema }}</div>
                            </div>
                        </div>
                    </section>

                    <!-- Indicadores -->
                    <section v-if="indicadores.length" class="rounded-lg border bg-background p-4">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Objetivos de Aprendizagem e Desenvolvimento (Indicadores)
                        </h3>
                        <ul class="list-disc pl-5 text-sm">
                            <li v-for="ind in indicadores" :key="ind.ind_id">{{ ind.ind_descricao }}</li>
                        </ul>
                    </section>

                    <!-- Detalhamento -->
                    <section class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="rounded-lg border bg-background p-4">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Objeto do Conhecimento</h3>
                            <p class="whitespace-pre-wrap text-sm">{{ plano.dpa_objeto_conhecimento || '—' }}</p>
                        </div>
                        <div class="rounded-lg border bg-background p-4">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Estratégias / Metodologia</h3>
                            <p class="whitespace-pre-wrap text-sm">{{ plano.dpa_estrategias || '—' }}</p>
                        </div>
                        <div class="rounded-lg border bg-background p-4">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Recursos Didáticos</h3>
                            <p class="whitespace-pre-wrap text-sm">{{ plano.dpa_recursos || '—' }}</p>
                        </div>
                        <div class="rounded-lg border bg-background p-4">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Competências Gerais</h3>
                            <p class="whitespace-pre-wrap text-sm">{{ plano.dpa_competencias || '—' }}</p>
                        </div>
                        <div class="rounded-lg border bg-background p-4 md:col-span-2">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Avaliação</h3>
                            <p class="whitespace-pre-wrap text-sm">{{ plano.dpa_avaliacao || '—' }}</p>
                        </div>
                        <div class="rounded-lg border bg-background p-4 md:col-span-2">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Objetivos Complementares / Recomposição / Descritor</h3>
                            <p class="whitespace-pre-wrap text-sm">{{ plano.dpa_objetivos_complementares || '—' }}</p>
                        </div>
                    </section>

                    <!-- Bloco Revisão -->
                    <section class="rounded-lg border-2 border-indigo-200 bg-indigo-50/40 p-4 dark:border-indigo-900 dark:bg-indigo-950/30">
                        <h3 class="mb-3 text-sm font-semibold text-indigo-900 dark:text-indigo-200">Revisão</h3>

                        <div v-if="ehMeuPlano" class="rounded-md bg-rose-100 p-3 text-sm text-rose-900">
                            Você não pode validar um plano que você mesmo criou.
                        </div>
                        <div v-else-if="!podeRevisar" class="rounded-md bg-amber-100 p-3 text-sm text-amber-900">
                            Este plano não está mais pendente. Apenas planos com status "Pendente" podem ser revisados.
                            <div v-if="plano?.validado_por || plano?.dpa_validado_em" class="mt-2 text-xs">
                                Validado por <span class="font-semibold">{{ plano?.validado_por?.name ?? '—' }}</span>
                                em <span class="font-semibold">{{ fmtDateTime(plano?.dpa_validado_em) }}</span>
                            </div>
                        </div>
                        <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <Label>Decisão <span class="text-rose-600">*</span></Label>
                                <select v-model="statusNovo" class="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm">
                                    <option value="pendente">— manter pendente —</option>
                                    <option v-for="o in opcoesStatus" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                                <InputError :message="erros.dpa_status" />
                            </div>
                            <div class="md:col-span-2">
                                <Label>Observação do Coordenador <span class="text-rose-600">*</span></Label>
                                <textarea v-model="obs" rows="4"
                                    class="mt-1 w-full rounded-md border bg-background p-2 text-sm"
                                    placeholder="Descreva a justificativa da decisão..." />
                                <InputError :message="erros.dpa_obs_coordenador" />
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between border-t bg-muted/40 px-5 py-3">
                <Button variant="outline" @click="emit('close')">Cancelar</Button>
                <Button :disabled="!podeSalvar"
                    class="bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
                    @click="salvar">
                    <Save class="mr-2 size-4" /> Salvar revisão
                </Button>
            </div>
        </div>
        <PdfPreviewModal v-if="previewUrl" :url="previewUrl" :filename="`plano_${planoId}.pdf`" title="Pré-visualização do Plano" @close="previewUrl = null" />
    </div>
</template>
