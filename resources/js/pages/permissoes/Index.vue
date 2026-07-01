<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { LoaderCircle, Save } from 'lucide-vue-next';
import { reactive, ref } from 'vue';

interface TelaRow {
    tla_id: number;
    tla_rota: string;
    tla_nome: string;
    tla_grupo: string | null;
    niveis: Record<string, number>;
}

const props = defineProps<{
    telas: TelaRow[];
    roles: Record<string, string>; // slug -> label
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Permissões de Telas', href: '/permissoes' }];

// Cópia reativa editável.
const linhas = reactive<TelaRow[]>(
    props.telas.map((t) => ({ ...t, niveis: { ...t.niveis } })),
);

const roleSlugs = Object.keys(props.roles);
const processing = ref(false);

const NIVEIS = [
    { v: 0, label: 'Sem acesso' },
    { v: 1, label: 'Ver + Editar' },
    { v: 2, label: 'Só visualizar' },
];

const corNivel = (n: number) =>
    n === 1
        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
        : n === 2
          ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
          : 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300';

// Aplica um nível em toda a coluna de uma role (atalho).
const aplicarColuna = (role: string, nivel: number) => {
    linhas.forEach((l) => {
        if (role !== 'admin') l.niveis[role] = nivel;
    });
};

const salvar = () => {
    processing.value = true;
    router.put(
        '/permissoes',
        { permissoes: linhas.map((l) => ({ tla_id: l.tla_id, niveis: l.niveis })) },
        {
            preserveScroll: true,
            onFinish: () => (processing.value = false),
        },
    );
};

// True na primeira linha de cada grupo (para renderizar cabeçalho do grupo).
const ehInicioGrupo = (i: number) => i === 0 || linhas[i].tla_grupo !== linhas[i - 1].tla_grupo;
</script>

<template>
    <Head title="Permissões de Telas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex w-[95%] flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Permissões de Telas</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Defina o acesso de cada perfil por tela: <strong>Sem acesso</strong>, <strong>Ver + Editar</strong> ou <strong>Só visualizar</strong>.
                    </p>
                </div>
                <Button
                    :disabled="processing"
                    class="bg-indigo-600 text-white hover:bg-indigo-700"
                    @click="salvar"
                >
                    <LoaderCircle v-if="processing" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    Salvar
                </Button>
            </div>

            <div class="overflow-auto rounded-xl border bg-card shadow-sm">
                <table class="min-w-full border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr class="bg-muted/60">
                            <th class="sticky left-0 z-10 border-b bg-muted/60 px-4 py-3 text-left font-semibold">Tela</th>
                            <th
                                v-for="slug in roleSlugs"
                                :key="slug"
                                class="border-b px-2 py-2 text-center align-bottom"
                            >
                                <div class="whitespace-nowrap text-xs font-semibold">{{ roles[slug] }}</div>
                                <select
                                    v-if="slug !== 'admin'"
                                    class="mt-1 rounded border bg-background px-1 py-0.5 text-[11px] text-muted-foreground"
                                    title="Aplicar a toda a coluna"
                                    @change="aplicarColuna(slug, Number(($event.target as HTMLSelectElement).value)); ($event.target as HTMLSelectElement).value = ''"
                                >
                                    <option value="">aplicar…</option>
                                    <option v-for="n in NIVEIS" :key="n.v" :value="n.v">{{ n.label }}</option>
                                </select>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(linha, i) in linhas" :key="linha.tla_id">
                            <tr v-if="ehInicioGrupo(i)" class="bg-slate-100/70 dark:bg-slate-800/40">
                                <td :colspan="roleSlugs.length + 1" class="px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-500">
                                    {{ linha.tla_grupo ?? '—' }}
                                </td>
                            </tr>
                            <tr class="hover:bg-muted/30">
                                <td class="sticky left-0 z-10 border-b bg-card px-4 py-2 hover:bg-muted/30">
                                    <div class="font-medium">{{ linha.tla_nome }}</div>
                                    <div class="font-mono text-[11px] text-muted-foreground">{{ linha.tla_rota }}</div>
                                </td>
                                <td v-for="slug in roleSlugs" :key="slug" class="border-b px-2 py-2 text-center">
                                    <span
                                        v-if="slug === 'admin'"
                                        class="inline-block rounded px-2 py-1 text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                                        title="Administrador sempre tem acesso total"
                                    >Total</span>
                                    <select
                                        v-else
                                        v-model.number="linha.niveis[slug]"
                                        :class="['rounded border-0 px-2 py-1 text-xs font-medium focus:ring-2 focus:ring-indigo-400', corNivel(linha.niveis[slug] ?? 0)]"
                                    >
                                        <option v-for="n in NIVEIS" :key="n.v" :value="n.v">{{ n.v }} — {{ n.label }}</option>
                                    </select>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
