<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AnoLetivo, DiasLetivos, SegmentoResumo, Unidade } from '@/types/parametro';
import { router } from '@inertiajs/vue3';
import { CalendarRange, CopyPlus, LoaderCircle, Save, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    diasLetivos: DiasLetivos[];
    anosLetivos: AnoLetivo[];
    segmentos: SegmentoResumo[];
    unidades: Unidade[];
}>();

const MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

const anosOrdenados = computed(() =>
    [...props.anosLetivos].sort((a, b) => {
        if (a.anl_fl_em_exercicio !== b.anl_fl_em_exercicio) return a.anl_fl_em_exercicio ? -1 : 1;
        return b.anl_ano - a.anl_ano;
    }),
);

const anoAtual = props.anosLetivos.find((a) => a.anl_fl_em_exercicio);
const selectedAnlId = ref<number | null>(anoAtual?.anl_id ?? props.anosLetivos[0]?.anl_id ?? null);
const selectedSegId = ref<number | null>(props.segmentos[0]?.seg_id ?? null);

const processing = ref(false);

// Períodos (unidades) do ano selecionado.
const capitalize = (s: string) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);
const periodos = computed(() =>
    props.unidades
        .filter((u) => u.uni_anl_id === selectedAnlId.value)
        .slice()
        .sort((a, b) => a.uni_numero - b.uni_numero)
        .map((u) => ({ uni_id: u.uni_id, label: `${u.uni_numero}º ${capitalize(u.uni_tipo)}` })),
);

// Estado editável: meses[1..12] e periodos[uni_id].
const meses = reactive<Record<number, number | ''>>({});
const periodosVal = reactive<Record<number, number | ''>>({});

const registroAtual = computed(() =>
    props.diasLetivos.find((d) => d.dlt_anl_id === selectedAnlId.value && d.dlt_seg_id === selectedSegId.value) ?? null,
);

const carregar = () => {
    const reg = registroAtual.value;
    for (let m = 1; m <= 12; m++) meses[m] = reg?.dlt_meses?.[String(m)] ?? '';
    for (const p of periodos.value) periodosVal[p.uni_id] = reg?.dlt_periodos?.[String(p.uni_id)] ?? '';
};

watch([selectedAnlId, selectedSegId, periodos], carregar, { immediate: true });

const num = (v: number | '' | undefined) => (v === '' || v === undefined ? 0 : Number(v));
const totalMeses = computed(() => Object.values(meses).reduce((s, v) => s + num(v), 0));
const totalPeriodos = computed(() => Object.values(periodosVal).reduce((s, v) => s + num(v), 0));
const divergem = computed(() => periodos.value.length > 0 && totalMeses.value !== totalPeriodos.value && (totalMeses.value > 0 || totalPeriodos.value > 0));

const salvar = () => {
    if (!selectedAnlId.value || !selectedSegId.value) return;
    processing.value = true;
    router.post('/parametros/dias-letivos', {
        dlt_anl_id: selectedAnlId.value,
        dlt_seg_id: selectedSegId.value,
        meses: { ...meses },
        periodos: { ...periodosVal },
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { processing.value = false; },
    });
};

// ===== Migrar p/ outros segmentos =====
const showMigrar = ref(false);
const destinos = ref<number[]>([]);
const migrando = ref(false);
const outrosSegmentos = computed(() => props.segmentos.filter((s) => s.seg_id !== selectedSegId.value));

const toggleDestino = (id: number) => {
    const i = destinos.value.indexOf(id);
    if (i === -1) destinos.value.push(id);
    else destinos.value.splice(i, 1);
};

const abrirMigrar = () => { destinos.value = []; showMigrar.value = true; };

const migrar = () => {
    if (!selectedAnlId.value || !destinos.value.length) return;
    migrando.value = true;
    router.post('/parametros/dias-letivos/migrar', {
        dlt_anl_id: selectedAnlId.value,
        seg_ids: [...destinos.value],
        meses: { ...meses },
        periodos: { ...periodosVal },
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { showMigrar.value = false; },
        onFinish: () => { migrando.value = false; },
    });
};
</script>

<template>
    <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <CalendarRange class="size-4 text-indigo-600" />
                <h3 class="text-sm font-semibold">Dias Letivos por Curso (Segmento)</h3>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <select v-model="selectedAnlId" class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                    <option v-for="anl in anosOrdenados" :key="anl.anl_id" :value="anl.anl_id">
                        {{ anl.anl_ano }}{{ anl.anl_fl_em_exercicio ? ' (em exercício)' : '' }}
                    </option>
                </select>
                <select v-model="selectedSegId" class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                    <option v-for="s in segmentos" :key="s.seg_id" :value="s.seg_id">{{ s.seg_nome_reduzido ?? s.seg_nome_completo }}</option>
                </select>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            Informe a quantidade de dias letivos do ano por mês e por período. Os totais são somados automaticamente. 1 registro por curso/ano.
        </p>

        <!-- Meses -->
        <div>
            <div class="mb-2 flex items-center justify-between">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Por mês</h4>
                <span class="text-xs">Total: <b>{{ totalMeses }}</b> dia(s)</span>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                <div v-for="(nome, i) in MESES" :key="i" class="grid gap-1">
                    <Label class="text-xs">{{ nome }}</Label>
                    <Input v-model.number="meses[i + 1]" type="number" min="0" max="31" class="h-9 text-center" placeholder="0" />
                </div>
            </div>
        </div>

        <!-- Períodos -->
        <div v-if="periodos.length">
            <div class="mb-2 flex items-center justify-between">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Por período</h4>
                <span class="text-xs">Total: <b>{{ totalPeriodos }}</b> dia(s)</span>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                <div v-for="p in periodos" :key="p.uni_id" class="grid gap-1">
                    <Label class="text-xs">{{ p.label }}</Label>
                    <Input v-model.number="periodosVal[p.uni_id]" type="number" min="0" max="366" class="h-9 text-center" placeholder="0" />
                </div>
            </div>
        </div>
        <p v-else class="text-xs text-muted-foreground">Defina as unidades (períodos) deste ano na aba Unidade para detalhar por período.</p>

        <p v-if="divergem" class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            O total por mês ({{ totalMeses }}) difere do total por período ({{ totalPeriodos }}).
        </p>

        <div class="flex flex-wrap justify-end gap-2">
            <Button type="button" size="sm" variant="outline" :disabled="!selectedSegId || outrosSegmentos.length === 0" @click="abrirMigrar">
                <CopyPlus class="mr-2 size-4" /> Migrar p/ outros segmentos
            </Button>
            <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" :disabled="processing || !selectedSegId" @click="salvar">
                <LoaderCircle v-if="processing" class="mr-2 size-4 animate-spin" />
                <Save v-else class="mr-2 size-4" />
                Salvar
            </Button>
        </div>

        <!-- Migração p/ outros segmentos -->
        <div v-if="showMigrar" class="rounded-lg border bg-background p-4">
            <div class="mb-2 flex items-center justify-between">
                <h4 class="text-sm font-semibold">Replicar os valores atuais para:</h4>
                <button type="button" class="rounded p-1 text-muted-foreground hover:bg-muted" @click="showMigrar = false"><X class="size-4" /></button>
            </div>
            <p class="mb-3 text-xs text-muted-foreground">Os dias por mês e por período exibidos acima serão copiados para os segmentos marcados (mesmo ano). Sobrescreve o que já houver.</p>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <label v-for="s in outrosSegmentos" :key="s.seg_id" class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted/50">
                    <input type="checkbox" :checked="destinos.includes(s.seg_id)" @change="toggleDestino(s.seg_id)" />
                    <span>{{ s.seg_nome_reduzido ?? s.seg_nome_completo }}</span>
                </label>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" @click="showMigrar = false"><X class="mr-2 size-4" /> Cancelar</Button>
                <Button type="button" size="sm" class="bg-indigo-600 hover:bg-indigo-700" :disabled="migrando || !destinos.length" @click="migrar">
                    <LoaderCircle v-if="migrando" class="mr-2 size-4 animate-spin" />
                    <CopyPlus v-else class="mr-2 size-4" />
                    Replicar ({{ destinos.length }})
                </Button>
            </div>
        </div>
    </div>
</template>
