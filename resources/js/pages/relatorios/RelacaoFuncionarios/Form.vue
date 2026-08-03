<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { UsersRound, Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';

interface Escola { esc_id: number; esc_nome: string }
interface Cargo { crg_id: number; crg_nome: string }

const props = defineProps<{
    escolas: Escola[];
    userEscola: Escola | null;
    isAdmin: boolean;
    cargos: Cargo[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Relação de Funcionários', href: '/relatorios/relacao-funcionarios' },
];

const { restaurar, salvar } = useFiltrosRelatorio('relacao-funcionarios', {
    esc_id: null as number | null,
    crg_id: null as number | null,
    situacao: '' as '' | 'ativo' | 'inativo',
});
const salvos = restaurar();

const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null);
const crgId = ref<number | null>(salvos.crg_id ?? null);
const situacao = ref<'' | 'ativo' | 'inativo'>(salvos.situacao ?? '');
const gerando = ref(false);

const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsCargo = computed(() => props.cargos.map((c) => ({ id: c.crg_id, label: c.crg_nome })));

onMounted(() => {
    if (salvos.esc_id && !props.escolas.some((e) => e.esc_id === salvos.esc_id)) escId.value = props.userEscola?.esc_id ?? null;
    if (salvos.crg_id && !props.cargos.some((c) => c.crg_id === salvos.crg_id)) crgId.value = null;
});

function gerar() {
    salvar({ esc_id: escId.value, crg_id: crgId.value, situacao: situacao.value });
    gerando.value = true;
    const payload: Record<string, any> = {};
    if (escId.value) payload.esc_id = escId.value;
    if (crgId.value) payload.crg_id = crgId.value;
    if (situacao.value) payload.situacao = situacao.value;

    router.get('/relatorios/relacao-funcionarios/gerar', payload, {
        preserveScroll: true,
        onFinish: () => { gerando.value = false; },
    });
}
</script>

<template>
    <Head title="Relação de Funcionários" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <h1 class="text-xl font-semibold mb-1 flex items-center gap-2">
                <UsersRound class="size-5 text-indigo-600" /> Relação de Funcionários
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Relação de funcionários cadastrados com cargo, escolaridade e unidade de lotação. Filtros opcionais — vazio traz todos.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid gap-4 sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel>Unidade Escolar</FormLabel>
                    <LocalCombobox
                        v-model="escId"
                        :items="itemsEscola"
                        :disabled="!isAdmin && !!userEscola"
                        placeholder="Todas as unidades"
                    />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Cargo / Função</FormLabel>
                    <LocalCombobox v-model="crgId" :items="itemsCargo" placeholder="Todos os cargos" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Situação</FormLabel>
                    <select v-model="situacao" class="h-9 rounded-md border bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                        <option value="">Todos</option>
                        <option value="ativo">Ativos</option>
                        <option value="inativo">Inativos</option>
                    </select>
                </div>

                <div class="sm:col-span-3 flex justify-end">
                    <Button :disabled="gerando" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="gerar">
                        <Loader2 v-if="gerando" class="mr-1 size-4 animate-spin" />
                        Gerar Relatório
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
