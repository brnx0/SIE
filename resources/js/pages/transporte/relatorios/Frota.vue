<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, Bus, CheckCircle2, Clock } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Status = 'vencido' | 'a_vencer' | 'em_dia' | 'sem_data';
interface Doc { data: string | null; status: Status }
interface VeiculoFrota {
    vei_id: number;
    vei_placa: string;
    vei_tipo: string;
    vei_fl_ativo: boolean;
    docs: { licenciamento: Doc; seguro: Doc; vistoria: Doc };
    situacao: Status;
}

const props = defineProps<{
    veiculos: VeiculoFrota[];
    resumo: { total: number; vencido: number; a_vencer: number; em_dia: number; sem_data: number };
    filtros: { dias: number; incluir_inativos: boolean };
}>();

const dias = ref(props.filtros.dias);
const incluirInativos = ref(props.filtros.incluir_inativos);

const apply = () => {
    router.get('/transporte/relatorios/frota', {
        dias: dias.value,
        incluir_inativos: incluirInativos.value ? 1 : 0,
    }, { preserveState: true, replace: true });
};
watch([dias, incluirInativos], apply);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Situação da Frota', href: '/transporte/relatorios/frota' }];

const rotulo: Record<Status, string> = { vencido: 'Vencido', a_vencer: 'A vencer', em_dia: 'Em dia', sem_data: 'Sem data' };
const badge: Record<Status, string> = {
    vencido:  'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    a_vencer: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    em_dia:   'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    sem_data: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};
const fmt = (d: string | null) => (d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '—');
</script>

<template>
    <Head title="Situação da Frota" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Situação da Frota</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Vencimento de licenciamento, seguro e vistoria dos veículos.</p>
            </div>

            <!-- Resumo -->
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="flex items-center gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <Bus class="size-8 text-indigo-500" />
                    <div><div class="text-2xl font-bold tabular-nums">{{ resumo.total }}</div><div class="text-xs text-muted-foreground">Veículos</div></div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <AlertTriangle class="size-8 text-rose-500" />
                    <div><div class="text-2xl font-bold tabular-nums text-rose-600">{{ resumo.vencido }}</div><div class="text-xs text-muted-foreground">Com documento vencido</div></div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <Clock class="size-8 text-amber-500" />
                    <div><div class="text-2xl font-bold tabular-nums text-amber-600">{{ resumo.a_vencer }}</div><div class="text-xs text-muted-foreground">A vencer ({{ filtros.dias }}d)</div></div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <CheckCircle2 class="size-8 text-emerald-500" />
                    <div><div class="text-2xl font-bold tabular-nums text-emerald-600">{{ resumo.em_dia }}</div><div class="text-xs text-muted-foreground">Em dia</div></div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1.5">
                    <FormLabel>Janela de vencimento (dias)</FormLabel>
                    <Input v-model.number="dias" type="number" min="1" max="365" step="1" class="w-40" />
                </div>
                <label class="flex items-center gap-2 pb-2 text-sm">
                    <input type="checkbox" v-model="incluirInativos" class="size-4 accent-indigo-600" />
                    Incluir veículos inativos
                </label>
                <div class="ml-auto flex items-center gap-3">
                    <ExportMenu base-url="/transporte/relatorios/frota/export" :filters="{ dias, incluir_inativos: incluirInativos ? 1 : 0 }" />
                    <RefreshButton />
                </div>
            </div>

            <!-- Tabela -->
            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-3">Placa</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3 text-center">Licenciamento</th>
                            <th class="px-4 py-3 text-center">Seguro</th>
                            <th class="px-4 py-3 text-center">Vistoria</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="veiculos.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">Nenhum veículo encontrado.</td>
                        </tr>
                        <tr v-for="v in veiculos" :key="v.vei_id" class="border-b transition-colors last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">
                                {{ v.vei_placa }}
                                <span v-if="!v.vei_fl_ativo" class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-500 dark:bg-slate-800 dark:text-slate-400">inativo</span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ v.vei_tipo }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', badge[v.docs.licenciamento.status]]" :title="rotulo[v.docs.licenciamento.status]">{{ fmt(v.docs.licenciamento.data) }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', badge[v.docs.seguro.status]]" :title="rotulo[v.docs.seguro.status]">{{ fmt(v.docs.seguro.data) }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', badge[v.docs.vistoria.status]]" :title="rotulo[v.docs.vistoria.status]">{{ fmt(v.docs.vistoria.data) }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold', badge[v.situacao]]">{{ rotulo[v.situacao] }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
