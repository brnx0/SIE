<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RemoteCombobox from '@/components/common/RemoteCombobox.vue';
import MunicipioCombobox from '@/components/aluno/MunicipioCombobox.vue';
import type { Municipio } from '@/types/aluno';
import { router, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Trash2, Pencil, X, Save } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface AnoSistema {
    anl_id: number; anl_ano: number; serie: string | null; escola: string | null;
    situacao: string | null; disciplinas: { nome: string; mf: string }[];
}
interface Segmento { seg_id: number; seg_nome_reduzido: string }
interface Conceito { cnc_id: number; cnc_sigla: string; cnc_descricao: string }
interface Situacao { tas_cod: number; tas_descricao: string }
interface DiscLinha { hmd_dis_id: number | null; dis_nome?: string | null; tipo: 'num' | 'cnc'; hmd_mf: number | string | null; hmd_cnc_id: number | null; hmd_conceito: string }
interface DiscSalva { hmd_dis_id: number; dis_nome: string | null; hmd_mf: number | string | null; hmd_cnc_id: number | null; hmd_conceito: string | null; cnc_sigla: string | null }
interface Lancamento {
    hmn_id: number;
    hmn_ano: number;
    hmn_ser_id: number; serie: string | null;
    hmn_esc_id: number | null; escola: string | null; hmn_esc_nome: string | null;
    hmn_mun_id: number | null; municipio: Municipio | null;
    hmn_carga_horaria: number | null; hmn_dias_letivos: number | null; hmn_frequencia: number | string | null;
    hmn_tas_cod: number | null; situacao: string | null;
    disciplinas: DiscSalva[];
}

// `active` gateia o carregamento: no modal reflete o `open`; na tela é sempre true
// e o (re)carregamento acompanha a troca do aluno selecionado.
const props = withDefaults(defineProps<{ aluno: { aln_id: number; aln_nome: string } | null; active?: boolean }>(), {
    active: true,
});

const page = usePage();
const params = computed(() => (page.props as any).params ?? {});
const escolaUppercase = computed(() => !!params.value.nome_escola_caixa_alta);

const loading = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const anosSistema = ref<AnoSistema[]>([]);
const segmentos = ref<Segmento[]>([]);
const conceitos = ref<Conceito[]>([]);
const situacoes = ref<Situacao[]>([]);
const lancamentos = ref<Lancamento[]>([]);

const showForm = ref(false);
const escolaMode = ref<'rede' | 'livre'>('rede');

const form = reactive({
    hmn_id: null as number | null,
    hmn_ano: '' as number | string,
    seg_id: null as number | null,
    hmn_ser_id: null as number | null,
    serie_label: null as string | null,
    hmn_esc_id: null as number | null,
    escola_label: null as string | null,
    hmn_esc_nome: '',
    hmn_mun_id: null as number | null,
    municipio_initial: null as Municipio | null,
    hmn_carga_horaria: '' as number | string,
    hmn_dias_letivos: '' as number | string,
    hmn_frequencia: '' as number | string,
    hmn_tas_cod: null as number | null,
    disciplinas: [] as DiscLinha[],
});

// Classe visual dos inputs numéricos (input nativo p/ garantir clamp no DOM).
const inputCls =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm tabular-nums ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';

// Corrige o valor exibido no próprio elemento — necessário porque clampar para
// um valor já vigente não dispara re-render do v-model interno do componente.
const soDigitos = (e: Event, max: number): string => {
    const el = e.target as HTMLInputElement;
    const s = el.value.replace(/\D/g, '').slice(0, max);
    el.value = s;
    return s;
};

const onAnoInput = (e: Event) => {
    const s = soDigitos(e, 4);
    form.hmn_ano = s === '' ? '' : Number(s);
};
const onHorasInput = (e: Event) => {
    const s = soDigitos(e, 5);
    form.hmn_carga_horaria = s === '' ? '' : Number(s);
};
const onDiasInput = (e: Event) => {
    const s = soDigitos(e, 3);
    form.hmn_dias_letivos = s === '' ? '' : Number(s);
};

// Frequência: decimal com teto de 100%.
const onFreqInput = (e: Event) => {
    const el = e.target as HTMLInputElement;
    let s = el.value.replace(/[^\d.]/g, '');
    const i = s.indexOf('.');
    if (i !== -1) s = s.slice(0, i + 1) + s.slice(i + 1).replace(/\./g, '');
    if (s !== '' && s !== '.' && Number(s) > 100) s = '100';
    el.value = s;
    form.hmn_frequencia = s;
};

// Nome da escola fora da rede nunca aceita acentos — removidos em tempo real,
// igual ao cadastro de escola (EscolaForm). Strip é sempre aplicado; só o caixa
// alta é que segue o parâmetro nome_escola_caixa_alta.
const stripAccents = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '');
watch(
    () => form.hmn_esc_nome,
    (v) => {
        if (typeof v !== 'string') return;
        let out = stripAccents(v);
        if (escolaUppercase.value) out = out.toLocaleUpperCase('pt-BR');
        if (out !== v) form.hmn_esc_nome = out;
    },
);

// Série filtrada pelo segmento escolhido (seg_id vira query da busca remota).
const serieFetchUrl = computed(() =>
    form.seg_id ? `/api/series/search?seg_id=${form.seg_id}` : '/api/series/search',
);
const onSegmentoChange = () => { form.hmn_ser_id = null; form.serie_label = null; };

const carregar = async () => {
    if (!props.aluno) return;
    loading.value = true;
    try {
        const r = await fetch(`/alunos/${props.aluno.aln_id}/historico-manual`, { headers: { Accept: 'application/json' } });
        if (r.ok) {
            const j = await r.json();
            anosSistema.value = j.anos_sistema ?? [];
            segmentos.value = j.segmentos ?? [];
            conceitos.value = j.conceitos ?? [];
            situacoes.value = j.situacoes ?? [];
            lancamentos.value = j.lancamentos ?? [];
        }
    } finally {
        loading.value = false;
    }
};

// Carrega ao ativar (modal abrindo) e sempre que o aluno selecionado mudar.
watch(
    () => [props.active, props.aluno?.aln_id] as const,
    ([active, alnId]) => {
        if (active && alnId) { showForm.value = false; errors.value = {}; carregar(); }
    },
    { immediate: true },
);

const resetForm = () => {
    form.hmn_id = null; form.hmn_ano = ''; form.seg_id = null; form.hmn_ser_id = null; form.serie_label = null;
    form.hmn_esc_id = null; form.escola_label = null; form.hmn_esc_nome = ''; form.hmn_mun_id = null; form.municipio_initial = null;
    form.hmn_carga_horaria = ''; form.hmn_dias_letivos = ''; form.hmn_frequencia = ''; form.hmn_tas_cod = null;
    form.disciplinas = [{ hmd_dis_id: null, dis_nome: null, tipo: 'num', hmd_mf: '', hmd_cnc_id: null, hmd_conceito: '' }];
    escolaMode.value = 'rede';
    errors.value = {};
};

const novo = () => { resetForm(); showForm.value = true; };

const editar = (l: Lancamento) => {
    form.hmn_id = l.hmn_id;
    form.hmn_ano = l.hmn_ano;
    form.seg_id = null;
    form.hmn_ser_id = l.hmn_ser_id; form.serie_label = l.serie;
    form.hmn_esc_id = l.hmn_esc_id; form.escola_label = l.escola;
    form.hmn_esc_nome = l.hmn_esc_nome ?? ''; form.hmn_mun_id = l.hmn_mun_id; form.municipio_initial = l.municipio;
    form.hmn_carga_horaria = l.hmn_carga_horaria ?? ''; form.hmn_dias_letivos = l.hmn_dias_letivos ?? '';
    form.hmn_frequencia = l.hmn_frequencia ?? ''; form.hmn_tas_cod = l.hmn_tas_cod;
    form.disciplinas = l.disciplinas.map((d) => ({
        hmd_dis_id: d.hmd_dis_id, dis_nome: d.dis_nome,
        // Conceitual = tem sigla digitada ou vinda do catálogo (lançamentos antigos).
        tipo: (d.hmd_conceito || d.hmd_cnc_id) ? 'cnc' : 'num',
        hmd_mf: d.hmd_mf ?? '', hmd_cnc_id: d.hmd_cnc_id ?? null,
        hmd_conceito: d.hmd_conceito ?? d.cnc_sigla ?? '',
    }));
    escolaMode.value = l.hmn_esc_id ? 'rede' : 'livre';
    errors.value = {};
    showForm.value = true;
};

const addDisc = () => form.disciplinas.push({ hmd_dis_id: null, dis_nome: null, tipo: 'num', hmd_mf: '', hmd_cnc_id: null, hmd_conceito: '' });

// Primeiro erro de conceito entre as linhas (mostrado uma vez sob a lista).
const erroConceito = computed(() => {
    const k = Object.keys(errors.value).find((key) => key.endsWith('.hmd_conceito'));
    return k ? errors.value[k] : null;
});
const rmDisc = (i: number) => form.disciplinas.splice(i, 1);

const salvar = () => {
    if (!props.aluno) return;
    errors.value = {};

    // Conceito continua obrigatório nas linhas conceituais (agora é texto livre).
    const semConceito = form.disciplinas.findIndex(
        (d) => d.hmd_dis_id && d.tipo === 'cnc' && !String(d.hmd_conceito ?? '').trim(),
    );
    if (semConceito >= 0) {
        errors.value = { [`disciplinas.${semConceito}.hmd_conceito`]: 'Informe o conceito.' };
        return;
    }

    processing.value = true;
    const payload: Record<string, unknown> = {
        hmn_id: form.hmn_id,
        hmn_ano: form.hmn_ano === '' ? null : form.hmn_ano,
        hmn_ser_id: form.hmn_ser_id,
        hmn_esc_id: escolaMode.value === 'rede' ? form.hmn_esc_id : null,
        hmn_esc_nome: escolaMode.value === 'livre' ? form.hmn_esc_nome : null,
        hmn_mun_id: escolaMode.value === 'livre' ? form.hmn_mun_id : null,
        hmn_carga_horaria: form.hmn_carga_horaria === '' ? null : form.hmn_carga_horaria,
        hmn_dias_letivos: form.hmn_dias_letivos === '' ? null : form.hmn_dias_letivos,
        hmn_frequencia: form.hmn_frequencia === '' ? null : form.hmn_frequencia,
        hmn_tas_cod: form.hmn_tas_cod,
        disciplinas: form.disciplinas
            .filter((d) => d.hmd_dis_id)
            .map((d) => ({
                hmd_dis_id: d.hmd_dis_id,
                hmd_mf: d.tipo === 'cnc' ? null : (d.hmd_mf === '' ? null : d.hmd_mf),
                hmd_cnc_id: null,
                hmd_conceito: d.tipo === 'cnc' ? String(d.hmd_conceito ?? '').trim() : null,
            })),
    };
    router.post(`/alunos/${props.aluno.aln_id}/historico-manual`, payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { showForm.value = false; carregar(); },
        onError: (e) => { errors.value = e as Record<string, string>; },
        onFinish: () => { processing.value = false; },
    });
};

const remover = (l: Lancamento) => {
    if (!props.aluno) return;
    if (!confirm(`Remover o lançamento de ${l.hmn_ano} (${l.serie ?? ''})?`)) return;
    router.delete(`/alunos/${props.aluno.aln_id}/historico-manual/${l.hmn_id}`, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => carregar(),
    });
};

const err = (k: string) => errors.value[k];
</script>

<template>
    <div v-if="loading" class="flex items-center gap-2 p-6 text-sm text-muted-foreground">
        <LoaderCircle class="size-4 animate-spin" /> Carregando...
    </div>

    <div v-else class="flex-1 space-y-5 overflow-y-auto p-4">
        <!-- Anos do sistema (read-only) -->
        <div v-if="anosSistema.length">
            <h3 class="mb-1 text-sm font-semibold">Anos calculados pelo sistema (somente leitura)</h3>
            <ul class="space-y-2">
                <li v-for="a in anosSistema" :key="a.anl_id" class="rounded-lg border bg-muted/30 p-2 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="tabular-nums font-medium">{{ a.anl_ano }}</span> — {{ a.serie ?? '—' }}
                        <span class="text-muted-foreground">· {{ a.escola ?? '—' }} · {{ a.situacao ?? '—' }}</span>
                    </div>
                    <div v-if="a.disciplinas.length" class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-muted-foreground">
                        <span v-for="(d, di) in a.disciplinas" :key="di">{{ d.nome }}: <span class="font-medium text-foreground">{{ d.mf }}</span></span>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Lançamentos manuais -->
        <div>
            <div class="mb-1 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Lançamentos manuais</h3>
                <Button v-if="!showForm" size="sm" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="novo">
                    <Plus class="mr-1 size-4" /> Novo lançamento
                </Button>
            </div>
            <div v-if="lancamentos.length === 0 && !showForm" class="rounded-lg border py-6 text-center text-sm text-muted-foreground">
                Nenhum lançamento manual.
            </div>
            <ul v-else-if="!showForm" class="divide-y rounded-lg border">
                <li v-for="l in lancamentos" :key="l.hmn_id" class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                    <div>
                        <span class="tabular-nums font-medium">{{ l.hmn_ano }}</span> — {{ l.serie ?? '—' }}
                        <span class="text-muted-foreground">· {{ l.escola ?? l.hmn_esc_nome ?? '—' }} · {{ l.situacao ?? '—' }} · {{ l.disciplinas.length }} disc.</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button variant="ghost" size="sm" @click="editar(l)"><Pencil class="size-4" /></Button>
                        <Button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700" @click="remover(l)"><Trash2 class="size-4" /></Button>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Form -->
        <div v-if="showForm" class="space-y-4 rounded-xl border bg-card p-4">
            <div class="grid items-start gap-4 sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Ano Letivo</FormLabel>
                    <input
                        :value="form.hmn_ano"
                        type="text"
                        inputmode="numeric"
                        maxlength="4"
                        placeholder="Ex.: 2018"
                        :class="inputCls"
                        @input="onAnoInput"
                    />
                    <InputError :message="err('hmn_ano')" />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Etapa de Ensino</FormLabel>
                    <select v-model.number="form.seg_id" class="h-9 w-full min-w-0 truncate rounded-md border border-input bg-background px-3 text-sm" @change="onSegmentoChange">
                        <option :value="null">Todos</option>
                        <option v-for="s in segmentos" :key="s.seg_id" :value="s.seg_id">{{ s.seg_nome_reduzido }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Série / Ano escolar</FormLabel>
                    <RemoteCombobox
                        v-model="form.hmn_ser_id"
                        :fetch-url="serieFetchUrl"
                        id-key="ser_id" label-key="ser_nome"
                        :selected-label="form.serie_label"
                        placeholder="Buscar série..."
                        :invalid="!!err('hmn_ser_id')"
                    />
                    <InputError :message="err('hmn_ser_id')" />
                </div>
            </div>

            <!-- Escola -->
            <div class="grid gap-2">
                <div class="flex items-center gap-4 text-sm">
                    <label class="flex items-center gap-1.5"><input type="radio" value="rede" v-model="escolaMode" /> Escola da rede</label>
                    <label class="flex items-center gap-1.5"><input type="radio" value="livre" v-model="escolaMode" /> Fora da rede (digitar)</label>
                </div>
                <div v-if="escolaMode === 'rede'">
                    <RemoteCombobox
                        v-model="form.hmn_esc_id"
                        fetch-url="/api/escolas"
                        id-key="esc_id" label-key="esc_nome"
                        :selected-label="form.escola_label"
                        placeholder="Buscar escola da rede..."
                        :invalid="!!err('hmn_esc_id')"
                    />
                    <InputError :message="err('hmn_esc_id')" />
                </div>
                <div v-else class="grid items-start gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Nome da escola</FormLabel>
                        <Input v-model="form.hmn_esc_nome" maxlength="180" placeholder="Nome da escola" />
                        <InputError :message="err('hmn_esc_nome')" />
                    </div>
                    <div class="grid gap-1.5">
                        <FormLabel>Município / UF</FormLabel>
                        <MunicipioCombobox
                            v-model="form.hmn_mun_id"
                            :initial="form.municipio_initial"
                            placeholder="Buscar município..."
                            :invalid="!!err('hmn_mun_id')"
                        />
                        <InputError :message="err('hmn_mun_id')" />
                    </div>
                </div>
            </div>

            <div class="grid items-start gap-4 sm:grid-cols-4">
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Situação final</FormLabel>
                    <select v-model.number="form.hmn_tas_cod" class="h-9 w-full min-w-0 truncate rounded-md border border-input bg-background px-3 text-sm">
                        <option :value="null">Selecione...</option>
                        <option v-for="s in situacoes" :key="s.tas_cod" :value="s.tas_cod">{{ s.tas_descricao }}</option>
                    </select>
                    <InputError :message="err('hmn_tas_cod')" />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Carga horária</FormLabel>
                    <input :value="form.hmn_carga_horaria" type="text" inputmode="numeric" maxlength="5" :class="inputCls" @input="onHorasInput" />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Dias letivos</FormLabel>
                    <input :value="form.hmn_dias_letivos" type="text" inputmode="numeric" maxlength="3" :class="inputCls" @input="onDiasInput" />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Frequência (%)</FormLabel>
                    <input :value="form.hmn_frequencia" type="text" inputmode="decimal" maxlength="6" :class="inputCls" @input="onFreqInput" />
                </div>
            </div>

            <!-- Disciplinas -->
            <div>
                <div class="mb-1 flex items-center justify-between">
                    <FormLabel :required="true">Disciplinas</FormLabel>
                    <Button size="sm" variant="outline" @click="addDisc"><Plus class="mr-1 size-3.5" /> Adicionar</Button>
                </div>
                <InputError :message="err('disciplinas')" />
                <div class="space-y-2">
                    <div v-for="(d, i) in form.disciplinas" :key="i" class="flex items-center gap-2">
                        <div class="flex-1">
                            <RemoteCombobox
                                v-model="d.hmd_dis_id"
                                fetch-url="/api/disciplinas/search"
                                id-key="dis_id" label-key="dis_nome"
                                :selected-label="d.dis_nome ?? null"
                                placeholder="Buscar disciplina..."
                            />
                        </div>
                        <select v-model="d.tipo" class="h-9 w-24 shrink-0 rounded-md border border-input bg-background px-2 text-sm">
                            <option value="num">Nota</option>
                            <option value="cnc">Conceito</option>
                        </select>
                        <Input v-if="d.tipo === 'num'" v-model="d.hmd_mf" type="number" min="0" max="10" step="0.1" placeholder="MF" class="w-24 shrink-0" />
                        <!-- Conceito digitável: aceita a sigla usada pelo município de
                             origem (R, S, H...), sem limitar ao catálogo local. -->
                        <Input
                            v-else
                            v-model="d.hmd_conceito"
                            maxlength="10"
                            placeholder="Conceito"
                            :list="conceitos.length ? 'conceitos-sugestoes' : undefined"
                            class="w-28 shrink-0"
                            :class="{ 'border-red-500': errors[`disciplinas.${i}.hmd_conceito`] }"
                            :title="errors[`disciplinas.${i}.hmd_conceito`] ?? ''"
                        />
                        <Button variant="ghost" size="sm" class="text-rose-600" @click="rmDisc(i)"><X class="size-4" /></Button>
                    </div>
                    <InputError :message="erroConceito" />
                </div>
                <!-- Sugestões do catálogo local; o campo continua aceitando qualquer valor. -->
                <datalist v-if="conceitos.length" id="conceitos-sugestoes">
                    <option v-for="c in conceitos" :key="c.cnc_id" :value="c.cnc_sigla">{{ c.cnc_descricao }}</option>
                </datalist>
            </div>

            <div class="flex justify-end gap-2 border-t pt-3">
                <Button variant="outline" size="sm" @click="showForm = false">Cancelar</Button>
                <Button size="sm" class="bg-indigo-600 text-white hover:bg-indigo-700" :disabled="processing" @click="salvar">
                    <LoaderCircle v-if="processing" class="mr-1 size-4 animate-spin" />
                    <Save v-else class="mr-1 size-4" /> Salvar
                </Button>
            </div>
        </div>
    </div>
</template>
