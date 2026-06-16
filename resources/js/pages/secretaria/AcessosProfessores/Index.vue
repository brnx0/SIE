<script setup lang="ts">
import ExportMenu from '@/components/common/ExportMenu.vue';
import PerPageSelect from '@/components/common/PerPageSelect.vue';
import RefreshButton from '@/components/common/RefreshButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { KeyRound, Search, UserPlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface LinhaProfessor {
    fun_id: number;
    fun_nome: string;
    fun_cpf: string | null;
    fun_email: string | null;
    escolas: string[];
    roles: string[];
    tem_login: boolean;
    pode_gerar: boolean;
    motivo: string;
    motivo_label: string;
}

const props = defineProps<{
    professores: Paginated<LinhaProfessor>;
    escolas: { esc_id: number; esc_nome: string }[];
    filters: { search: string; escola: number | null; status: string; per_page: number };
    semVinculo: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Acessos de Professores', href: '/secretaria/acessos-professores' }];

const search  = ref(props.filters.search ?? '');
const escola  = ref<number | ''>(props.filters.escola ?? '');
const status  = ref(props.filters.status ?? '');
const perPage = ref(props.filters.per_page ?? 10);

let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (resetPage = false) => {
    const params: Record<string, string | number> = {
        search: search.value,
        status: status.value,
        per_page: perPage.value,
    };
    if (escola.value) params.escola = escola.value;
    if (!resetPage) params.page = props.professores.current_page;
    router.get('/secretaria/acessos-professores', params, { preserveState: true, replace: true });
};
watch(search, () => { if (timer) clearTimeout(timer); timer = setTimeout(() => apply(true), 300); });
watch([escola, status, perPage], () => apply(true));

const STATUS_OPCOES = [
    { value: '', label: 'Todas as situações' },
    { value: 'sem_login', label: 'Sem login' },
    { value: 'com_login', label: 'Com login' },
    { value: 'nao_lotado', label: 'Não lotado' },
];

// ── Seleção ─────────────────────────────────────────────────────────────────
const selecionados = ref<Set<number>>(new Set());
const elegiveisNaPagina = computed(() => props.professores.data.filter((p) => p.pode_gerar).map((p) => p.fun_id));
const todosMarcados = computed(() =>
    elegiveisNaPagina.value.length > 0 && elegiveisNaPagina.value.every((id) => selecionados.value.has(id)),
);

function toggle(id: number) {
    const s = new Set(selecionados.value);
    s.has(id) ? s.delete(id) : s.add(id);
    selecionados.value = s;
}
function toggleTodos() {
    const s = new Set(selecionados.value);
    if (todosMarcados.value) {
        elegiveisNaPagina.value.forEach((id) => s.delete(id));
    } else {
        elegiveisNaPagina.value.forEach((id) => s.add(id));
    }
    selecionados.value = s;
}

// ── Geração ─────────────────────────────────────────────────────────────────
const enviando = ref(false);
function gerar(funIds: number[]) {
    if (funIds.length === 0 || enviando.value) return;
    if (!confirm(`Gerar acesso para ${funIds.length} professor(es)? Login e senha serão o CPF.`)) return;
    enviando.value = true;
    router.post('/secretaria/acessos-professores/gerar', { fun_ids: funIds }, {
        preserveScroll: true,
        onFinish: () => { enviando.value = false; selecionados.value = new Set(); },
    });
}

const roleLabel = (r: string) => (r === 'professor_aee' ? 'Professor AEE' : 'Professor');
const roleClass = (r: string) =>
    r === 'professor_aee'
        ? 'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-300'
        : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300';

const motivoClass = (m: string) => {
    if (m === 'ok') return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
    if (m === 'com_login') return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300';
    return 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
};
</script>

<template>
    <Head title="Acessos de Professores" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-[95%] flex-col gap-4 p-4 md:p-6">

            <!-- Cabeçalho -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">
                        Acessos de Professores
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Gere logins de acesso para professores das suas escolas. Login e senha iniciais são o CPF.
                    </p>
                </div>
                <Button
                    type="button"
                    class="bg-indigo-600 hover:bg-indigo-700"
                    :disabled="selecionados.size === 0 || enviando"
                    @click="gerar([...selecionados])"
                >
                    <KeyRound class="mr-2 size-4" />
                    Gerar selecionados ({{ selecionados.size }})
                </Button>
            </div>

            <!-- Sem vínculo -->
            <div
                v-if="semVinculo"
                class="rounded-xl border border-amber-200 bg-amber-50 px-6 py-8 text-center text-amber-800 shadow-sm dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
            >
                Seu usuário não está vinculado a um funcionário com lotação ativa. Sem escola vinculada não é possível listar professores.
            </div>

            <template v-else>
                <!-- Filtros -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative flex-1 min-w-[220px]">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" placeholder="Buscar por nome ou CPF..." class="pl-9" />
                    </div>
                    <select v-model="escola" class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                        <option value="">Todas as escolas</option>
                        <option v-for="e in escolas" :key="e.esc_id" :value="e.esc_id">{{ e.esc_nome }}</option>
                    </select>
                    <select v-model="status" class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                        <option v-for="o in STATUS_OPCOES" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                    <div class="ml-auto flex items-center gap-3">
                        <RefreshButton />
                        <PerPageSelect v-model="perPage" />
                        <ExportMenu base-url="/secretaria/acessos-professores/export" :filters="{ search, escola, status }" />
                    </div>
                </div>

                <!-- Tabela -->
                <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="w-10 px-4 py-3">
                                    <input type="checkbox" :checked="todosMarcados" :disabled="elegiveisNaPagina.length === 0" @change="toggleTodos" />
                                </th>
                                <th class="px-4 py-3">Nome</th>
                                <th class="px-4 py-3">CPF</th>
                                <th class="px-4 py-3">Escola(s)</th>
                                <th class="px-4 py-3">Perfil sugerido</th>
                                <th class="px-4 py-3">Situação</th>
                                <th class="px-4 py-3 text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="professores.data.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">Nenhum professor encontrado.</td>
                            </tr>
                            <tr v-for="p in professores.data" :key="p.fun_id" class="transition-colors hover:bg-muted/30">
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="selecionados.has(p.fun_id)"
                                        :disabled="!p.pode_gerar"
                                        @change="toggle(p.fun_id)"
                                    />
                                </td>
                                <td class="px-4 py-3 font-medium">{{ p.fun_nome }}</td>
                                <td class="px-4 py-3 font-mono text-muted-foreground">{{ p.fun_cpf || '—' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <span v-if="p.escolas.length">{{ p.escolas.join(', ') }}</span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="r in p.roles" :key="r" :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', roleClass(r)]">
                                            {{ roleLabel(r) }}
                                        </span>
                                        <span v-if="!p.roles.length" class="text-xs text-muted-foreground">—</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', motivoClass(p.motivo)]">
                                        {{ p.motivo_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button
                                        v-if="p.pode_gerar"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="enviando"
                                        class="border-indigo-200 text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-300"
                                        @click="gerar([p.fun_id])"
                                    >
                                        <UserPlus class="mr-1.5 size-4" /> Criar
                                    </Button>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-sm">
                        <span class="text-muted-foreground">
                            <template v-if="professores.total > 0">{{ professores.from }}–{{ professores.to }} de {{ professores.total }} professor{{ professores.total !== 1 ? 'es' : '' }}</template>
                            <template v-else>Nenhum registro</template>
                        </span>
                        <div v-if="professores.last_page > 1" class="flex flex-wrap gap-1">
                            <component
                                :is="link.url ? 'a' : 'span'"
                                v-for="(link, i) in professores.links"
                                :key="i"
                                :href="link.url ?? undefined"
                                v-html="link.label"
                                :class="['rounded-md px-3 py-1 text-xs', link.active ? 'bg-indigo-600 text-white' : 'border bg-background hover:bg-muted', !link.url && 'pointer-events-none opacity-40']"
                                @click.prevent="link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })"
                            />
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
