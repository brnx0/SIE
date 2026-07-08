<script setup lang="ts">
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Database, Play, Trash2, Upload } from 'lucide-vue-next';

interface Lote {
    icl_id: number;
    icl_arquivo_nome: string;
    icl_anl_id: number;
    icl_versao_layout: number | null;
    icl_status: string;
    icl_total_linhas: number;
    icl_total_erros: number;
    icl_contadores: Record<string, number> | null;
    icl_mensagem: string | null;
    icl_created_at: string | null;
}

const props = defineProps<{
    lotes: Lote[];
    anosLetivos: { anl_id: number; anl_ano: number }[];
}>();

const form = useForm<{ arquivo: File | null; anl_id: number | null }>({
    arquivo: null,
    anl_id: props.anosLetivos[0]?.anl_id ?? null,
});

const onFile = (e: Event) => {
    form.arquivo = (e.target as HTMLInputElement).files?.[0] ?? null;
};

const enviar = () => {
    form.post('/censo/importacao', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('arquivo'),
    });
};

const comitar = (lote: Lote) => {
    if (!confirm(`Importar o lote #${lote.icl_id} para a base? Registros já existentes (por INEP) não são sobrescritos.`)) return;
    router.post(`/censo/importacao/${lote.icl_id}/commit`, {}, { preserveScroll: true });
};

const remover = (lote: Lote) => {
    if (!confirm(`Remover o lote #${lote.icl_id} e seu staging?`)) return;
    router.delete(`/censo/importacao/${lote.icl_id}`, { preserveScroll: true });
};

const badge = (s: string) => {
    switch (s) {
        case 'COMMITADO': return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
        case 'PARSEADO': return 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300';
        case 'PROCESSANDO':
        case 'COMMITANDO': return 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 'ERRO': return 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300';
        default: return 'bg-muted text-muted-foreground';
    }
};

const resumoContadores = (c: Record<string, number> | null) =>
    c ? Object.entries(c).map(([k, v]) => `${k}:${v}`).join('  ') : '—';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Importar Censo', href: '/censo/importacao' }];
</script>

<template>
    <Head title="Importar Censo" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-6 py-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Importar Censo Escolar</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Carga inicial da rede a partir do arquivo de migração do Educacenso (INEP). Reimportar não sobrescreve registros já existentes.
                </p>
            </div>

            <!-- Upload -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Arquivo (.txt)</label>
                        <input
                            type="file"
                            accept=".txt,text/plain"
                            @change="onFile"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200 dark:text-slate-300 dark:file:bg-slate-800"
                        />
                        <p v-if="form.errors.arquivo" class="mt-1 text-xs text-rose-600">{{ form.errors.arquivo }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Ano letivo destino</label>
                        <select v-model="form.anl_id" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option v-for="a in anosLetivos" :key="a.anl_id" :value="a.anl_id">{{ a.anl_ano }}</option>
                        </select>
                        <p v-if="form.errors.anl_id" class="mt-1 text-xs text-rose-600">{{ form.errors.anl_id }}</p>
                    </div>
                    <Button
                        :disabled="!form.arquivo || form.processing"
                        @click="enviar"
                        class="bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    >
                        <Upload class="mr-2 size-4" /> Enviar e processar
                    </Button>
                </div>
            </div>

            <!-- Lotes -->
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-slate-800 dark:text-slate-200">Lotes</h2>
                <RefreshButton />
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/50 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Arquivo</th>
                            <th class="px-4 py-3">Layout</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Linhas</th>
                            <th class="px-4 py-3">Registros</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        <tr v-for="lote in lotes" :key="lote.icl_id">
                            <td class="px-4 py-3 font-mono text-slate-500">{{ lote.icl_id }}</td>
                            <td class="px-4 py-3">{{ lote.icl_arquivo_nome }}</td>
                            <td class="px-4 py-3">{{ lote.icl_versao_layout ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="badge(lote.icl_status)">{{ lote.icl_status }}</span>
                                <span v-if="lote.icl_total_erros > 0" class="ml-2 text-xs text-rose-600">{{ lote.icl_total_erros }} erros</span>
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ lote.icl_total_linhas.toLocaleString('pt-BR') }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ resumoContadores(lote.icl_contadores) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Button
                                        v-if="lote.icl_status === 'PARSEADO'"
                                        size="sm"
                                        @click="comitar(lote)"
                                        class="bg-emerald-600 text-white hover:bg-emerald-700"
                                    >
                                        <Play class="mr-1 size-3.5" /> Importar
                                    </Button>
                                    <Button size="sm" variant="ghost" @click="remover(lote)" class="text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!lotes.length">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                <Database class="mx-auto mb-2 size-6 opacity-50" />
                                Nenhum lote ainda. Envie um arquivo de migração para começar.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="lotes.some(l => ['PROCESSANDO','COMMITANDO','PENDENTE'].includes(l.icl_status))" class="text-xs text-amber-600">
                Há lotes em processamento. Requer o worker de fila ativo (<code>php artisan queue:work</code>). Use Atualizar para acompanhar.
            </p>
        </div>
    </AppLayout>
</template>
