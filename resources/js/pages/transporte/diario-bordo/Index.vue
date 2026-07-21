<script setup lang="ts">
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import FormLabel from '@/components/common/FormLabel.vue';
import InputError from '@/components/common/InputError.vue';
import MotoristaCombobox from '@/components/transporte/MotoristaCombobox.vue';
import RotaCombobox from '@/components/transporte/RotaCombobox.vue';
import VeiculoCombobox from '@/components/transporte/VeiculoCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { ROTA_TURNOS, type DiarioBordoLista } from '@/types/transporte';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CalendarPlus, Pencil, Route as RouteIcon, Trash2, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    diarios: Paginated<DiarioBordoLista>;
    filters: { rot_id: number | null; vei_id: number | null; mot_id: number | null; dt_inicio: string | null; dt_fim: string | null; per_page: number };
}>();

const { isReadOnly } = usePermissao();

// ── Filtros ──────────────────────────────────────────────────────────────
const rotId    = ref<number | null>(props.filters.rot_id ?? null);
const veiId    = ref<number | null>(props.filters.vei_id ?? null);
const motId    = ref<number | null>(props.filters.mot_id ?? null);
const dtInicio = ref(props.filters.dt_inicio ?? '');
const dtFim    = ref(props.filters.dt_fim ?? '');
const perPage  = ref(props.filters.per_page ?? 10);

const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        rot_id: rotId.value ?? '',
        vei_id: veiId.value ?? '',
        mot_id: motId.value ?? '',
        dt_inicio: dtInicio.value,
        dt_fim: dtFim.value,
        per_page: perPage.value,
    };
    if (!resetPage) params.page = props.diarios.current_page;
    router.get('/transporte/diario-bordo', params, { preserveState: true, replace: true });
};
watch([rotId, veiId, motId, dtInicio, dtFim, perPage], () => apply(true));

const limparFiltros = () => {
    rotId.value = null; veiId.value = null; motId.value = null; dtInicio.value = ''; dtFim.value = '';
};

// ── Registrar viagem ───────────────────────────────────────────────────────
const registrar = useForm<{ tdb_rot_id: number | null; tdb_dt_viagem: string }>({
    tdb_rot_id: null,
    tdb_dt_viagem: new Date().toISOString().slice(0, 10),
});
const submitRegistrar = () => {
    registrar.post('/transporte/diario-bordo', { preserveScroll: true });
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Diário de Bordo', href: '/transporte/diario-bordo' }];

const kmPercorrido = (d: DiarioBordoLista) =>
    d.tdb_km_inicial != null && d.tdb_km_final != null ? (d.tdb_km_final - d.tdb_km_inicial).toFixed(1) : null;

const remove = (d: DiarioBordoLista) => {
    if (!confirm(`Remover o registro de ${d.rota?.rot_nome ?? 'rota'} em ${new Date(d.tdb_dt_viagem + 'T00:00:00').toLocaleDateString('pt-BR')}?`)) return;
    router.delete(`/transporte/diario-bordo/${d.tdb_id}`, { preserveScroll: true });
};

const fmtData = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('pt-BR');
</script>

<template>
    <Head title="Diário de Bordo" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Diário de Bordo</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Registro diário das viagens — chamada, quilometragem e ocorrências.</p>
            </div>

            <!-- Registrar viagem -->
            <div v-if="!isReadOnly" class="rounded-xl border bg-card p-5 shadow-sm">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold"><CalendarPlus class="size-4 text-indigo-600" /> Registrar viagem</h2>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="grid min-w-[16rem] flex-1 gap-1.5">
                        <FormLabel :required="true">Rota</FormLabel>
                        <RotaCombobox :model-value="registrar.tdb_rot_id" :invalid="!!registrar.errors.tdb_rot_id" @update:model-value="(v) => (registrar.tdb_rot_id = v)" />
                        <InputError :message="registrar.errors.tdb_rot_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <FormLabel :required="true">Data</FormLabel>
                        <Input v-model="registrar.tdb_dt_viagem" type="date" class="w-44" />
                        <InputError :message="registrar.errors.tdb_dt_viagem" />
                    </div>
                    <Button type="button" :disabled="registrar.processing || !registrar.tdb_rot_id" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="submitRegistrar">
                        Abrir registro
                    </Button>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">Cria o registro do dia com a chamada dos passageiros ativos, ou abre o já existente.</p>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid min-w-[14rem] gap-1.5">
                    <FormLabel>Rota</FormLabel>
                    <RotaCombobox :model-value="rotId" @update:model-value="(v) => (rotId = v)" placeholder="Todas as rotas" />
                </div>
                <div class="grid min-w-[12rem] gap-1.5">
                    <FormLabel>Veículo</FormLabel>
                    <VeiculoCombobox :model-value="veiId" @update:model-value="(v) => (veiId = v)" placeholder="Todos" />
                </div>
                <div class="grid min-w-[12rem] gap-1.5">
                    <FormLabel>Motorista</FormLabel>
                    <MotoristaCombobox :model-value="motId" @update:model-value="(v) => (motId = v)" placeholder="Todos" />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>De</FormLabel>
                    <Input v-model="dtInicio" type="date" class="w-40" />
                </div>
                <div class="grid gap-1.5">
                    <FormLabel>Até</FormLabel>
                    <Input v-model="dtFim" type="date" class="w-40" />
                </div>
                <Button type="button" variant="outline" size="sm" @click="limparFiltros">Limpar</Button>
                <div class="ml-auto flex items-center gap-3">
                    <RefreshButton />
                    <PerPageSelect v-model="perPage" />
                </div>
            </div>

            <!-- Tabela -->
            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Rota</th>
                            <th class="px-4 py-3">Veículo</th>
                            <th class="px-4 py-3">Motorista</th>
                            <th class="px-4 py-3 text-center">Km</th>
                            <th class="px-4 py-3 text-center">Embarcados</th>
                            <th class="px-4 py-3 text-center">Ocorrências</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="diarios.data.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">Nenhum registro no período.</td>
                        </tr>
                        <tr v-for="d in diarios.data" :key="d.tdb_id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">{{ fmtData(d.tdb_dt_viagem) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <RouteIcon class="size-4 text-indigo-500" />
                                    <div class="flex flex-col">
                                        <span>{{ d.rota?.rot_nome ?? '—' }}</span>
                                        <span class="text-xs text-muted-foreground">{{ d.rota ? (ROTA_TURNOS[d.rota.rot_turno] ?? d.rota.rot_turno) : '' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ d.veiculo?.vei_placa ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ d.motorista?.mot_nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="kmPercorrido(d)" class="tabular-nums">{{ kmPercorrido(d) }} km</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <Users class="size-3" /> {{ d.embarcados_count }}/{{ d.passageiros_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="d.ocorrencias_count > 0" class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                    <AlertTriangle class="size-3" /> {{ d.ocorrencias_count }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/transporte/diario-bordo/${d.tdb_id}/edit`">
                                        <Button variant="ghost" size="sm"><Pencil class="size-4" /></Button>
                                    </Link>
                                    <Button v-if="!isReadOnly" variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30" @click="remove(d)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                    <span class="text-muted-foreground">
                        <template v-if="diarios.total > 0">{{ diarios.from }}–{{ diarios.to }} de {{ diarios.total }} registro{{ diarios.total !== 1 ? 's' : '' }}</template>
                        <template v-else>Nenhum registro</template>
                    </span>
                    <div v-if="diarios.last_page > 1" class="flex flex-wrap gap-1">
                        <Link v-for="(link, i) in diarios.links" :key="i" :href="link.url ?? '#'" v-html="link.label" :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']" preserve-scroll />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
