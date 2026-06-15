<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import PdfPreviewModal from '@/components/common/PdfPreviewModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type {
    AnoLetivoResumo,
    PlanoAee,
    PlanoAeeTurmaResumo,
    PlanoStatus,
    ProfessorResumoDiario,
} from '@/types/diario';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Printer, Save } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    plano: PlanoAee | null;
    professor: ProfessorResumoDiario;
    anosLetivos: AnoLetivoResumo[];
}>();

const isEdit = computed(() => !!props.plano);

const statusAtual = computed<PlanoStatus>(() => props.plano?.dae_status ?? 'pendente');
const readonly = computed(() => ['aprovado', 'reprovado'].includes(statusAtual.value));

const anlInicial = computed<number | null>(() => {
    if (props.plano) return props.plano.dae_anl_id;
    if (props.anosLetivos.length === 1) return props.anosLetivos[0].anl_id;
    const exer = props.anosLetivos.find((a) => a.anl_fl_em_exercicio);
    return exer?.anl_id ?? null;
});

// Valores iniciais derivados do prop atual. Extraído numa função porque o
// painel é mantido em cache pelo shell de abas (keep-alive): a MESMA instância
// do Form é reusada entre create/edit/edit-de-outro-registro. Sem re-sincronizar
// o useForm quando props.plano muda, o form fica preso aos valores do 1º mount
// (tudo em branco quando o 1º mount foi o "Novo plano").
const buildData = () => ({
    dae_esc_id: props.plano?.dae_esc_id ?? null,
    dae_anl_id: anlInicial.value,
    dae_tur_id: props.plano?.dae_tur_id ?? null,
    dae_tema: props.plano?.dae_tema ?? '',
    dae_dt_inicio: props.plano?.dae_dt_inicio?.substring(0, 10) ?? '',
    dae_dt_fim: props.plano?.dae_dt_fim?.substring(0, 10) ?? '',
    dae_objetivo: props.plano?.dae_objetivo ?? '',
    dae_diagnostico: props.plano?.dae_diagnostico ?? '',
    dae_area_desenv: props.plano?.dae_area_desenv ?? '',
    dae_metas: props.plano?.dae_metas ?? '',
    dae_estrategias: props.plano?.dae_estrategias ?? '',
    dae_recursos: props.plano?.dae_recursos ?? '',
    dae_avaliacao: props.plano?.dae_avaliacao ?? '',
});

const form = useForm(buildData());

const turmas = ref<PlanoAeeTurmaResumo[]>([]);
const escolas = ref<{ esc_id: number; esc_nome: string }[]>([]);

const fetchEscolas = async () => {
    if (!form.dae_anl_id) {
        escolas.value = [];
        return;
    }
    const url = new URL('/api/diario/planos-aee/escolas', window.location.origin);
    url.searchParams.set('anl_id', String(form.dae_anl_id));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) escolas.value = await r.json();
};

const fetchTurmas = async () => {
    if (!form.dae_anl_id || !form.dae_esc_id) {
        turmas.value = [];
        return;
    }
    const url = new URL('/api/diario/planos-aee/turmas', window.location.origin);
    url.searchParams.set('anl_id', String(form.dae_anl_id));
    url.searchParams.set('esc_id', String(form.dae_esc_id));
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (r.ok) turmas.value = await r.json();
};

watch(() => form.dae_anl_id, () => {
    if (!isEdit.value) {
        form.dae_esc_id = null;
        form.dae_tur_id = null;
        turmas.value = [];
    }
    fetchEscolas();
});
watch(() => form.dae_esc_id, () => {
    if (!isEdit.value) form.dae_tur_id = null;
    fetchTurmas();
});

onMounted(async () => {
    await fetchEscolas();
    if (form.dae_esc_id) await fetchTurmas();
});

// Painel reusado pelo shell de abas: re-sincroniza o form e os lookups quando
// o registro muda (create -> edit, edit de outro plano). Sem isto a instância
// em cache mantém os dados do 1º mount e a edição abre em branco.
watch(() => props.plano?.dae_id, async () => {
    form.defaults(buildData());
    form.reset();
    form.clearErrors();
    await fetchEscolas();
    if (form.dae_esc_id) await fetchTurmas();
    else turmas.value = [];
});

// Garante que o valor salvo no registro sempre apareça no lookup, mesmo
// que o filtro (lotação/vínculo) não o retorne — padrão incluir_ids.
const itemsEscola = computed(() => {
    const list = escolas.value.map((e) => ({ id: e.esc_id, label: e.esc_nome }));
    const s = props.plano?.escola;
    if (s && !list.some((i) => i.id === s.esc_id)) list.unshift({ id: s.esc_id, label: s.esc_nome });
    return list;
});
const itemsTurma = computed(() => {
    const list = turmas.value.map((t) => ({ id: t.tur_id, label: t.tur_nome }));
    const s = props.plano?.turma;
    if (s && !list.some((i) => i.id === s.tur_id)) list.unshift({ id: s.tur_id, label: s.tur_nome });
    return list;
});
const itemsAno = computed(() => {
    const list = props.anosLetivos.map((a) => ({ id: a.anl_id, label: String(a.anl_ano) }));
    const s = props.plano?.anoLetivo;
    if (s && !list.some((i) => i.id === s.anl_id)) list.unshift({ id: s.anl_id, label: String(s.anl_ano) });
    return list;
});

const submit = () => {
    if (isEdit.value) {
        form.put(`/diario/planos-aee/${props.plano!.dae_id}`, { preserveScroll: true });
    } else {
        form.post('/diario/planos-aee', { preserveScroll: true });
    }
};

const previewUrl = ref<string | null>(null);
const imprimir = () => {
    if (!props.plano?.dae_id) return;
    previewUrl.value = `/diario/planos-aee/${props.plano.dae_id}/pdf`;
};

const statusBadge = (s: PlanoStatus) => ({
    pendente: 'bg-amber-50 text-amber-700',
    aprovado: 'bg-emerald-50 text-emerald-700',
    reprovado: 'bg-rose-50 text-rose-700',
    correcao: 'bg-sky-50 text-sky-700',
}[s]);

const statusLabel = (s: PlanoStatus) => ({
    pendente: 'Pendente',
    aprovado: 'Aprovado',
    reprovado: 'Reprovado',
    correcao: 'Em correção',
}[s]);

const formatDateTime = (d?: string | null) => {
    if (!d) return '—';
    const dt = new Date(d);
    if (isNaN(dt.getTime())) return '—';
    return dt.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Plano de Aula AEE', href: '/diario/planos-aee' },
    { title: isEdit.value ? `Plano AEE #${props.plano!.dae_id}` : 'Novo plano', href: '#' },
];
</script>

<template>
    <Head :title="isEdit ? `Editar Plano AEE #${plano!.dae_id}` : 'Novo Plano de Aula AEE'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <!-- Header + ações topo -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        {{ isEdit ? `Editar Plano de Aula AEE` : 'Novo Plano de Aula AEE' }}
                    </h1>
                    <div class="mt-1 flex items-center gap-2">
                        <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadge(statusAtual)]">
                            {{ statusLabel(statusAtual) }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="router.visit('/diario/planos-aee')">
                        <ArrowLeft class="mr-2 size-4" /> Voltar
                    </Button>
                    <Button v-if="isEdit" variant="outline" @click="imprimir">
                        <Printer class="mr-2 size-4" /> Imprimir
                    </Button>
                    <Button :disabled="form.processing || readonly"
                        class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        @click="submit">
                        <Save class="mr-2 size-4" /> Salvar
                    </Button>
                </div>
            </div>

            <!-- Cabeçalho -->
            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-muted-foreground">Cabeçalho</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <div class="md:col-span-12">
                        <Label>Professor</Label>
                        <Input :model-value="professor.fun_nome" readonly />
                    </div>

                    <div class="md:col-span-8">
                        <Label>Tema <span class="text-rose-600">*</span></Label>
                        <Input v-model="form.dae_tema" :disabled="readonly" maxlength="255" />
                        <InputError :message="form.errors.dae_tema" />
                    </div>
                    <div class="md:col-span-2">
                        <Label>Data Inicial <span class="text-rose-600">*</span></Label>
                        <Input type="date" v-model="form.dae_dt_inicio" :disabled="readonly" />
                        <InputError :message="form.errors.dae_dt_inicio" />
                    </div>
                    <div class="md:col-span-2">
                        <Label>Data Final <span class="text-rose-600">*</span></Label>
                        <Input type="date" v-model="form.dae_dt_fim" :disabled="readonly" />
                        <InputError :message="form.errors.dae_dt_fim" />
                    </div>

                    <div class="md:col-span-3">
                        <Label>Ano Letivo <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="form.dae_anl_id" :items="itemsAno"
                            placeholder="Selecione o ano..." :invalid="!!form.errors.dae_anl_id" />
                        <InputError :message="form.errors.dae_anl_id" />
                    </div>
                    <div class="md:col-span-5">
                        <Label>Escola <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="form.dae_esc_id" :items="itemsEscola"
                            placeholder="Selecione a escola..." :invalid="!!form.errors.dae_esc_id" />
                        <InputError :message="form.errors.dae_esc_id" />
                    </div>
                    <div class="md:col-span-4">
                        <Label>Turma AEE <span class="text-rose-600">*</span></Label>
                        <LocalCombobox v-model="form.dae_tur_id" :items="itemsTurma"
                            placeholder="Selecione..." :invalid="!!form.errors.dae_tur_id" />
                        <InputError :message="form.errors.dae_tur_id" />
                    </div>
                </div>
            </section>

            <!-- Detalhamento AEE -->
            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-muted-foreground">Detalhamento</h2>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <Label>Objetivo das Intervenções <span class="text-rose-600">*</span></Label>
                        <textarea v-model="form.dae_objetivo" rows="6" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dae_objetivo" />
                    </div>
                    <div>
                        <Label>Diagnóstico da Deficiência</Label>
                        <textarea v-model="form.dae_diagnostico" rows="3" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dae_diagnostico" />
                    </div>
                    <div>
                        <Label>Área de Desenvolvimento</Label>
                        <textarea v-model="form.dae_area_desenv" rows="6" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dae_area_desenv" />
                    </div>
                    <div>
                        <Label>Metas de Aprendizagem <span class="text-rose-600">*</span></Label>
                        <textarea v-model="form.dae_metas" rows="6" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dae_metas" />
                    </div>
                    <div>
                        <Label>Estratégias de Intervenção <span class="text-rose-600">*</span></Label>
                        <textarea v-model="form.dae_estrategias" rows="6" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dae_estrategias" />
                    </div>
                    <div>
                        <Label>Recursos Utilizados <span class="text-rose-600">*</span></Label>
                        <textarea v-model="form.dae_recursos" rows="4" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dae_recursos" />
                    </div>
                    <div>
                        <Label>Avaliação Feita Durante a Intervenção</Label>
                        <textarea v-model="form.dae_avaliacao" rows="5" :disabled="readonly"
                            class="w-full rounded-md border bg-background p-2 text-sm" />
                        <InputError :message="form.errors.dae_avaliacao" />
                    </div>
                </div>
            </section>

            <!-- Coordenação -->
            <section v-if="isEdit && statusAtual !== 'pendente'" class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-muted-foreground">Observação do coordenador</h2>
                    <div v-if="(plano as any)?.validado_por || (plano as any)?.dae_validado_em" class="text-xs text-muted-foreground">
                        Validado por <span class="font-medium">{{ (plano as any)?.validado_por?.name ?? '—' }}</span>
                        em <span class="font-medium">{{ formatDateTime((plano as any)?.dae_validado_em) }}</span>
                    </div>
                </div>
                <textarea :value="plano?.dae_obs_coordenador ?? ''" readonly rows="4"
                    class="w-full rounded-md border bg-muted/30 p-2 text-sm" />
            </section>

            <!-- Rodapé -->
            <div class="flex justify-between border-t pt-4">
                <Button variant="outline" @click="router.visit('/diario/planos-aee')">Voltar</Button>
                <Button :disabled="form.processing || readonly"
                    class="bg-indigo-600 text-white hover:bg-indigo-700" @click="submit">
                    <Save class="mr-2 size-4" /> Salvar
                </Button>
            </div>
        </div>
        <PdfPreviewModal v-if="previewUrl" :url="previewUrl" :filename="`plano_aee_${plano?.dae_id ?? ''}.pdf`" title="Pré-visualização do Plano AEE" @close="previewUrl = null" />
    </AppLayout>
</template>
