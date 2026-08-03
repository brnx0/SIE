<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { PackageMinus, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

interface Escola { esc_id: number; esc_nome: string }
interface TipoRetirada { tpr_id: number; tpr_nome: string }

const props = defineProps<{
    escolas: Escola[];
    userEscola: Escola | null;
    tipos: TipoRetirada[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Retirada de Alimentos', href: '/relatorios/retirada-alimentos' },
];

const hoje = () => new Date().toISOString().slice(0, 10);
const inicioDoMes = () => {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
};

const { restaurar, salvar } = useFiltrosRelatorio('alimentacao-retirada', {
    esc_id: null as number | null,
    tpr_id: null as number | null,
    dt_ini: '' as string,
    dt_fim: '' as string,
});
const salvos = restaurar();

const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null);
const tprId = ref<number | null>(salvos.tpr_id ?? null);
const dtIni = ref<string>(salvos.dt_ini || inicioDoMes());
const dtFim = ref<string>(salvos.dt_fim || hoje());
const gerando = ref(false);

const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsTipo = computed(() => props.tipos.map((t) => ({ id: t.tpr_id, label: t.tpr_nome })));
const periodoValido = computed(() => !!dtIni.value && !!dtFim.value && dtIni.value <= dtFim.value);

const clampAno = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const [ano] = el.value.split('-');
    if (ano && ano.length > 4) el.value = `${ano.slice(0, 4)}${el.value.slice(ano.length)}`;
};

function gerar() {
    if (!periodoValido.value) return;
    salvar({ esc_id: escId.value, tpr_id: tprId.value, dt_ini: dtIni.value, dt_fim: dtFim.value });
    gerando.value = true;
    const payload: Record<string, any> = { dt_ini: dtIni.value, dt_fim: dtFim.value };
    if (escId.value) payload.esc_id = escId.value;
    if (tprId.value) payload.tpr_id = tprId.value;
    router.get('/relatorios/retirada-alimentos/gerar', payload, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Retirada de Alimentos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="mb-1 flex items-center gap-2 text-xl font-semibold">
                <PackageMinus class="size-5 text-indigo-600" /> Retirada de Alimentos
            </h1>
            <p class="mb-6 text-sm text-muted-foreground">
                Retiradas do estoque no período, com gráfico comparativo entre as unidades. Deixe a unidade em branco para comparar todas.
            </p>

            <div class="grid items-start gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <div class="grid gap-1.5">
                    <FormLabel>Unidade Escolar <span class="text-xs font-normal text-muted-foreground">(opcional)</span></FormLabel>
                    <LocalCombobox v-model="escId" :items="itemsEscola" placeholder="Todas as unidades" />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Motivo <span class="text-xs font-normal text-muted-foreground">(opcional)</span></FormLabel>
                    <LocalCombobox v-model="tprId" :items="itemsTipo" placeholder="Todos os motivos" />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Data Inicial</FormLabel>
                    <Input v-model="dtIni" type="date" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel :required="true">Data Final</FormLabel>
                    <Input v-model="dtFim" type="date" min="2000-01-01" max="2099-12-31" @input="clampAno" />
                </div>
                <div class="flex items-end justify-between gap-3 sm:col-span-2 lg:col-span-4">
                    <p v-if="dtIni && dtFim && dtIni > dtFim" class="text-sm text-rose-600">
                        A data inicial não pode ser maior que a final.
                    </p>
                    <span v-else />
                    <Button :disabled="!periodoValido || gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" /> Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
