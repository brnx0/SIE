<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import FormLabel from '@/components/common/FormLabel.vue';
import LocalCombobox from '@/components/common/LocalCombobox.vue';
import { useFiltrosRelatorio } from '@/composables/useFiltrosRelatorio';
import { CAMPO } from '@/lib/formCampo';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Contact, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Escola { esc_id: number; esc_nome: string }
interface Cargo { crg_id: number; crg_nome: string }

const props = defineProps<{
    escolas: Escola[];
    cargos: Cargo[];
    isAdmin: boolean;
    userEscola?: { esc_id: number; esc_nome: string } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Relação de Funcionários', href: '/relatorios/relacao-funcionarios' },
];

const { restaurar, salvar } = useFiltrosRelatorio('relacao-funcionarios', {
    esc_id: null as number | null,
    crg_id: null as number | null,
    situacao: 'todos' as 'todos' | 'ativo' | 'inativo',
});
const salvos = restaurar();

const escId = ref<number | null>(salvos.esc_id ?? props.userEscola?.esc_id ?? null);
const crgId = ref<number | null>(salvos.crg_id ?? null);
const situacao = ref<'todos' | 'ativo' | 'inativo'>(salvos.situacao ?? 'todos');
const gerando = ref(false);

const itemsEscola = computed(() => props.escolas.map((e) => ({ id: e.esc_id, label: e.esc_nome })));
const itemsCargo = computed(() => props.cargos.map((c) => ({ id: c.crg_id, label: c.crg_nome })));

function limpar() {
    escId.value = props.isAdmin ? null : (props.userEscola?.esc_id ?? null);
    crgId.value = null;
    situacao.value = 'todos';
}

function gerar() {
    gerando.value = true;
    salvar({ esc_id: escId.value, crg_id: crgId.value, situacao: situacao.value });
    router.get('/relatorios/relacao-funcionarios/gerar', {
        ...(escId.value ? { esc_id: Number(escId.value) } : {}),
        ...(crgId.value ? { crg_id: Number(crgId.value) } : {}),
        situacao: situacao.value,
    }, {
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
                <Contact class="size-5 text-indigo-600" /> Relação de Funcionários
            </h1>
            <p class="text-sm text-muted-foreground mb-6">
                Relação nominal dos funcionários da rede em ordem alfabética: matrícula, cargo/função,
                escolaridade, unidade de lotação e situação. Uma linha por vínculo de lotação vigente.
            </p>

            <div class="rounded-xl border bg-card p-6 shadow-sm grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="grid gap-1.5">
                    <FormLabel>Unidade Escolar</FormLabel>
                    <template v-if="isAdmin">
                        <LocalCombobox v-model="escId" :items="itemsEscola" placeholder="Todas as unidades" />
                    </template>
                    <template v-else>
                        <span class="flex h-9 items-center rounded-md border bg-muted px-3 text-sm text-muted-foreground">
                            {{ userEscola?.esc_nome ?? '—' }}
                        </span>
                    </template>
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Cargo / Função</FormLabel>
                    <LocalCombobox v-model="crgId" :items="itemsCargo" placeholder="Todos os cargos" />
                </div>

                <div class="grid gap-1.5">
                    <FormLabel>Situação</FormLabel>
                    <select v-model="situacao" :class="CAMPO">
                        <option value="todos">Todos</option>
                        <option value="ativo">Somente ativos</option>
                        <option value="inativo">Somente inativos</option>
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
