<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';
import { CAMPO } from '@/lib/formCampo';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { School, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Bairro { bai_id: number; bai_nome: string }
interface Segmento { seg_id: number; seg_nome: string }

const props = defineProps<{
    bairros: Bairro[];
    segmentos: Segmento[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Relação de Escolas', href: '/relatorios/relacao-escolas' },
];

const { restaurar, salvar } = useFiltrosRelatorio('relacao-escolas', {
    seg_id: null as number | null,
    bai_id: null as number | null,
    status: 'todas' as 'todas' | 'ativa' | 'inativa',
});
const salvos = restaurar();

const segId = ref<number | null>(salvos.seg_id ?? null);
const baiId = ref<number | null>(salvos.bai_id ?? null);
const status = ref<'todas' | 'ativa' | 'inativa'>(salvos.status ?? 'todas');
const gerando = ref(false);

const itemsSegmento = computed(() => props.segmentos.map((s) => ({ id: s.seg_id, label: s.seg_nome })));
const itemsBairro = computed(() => props.bairros.map((b) => ({ id: b.bai_id, label: b.bai_nome })));

function limpar() {
    segId.value = null;
    baiId.value = null;
    status.value = 'todas';
}

function gerar() {
    gerando.value = true;
    salvar({ seg_id: segId.value, bai_id: baiId.value, status: status.value });
    router.get('/relatorios/relacao-escolas/gerar', {
        ...(segId.value ? { seg_id: Number(segId.value) } : {}),
        ...(baiId.value ? { bai_id: Number(baiId.value) } : {}),
        status: status.value,
    }, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Relação de Escolas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <School class="size-5 text-indigo-600" /> Relação de Escolas
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Cadastro institucional da rede em ordem alfabética: códigos, endereço, contato e tipos de ensino
                ofertados. Diferente dos demais relatórios, este também alcança escolas inativas.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid items-start gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel>Tipo de Ensino</FormLabel>
                    <LocalCombobox v-model="segId" :items="itemsSegmento" placeholder="Todos os tipos" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Bairro / Região</FormLabel>
                    <LocalCombobox v-model="baiId" :items="itemsBairro" placeholder="Todos os bairros" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Status</FormLabel>
                    <select v-model="status" :class="CAMPO">
                        <option value="todas">Todas</option>
                        <option value="ativa">Somente ativas</option>
                        <option value="inativa">Somente inativas</option>
                    </select>
                </div>

                <div class="sm:col-span-2 lg:col-span-3 flex items-center justify-between gap-3">
                    <Button variant="outline" @click="limpar">Limpar filtros</Button>
                    <Button :disabled="gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
