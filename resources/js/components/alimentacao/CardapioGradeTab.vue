<script setup lang="ts">
import RemoteCombobox from '@/components/common/RemoteCombobox.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { usePermissao } from '@/composables/usePermissao';
import type { Cardapio, CardapioDiaLinha } from '@/types/alimentacao';
import { router, useForm } from '@inertiajs/vue3';
import { Info, LoaderCircle, Plus, Trash2, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    cardapio: Cardapio;
    dias: CardapioDiaLinha[];
    turnos: string[];
    refeicoes: string[];
}>();

const { isReadOnly } = usePermissao();

const DIAS = [
    { n: 1, label: 'Segunda' },
    { n: 2, label: 'Terça' },
    { n: 3, label: 'Quarta' },
    { n: 4, label: 'Quinta' },
    { n: 5, label: 'Sexta' },
    { n: 6, label: 'Sábado' },
    { n: 7, label: 'Domingo' },
];

const base = computed(() => `/alimentacao/cardapios/${props.cardapio.crd_id}/dias`);

// Renderiza direto do prop — cópia em ref fica obsoleta após o reload do Inertia.
const linhas = computed(() => props.dias ?? []);

// A grade mostra um turno por vez: 7 dias × 4 refeições × N turnos numa tela só
// vira ilegível.
const turnoAtivo = ref(props.turnos[0] ?? 'MATUTINO');

// Semana aberta na tela mas ainda sem prato nenhum. Sem isso ela não existiria
// em `semanas` (que sai dos pratos salvos), o seletor não apareceria e a grade
// ficaria vazia sem dizer o porquê — dá a impressão de que os pratos sumiram.
const semanasExtras = ref<number[]>([]);

// Semanas já usadas + a 1 + as recém-abertas. Rodízio (semana 2, 3...) só aparece
// se for usado — a coluna existe no banco, mas a rede de semana fixa nem vê.
const semanas = computed(() => {
    const usadas = new Set<number>([1, ...linhas.value.map((l) => l.cdi_semana), ...semanasExtras.value]);
    return [...usadas].sort((a, b) => a - b);
});
const semanaAtiva = ref(1);
const temRodizio = computed(() => semanas.value.length > 1);

/** Semana aberta que ainda não tem nenhum prato — usada para avisar na tela. */
const semanaVazia = computed(() =>
    temRodizio.value && !linhas.value.some((l) => l.cdi_semana === semanaAtiva.value),
);

const pratoNoSlot = (dia: number, refeicao: string): CardapioDiaLinha | undefined =>
    linhas.value.find(
        (l) =>
            l.cdi_semana === semanaAtiva.value &&
            l.cdi_dia_semana === dia &&
            l.cdi_turno === turnoAtivo.value &&
            l.cdi_refeicao === refeicao,
    );

const form = useForm<{
    cdi_prt_id: number | null;
    cdi_semana: number;
    cdi_dia_semana: number | null;
    cdi_turno: string;
    cdi_refeicao: string;
}>({
    cdi_prt_id: null,
    cdi_semana: 1,
    cdi_dia_semana: null,
    cdi_turno: props.turnos[0] ?? 'MATUTINO',
    cdi_refeicao: props.refeicoes[0] ?? '',
});

const pratoSelecionado = ref<{ id: number; label: string } | null>(null);

// Clicar na célula vazia já preenche dia/turno/refeição — o usuário só escolhe o prato.
const prepararSlot = (dia: number, refeicao: string) => {
    if (isReadOnly.value) return;
    form.cdi_dia_semana = dia;
    form.cdi_refeicao = refeicao;
    form.cdi_turno = turnoAtivo.value;
    form.cdi_semana = semanaAtiva.value;
};

const slotPreparado = computed(() => form.cdi_dia_semana !== null);

const adicionar = () => {
    if (isReadOnly.value) return;
    form.post(base.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.cdi_prt_id = null;
            form.cdi_dia_semana = null;
            pratoSelecionado.value = null;
        },
    });
};

const remover = (linha: CardapioDiaLinha) => {
    if (!confirm(`Remover "${linha.prato_nome}" deste dia?`)) return;
    router.delete(`${base.value}/${linha.cdi_id}`, { preserveScroll: true, preserveState: true });
};

const adicionarSemana = () => {
    const proxima = Math.max(...semanas.value) + 1;
    if (proxima > 5) return;
    // Registra a semana localmente: ela só existe no banco quando ganhar o
    // primeiro prato, mas precisa aparecer no seletor já.
    semanasExtras.value.push(proxima);
    semanaAtiva.value = proxima;
    form.cdi_semana = proxima;
};

const labelDia = (n: number) => DIAS.find((d) => d.n === n)?.label ?? '';
</script>

<template>
    <div class="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Grade do cardápio</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Qual prato é servido em cada dia da semana, por turno e refeição. Clique num espaço vazio para
                    preencher.
                </p>
            </div>
            <RefreshButton />
        </div>

        <div
            v-if="linhas.length === 0"
            class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            <p>Grade não montada — este cardápio ainda não define nenhum prato.</p>
        </div>

        <!-- Semana recém-aberta abre vazia: sem este aviso, a grade em branco
             passa a impressão de que os pratos das outras semanas sumiram. -->
        <div
            v-else-if="semanaVazia"
            class="flex items-start gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-900/50 dark:bg-sky-900/20 dark:text-sky-200"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <p>
                Você está na <strong>semana {{ semanaAtiva }}</strong> do rodízio, que ainda não tem pratos.
                Os pratos das outras semanas continuam salvos — troque de semana no seletor acima para vê-los.
            </p>
        </div>

        <!-- Turno e semana -->
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-1 rounded-lg border bg-muted/40 p-1">
                <button
                    v-for="t in turnos"
                    :key="t"
                    type="button"
                    :class="[
                        'rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                        turnoAtivo === t
                            ? 'bg-indigo-600 text-white'
                            : 'text-muted-foreground hover:bg-muted',
                    ]"
                    @click="turnoAtivo = t"
                >
                    {{ t }}
                </button>
            </div>

            <div v-if="temRodizio" class="flex items-center gap-2">
                <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Rodízio</span>
                <div class="flex items-center gap-1 rounded-lg border bg-muted/40 p-1">
                    <button
                        v-for="s in semanas"
                        :key="s"
                        type="button"
                        :class="[
                            'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                            semanaAtiva === s ? 'bg-indigo-600 text-white' : 'text-muted-foreground hover:bg-muted',
                        ]"
                        @click="semanaAtiva = s"
                    >
                        Semana {{ s }}
                        <!-- Ponto marca semana aberta que ainda não tem prato salvo. -->
                        <span
                            v-if="!linhas.some((l) => l.cdi_semana === s)"
                            :class="['size-1.5 rounded-full', semanaAtiva === s ? 'bg-white/70' : 'bg-amber-500']"
                            title="Semana ainda sem pratos"
                        />
                    </button>
                </div>
            </div>

            <Button
                v-if="!isReadOnly && Math.max(...semanas) < 5"
                type="button"
                variant="outline"
                size="sm"
                class="text-xs"
                @click="adicionarSemana"
            >
                <Plus class="mr-1 size-3.5" /> Semana de rodízio
            </Button>
        </div>

        <!-- Grade -->
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-3 py-3">Refeição</th>
                        <th v-for="d in DIAS" :key="d.n" class="px-3 py-3 text-center">{{ d.label }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="r in refeicoes" :key="r">
                        <td class="px-3 py-2 text-xs font-medium text-muted-foreground">{{ r }}</td>
                        <td v-for="d in DIAS" :key="d.n" class="px-2 py-2 text-center align-middle">
                            <div
                                v-if="pratoNoSlot(d.n, r)"
                                class="group flex items-center justify-between gap-1 rounded-md bg-indigo-50 px-2 py-1.5 text-left text-xs dark:bg-indigo-900/30"
                            >
                                <span class="truncate font-medium text-indigo-900 dark:text-indigo-200">
                                    {{ pratoNoSlot(d.n, r)!.prato_nome }}
                                </span>
                                <button
                                    v-if="!isReadOnly"
                                    type="button"
                                    class="shrink-0 text-rose-500 opacity-0 transition-opacity group-hover:opacity-100"
                                    @click="remover(pratoNoSlot(d.n, r)!)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                            <button
                                v-else-if="!isReadOnly"
                                type="button"
                                :class="[
                                    'w-full rounded-md border border-dashed py-1.5 text-xs transition-colors',
                                    form.cdi_dia_semana === d.n && form.cdi_refeicao === r
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                        : 'border-muted-foreground/30 text-muted-foreground hover:border-indigo-400 hover:text-indigo-600',
                                ]"
                                @click="prepararSlot(d.n, r)"
                            >
                                <Plus class="mx-auto size-3.5" />
                            </button>
                            <span v-else class="text-xs text-muted-foreground">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Preenchimento do slot escolhido -->
        <fieldset
            v-if="!isReadOnly && slotPreparado"
            :disabled="form.processing"
            class="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-6 sm:items-start"
        >
            <div class="sm:col-span-6">
                <p class="text-sm font-medium">
                    {{ labelDia(form.cdi_dia_semana!) }} · {{ form.cdi_turno }} · {{ form.cdi_refeicao }}
                    <span v-if="temRodizio" class="text-muted-foreground">· Semana {{ form.cdi_semana }}</span>
                </p>
            </div>

            <div class="grid gap-2 sm:col-span-4">
                <FormLabel :required="true">Prato</FormLabel>
                <RemoteCombobox
                    v-model="form.cdi_prt_id"
                    fetch-url="/api/alimentacao/pratos/search"
                    id-key="prt_id"
                    label-key="prt_nome"
                    placeholder="Buscar prato..."
                    :invalid="!!form.errors.cdi_prt_id"
                    :selected-label="pratoSelecionado?.label ?? null"
                    @selected="pratoSelecionado = $event"
                />
                <InputError :message="form.errors.cdi_prt_id" />
                <InputError :message="form.errors.cdi_dia_semana" />
            </div>

            <div class="flex gap-2 sm:col-span-2 sm:pt-7">
                <Button
                    type="button"
                    class="flex-1 bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    @click="adicionar"
                >
                    <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                    <Plus v-else class="mr-2 size-4" />
                    Incluir
                </Button>
                <Button type="button" variant="outline" @click="form.cdi_dia_semana = null">Cancelar</Button>
            </div>
        </fieldset>
    </div>
</template>
