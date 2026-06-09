<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FormLabel from '@/components/common/FormLabel.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Eye } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Tipo { tmv_cod: number; tmv_descricao: string }
interface Movimentacao {
    mva_id: number;
    mva_dt_movimentacao: string;
    mva_protocolo: string | null;
    aluno: { aln_id: number; aln_nome: string; aln_cpf: string | null } | null;
    tipo: Tipo | null;
    matricula_origem: { tma_id: number; turma: { tur_nome: string; escola: { esc_nome: string } | null } | null } | null;
    matricula_destino: { tma_id: number; turma: { tur_nome: string; escola: { esc_nome: string } | null } | null } | null;
    user: { id: number; name: string } | null;
}

const props = defineProps<{
    movimentacoes: { data: Movimentacao[]; links: any[]; current_page: number; last_page: number; total: number };
    filters: { search: string; tipo: number | null; per_page: number };
    tipos: Tipo[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Movimentações', href: '/movimentacoes' }];

const search  = ref(props.filters.search ?? '');
const tipo    = ref<number | ''>(props.filters.tipo ?? '');
const perPage = ref<number>(props.filters.per_page ?? 25);

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(aplicar, 350);
});
watch([tipo, perPage], aplicar);

function aplicar() {
    router.get('/movimentacoes', {
        search: search.value || undefined,
        tipo: tipo.value || undefined,
        per_page: perPage.value,
    }, { preserveScroll: true, preserveState: true, replace: true });
}

const formatDate = (iso: string | null) => {
    if (!iso) return '—';
    const [y, m, d] = iso.split('T')[0].split('-');
    return `${d}/${m}/${y}`;
};
</script>

<template>
    <Head title="Movimentações" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-[95%] py-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold">Movimentações do Aluno</h1>
                <Link href="/movimentacoes/create">
                    <Button class="bg-indigo-600 text-white hover:bg-indigo-700">
                        <Plus class="mr-1 size-4" /> Nova Movimentação
                    </Button>
                </Link>
            </div>

            <div class="flex flex-wrap items-end gap-3 mb-4 rounded-xl border bg-card p-4 shadow-sm">
                <div class="grid gap-1">
                    <FormLabel>Buscar aluno (nome/CPF)</FormLabel>
                    <Input v-model="search" placeholder="Digite..." class="w-64" />
                </div>
                <div class="grid gap-1">
                    <FormLabel>Tipo</FormLabel>
                    <select v-model="tipo" class="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        <option v-for="t in tipos" :key="t.tmv_cod" :value="t.tmv_cod">{{ t.tmv_descricao }}</option>
                    </select>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/30 text-left">
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Aluno</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Origem</th>
                            <th class="px-4 py-3">Destino</th>
                            <th class="px-4 py-3">Protocolo</th>
                            <th class="px-4 py-3">Usuário</th>
                            <th class="w-12 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="movimentacoes.data.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">Nenhuma movimentação encontrada.</td>
                        </tr>
                        <tr v-for="mv in movimentacoes.data" :key="mv.mva_id" class="border-b hover:bg-muted/20">
                            <td class="px-4 py-3">{{ formatDate(mv.mva_dt_movimentacao) }}</td>
                            <td class="px-4 py-3 font-medium">{{ mv.aluno?.aln_nome ?? '—' }}</td>
                            <td class="px-4 py-3">{{ mv.tipo?.tmv_descricao ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs">
                                <div>{{ mv.matricula_origem?.turma?.escola?.esc_nome ?? '—' }}</div>
                                <div class="text-muted-foreground">{{ mv.matricula_origem?.turma?.tur_nome ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <template v-if="mv.matricula_destino">
                                    <div>{{ mv.matricula_destino.turma?.escola?.esc_nome ?? '—' }}</div>
                                    <div class="text-muted-foreground">{{ mv.matricula_destino.turma?.tur_nome ?? '' }}</div>
                                </template>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3 text-xs">{{ mv.mva_protocolo ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs">{{ mv.user?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <Link :href="`/movimentacoes/${mv.mva_id}`" class="rounded p-1.5 hover:bg-muted inline-flex">
                                    <Eye class="size-4 text-indigo-600" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
