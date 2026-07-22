<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface OcorrenciaRel {
    toc_id: number;
    tdb_id: number;
    toc_tipo: string;
    toc_tipo_label: string;
    toc_hr: string | null;
    toc_descricao: string;
    data: string | null;
    rota: string | null;
    veiculo: string | null;
    motorista: string | null;
}

const props = defineProps<{
    ocorrencias: OcorrenciaRel[];
    resumo: { total: number; por_tipo: { tipo: string; label: string; total: number }[] };
    filtros: { dt_inicio: string | null; dt_fim: string | null; rot_id: number | null; tipo: string | null };
    tipos: Record<string, string>;
    rotas: { rot_id: number; rot_nome: string }[];
}>();

const dtInicio = ref(props.filtros.dt_inicio ?? '');
const dtFim    = ref(props.filtros.dt_fim ?? '');
const rotId    = ref<number | null>(props.filtros.rot_id ?? null);
const tipo     = ref(props.filtros.tipo ?? '');

const apply = () => {
    router.get('/transporte/relatorios/ocorrencias', {
        dt_inicio: dtInicio.value,
        dt_fim: dtFim.value,
        rot_id: rotId.value ?? '',
        tipo: tipo.value,
    }, { preserveState: true, replace: true });
};
watch([dtInicio, dtFim, rotId, tipo], apply);

const limpar = () => { dtInicio.value = ''; dtFim.value = ''; rotId.value = null; tipo.value = ''; };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Ocorrências do Transporte', href: '/transporte/relatorios/ocorrencias' }];

const fmt = (d: string | null) => (d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '—');
const selectClass = 'h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-background dark:text-foreground';
</script>

<template>
    <Head title="Ocorrências do Transporte" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Ocorrências do Transporte</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Ocorrências registradas nos diários de bordo — por período, rota e tipo.</p>
            </div>

            <!-- Resumo -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <AlertTriangle class="size-6 text-amber-500" />
                    <div><div class="text-2xl font-bold tabular-nums">{{ resumo.total }}</div><div class="text-xs text-muted-foreground">Ocorrências</div></div>
                </div>
                <span v-for="t in resumo.por_tipo" :key="t.tipo" class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-3 py-1.5 text-xs font-medium text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                    {{ t.label }} <strong class="tabular-nums">{{ t.total }}</strong>
                </span>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1.5"><FormLabel>De</FormLabel><Input v-model="dtInicio" type="date" class="w-40" /></div>
                <div class="grid gap-1.5"><FormLabel>Até</FormLabel><Input v-model="dtFim" type="date" class="w-40" /></div>
                <div class="grid min-w-[12rem] gap-1.5">
                    <FormLabel>Rota</FormLabel>
                    <select v-model="rotId" :class="selectClass">
                        <option :value="null">Todas as rotas</option>
                        <option v-for="r in rotas" :key="r.rot_id" :value="r.rot_id">{{ r.rot_nome }}</option>
                    </select>
                </div>
                <div class="grid min-w-[10rem] gap-1.5">
                    <FormLabel>Tipo</FormLabel>
                    <select v-model="tipo" :class="selectClass">
                        <option value="">Todos os tipos</option>
                        <option v-for="(label, key) in tipos" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <Button type="button" variant="outline" size="sm" @click="limpar">Limpar</Button>
                <div class="ml-auto flex items-center gap-3">
                    <ExportMenu base-url="/transporte/relatorios/ocorrencias/export" :filters="{ dt_inicio: dtInicio, dt_fim: dtFim, rot_id: rotId, tipo }" />
                    <RefreshButton />
                </div>
            </div>

            <!-- Tabela -->
            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Hora</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Rota</th>
                            <th class="px-4 py-3">Veículo</th>
                            <th class="px-4 py-3">Descrição</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="ocorrencias.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">Nenhuma ocorrência no período/filtros.</td>
                        </tr>
                        <tr v-for="o in ocorrencias" :key="o.toc_id" class="border-b align-top transition-colors last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">{{ fmt(o.data) }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ o.toc_hr ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">{{ o.toc_tipo_label }}</span>
                            </td>
                            <td class="px-4 py-3">{{ o.rota ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ o.veiculo ?? '—' }}</td>
                            <td class="px-4 py-3 max-w-md whitespace-pre-wrap text-muted-foreground">{{ o.toc_descricao }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/transporte/diario-bordo/${o.tdb_id}/edit`" class="text-xs text-indigo-600 hover:underline">ver diário</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
