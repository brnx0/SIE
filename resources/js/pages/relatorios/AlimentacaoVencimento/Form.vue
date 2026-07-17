<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CalendarClock, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

interface Escola { esc_id: number; esc_nome: string }

const props = defineProps<{
    escolas: Escola[];
    userEscola: Escola | null;
    podeVerCentral: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Itens Próximos do Vencimento', href: '/relatorios/itens-vencimento' },
];

const { restaurar, salvar } = useFiltrosRelatorio('alimentacao-vencimento', {
    escopo: '' as string,
    esc_id: null as number | null,
    dias: 30 as number,
    incluir_vencidos: false as boolean,
});
const salvos = restaurar();

// Quem tem lotação não escolhe escopo: o backend força a própria escola, então
// a tela não oferece central nem "todos" para não prometer o que não entrega.
const escopo = ref<string>(props.podeVerCentral ? (salvos.escopo || 'central') : 'escola');
const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null);
const dias = ref<number>(salvos.dias || 30);
const incluirVencidos = ref<boolean>(!!salvos.incluir_vencidos);
const gerando = ref(false);

const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const precisaEscola = computed(() => escopo.value === 'escola');
const valido = computed(() =>
    dias.value >= 1 && dias.value <= 365 && (!precisaEscola.value || !!escId.value),
);

// Trocar para central/todos deixaria uma escola escolhida pendurada no payload.
watch(escopo, (v) => { if (v !== 'escola') escId.value = null; });

function gerar() {
    if (!valido.value) return;
    salvar({ escopo: escopo.value, esc_id: escId.value, dias: dias.value, incluir_vencidos: incluirVencidos.value });
    gerando.value = true;
    const payload: Record<string, any> = {
        escopo: escopo.value,
        dias: dias.value,
        // 1/0 e não true/false: vai por query string, e o `false` viraria a
        // string "false", que a regra `boolean` do Laravel recusa.
        incluir_vencidos: incluirVencidos.value ? 1 : 0,
    };
    if (escId.value) payload.esc_id = escId.value;
    router.get('/relatorios/itens-vencimento/gerar', payload, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Itens Próximos do Vencimento" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <CalendarClock class="size-5 text-indigo-600" /> Itens Próximos do Vencimento
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Itens em estoque cujo lote vence dentro do prazo informado. Lote sem data de validade não entra no relatório.
            </p>

            <div class="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-3">
                <div v-if="podeVerCentral" class="grid gap-1.5">
                    <FormLabel :required="true">Estoque</FormLabel>
                    <select
                        v-model="escopo"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground"
                    >
                        <option value="central">Estoque central</option>
                        <option value="escola">Uma unidade escolar</option>
                        <option value="todos">Todos os estoques</option>
                    </select>
                </div>

                <div v-if="precisaEscola" class="grid gap-1.5">
                    <FormLabel :required="true">Unidade Escolar</FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" placeholder="Selecione a unidade..." />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel :required="true">Vence em até (dias)</FormLabel>
                    <Input v-model.number="dias" type="number" min="1" max="365" />
                </div>

                <div class="flex items-end">
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input v-model="incluirVencidos" type="checkbox" class="size-4 rounded border-input text-indigo-600 focus:ring-indigo-500" />
                        Incluir itens já vencidos
                    </label>
                </div>

                <div class="flex items-end justify-end sm:col-span-2 lg:col-span-3">
                    <Button :disabled="!valido || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" /> Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
