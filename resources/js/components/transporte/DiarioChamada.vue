<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { ChamadaItem, DiarioBordo } from '@/types/transporte';
import { router } from '@inertiajs/vue3';
import { CheckCheck, GraduationCap, Loader2, RefreshCw, RotateCw, UserPlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    diario: DiarioBordo;
    chamada: ChamadaItem[];
    pendentesSync: number;
    bloqueado?: boolean;
}>();

type Linha = ChamadaItem & {};
const clone = (arr: ChamadaItem[]): Linha[] => arr.map((c) => ({ ...c }));

const linhas = ref<Linha[]>(clone(props.chamada));
// Re-sincroniza ao recarregar (refresh/sync altera props.chamada).
watch(() => props.chamada, (v) => { linhas.value = clone(v); });

// Agrupa por ponto (ordem).
const grupos = computed(() => {
    const map = new Map<string, { ponto: string; ordem: number; itens: Linha[] }>();
    for (const l of linhas.value) {
        const key = l.ponto ? String(l.ponto.pto_id) : 'sem';
        if (!map.has(key)) {
            map.set(key, { ponto: l.ponto?.pto_nome ?? 'Sem ponto', ordem: l.ponto?.pto_ordem ?? 999, itens: [] });
        }
        map.get(key)!.itens.push(l);
    }
    return [...map.values()].sort((a, b) => a.ordem - b.ordem);
});

const totalEmbarcados = computed(() => linhas.value.filter((l) => l.tdp_fl_embarcou).length);
const totalDesembarcados = computed(() => linhas.value.filter((l) => l.tdp_fl_desembarcou).length);

// N9: desmarcar embarque limpa o desembarque.
const onEmbarque = (l: Linha) => {
    if (!l.tdp_fl_embarcou) {
        l.tdp_fl_desembarcou = false;
        l.tdp_hr_desembarque = null;
    }
};

const marcarTodosEmbarque = () => {
    linhas.value.forEach((l) => { l.tdp_fl_embarcou = true; });
};

const processing = ref(false);
const salvar = () => {
    processing.value = true;
    router.put(`/transporte/diario-bordo/${props.diario.tdb_id}/chamada`, {
        itens: linhas.value.map((l) => ({
            tdp_id: l.tdp_id,
            fl_embarcou: l.tdp_fl_embarcou,
            fl_desembarcou: l.tdp_fl_desembarcou,
            hr_embarque: l.tdp_hr_embarque || null,
            hr_desembarque: l.tdp_hr_desembarque || null,
            obs: l.tdp_obs || null,
        })),
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { processing.value = false; },
    });
};

const syncing = ref(false);
const sincronizar = () => {
    syncing.value = true;
    router.put(`/transporte/diario-bordo/${props.diario.tdb_id}/sincronizar-chamada`, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            router.reload({ only: ['chamada', 'pendentesSync', 'flash'], preserveScroll: true, preserveState: true });
        },
        onFinish: () => { syncing.value = false; },
    });
};

const refreshing = ref(false);
const refresh = () => {
    refreshing.value = true;
    router.reload({
        only: ['chamada', 'pendentesSync', 'flash'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { refreshing.value = false; },
    });
};
</script>

<template>
    <div class="grid gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3 text-sm">
                <span class="font-semibold">Chamada</span>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                    {{ totalEmbarcados }} embarcado(s)
                </span>
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ totalDesembarcados }} desembarcado(s)
                </span>
            </div>
            <div class="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" :disabled="refreshing" title="Atualizar" @click="refresh">
                    <RefreshCw :class="['mr-1 size-4', refreshing && 'animate-spin']" /> Atualizar
                </Button>
                <Button v-if="!bloqueado && pendentesSync > 0" type="button" size="sm" variant="outline" :disabled="syncing" class="border-amber-400 text-amber-700 hover:bg-amber-50 dark:text-amber-300" @click="sincronizar">
                    <RotateCw :class="['mr-1 size-4', syncing && 'animate-spin']" /> Sincronizar chamada ({{ pendentesSync }})
                </Button>
                <Button v-if="!bloqueado" type="button" size="sm" variant="outline" @click="marcarTodosEmbarque">
                    <CheckCheck class="mr-1 size-4" /> Marcar todos embarcados
                </Button>
            </div>
        </div>

        <div v-if="linhas.length === 0" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
            Nenhum passageiro na chamada. Vincule passageiros à rota e use "Sincronizar chamada".
        </div>

        <div v-for="g in grupos" :key="g.ponto" class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="flex items-center gap-2 border-b bg-muted/40 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                <span class="grid size-5 place-items-center rounded-full bg-indigo-100 text-[10px] text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">{{ g.ordem }}</span>
                {{ g.ponto }}
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-2">Passageiro</th>
                        <th class="px-4 py-2 text-center">Embarcou</th>
                        <th class="px-4 py-2 text-center">Desembarcou</th>
                        <th class="px-4 py-2">Obs</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in g.itens" :key="l.tdp_id" class="border-b last:border-0 hover:bg-muted/20">
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center gap-1.5">
                                <GraduationCap v-if="l.tipo === 'aluno'" class="size-3.5 text-indigo-500" />
                                <UserPlus v-else class="size-3.5 text-fuchsia-500" />
                                {{ l.nome }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <input type="checkbox" v-model="l.tdp_fl_embarcou" :disabled="bloqueado" class="size-4 accent-indigo-600 disabled:opacity-40" @change="onEmbarque(l)" />
                        </td>
                        <td class="px-4 py-2 text-center">
                            <input type="checkbox" v-model="l.tdp_fl_desembarcou" :disabled="bloqueado || !l.tdp_fl_embarcou" class="size-4 accent-emerald-600 disabled:opacity-40" />
                        </td>
                        <td class="px-4 py-2">
                            <Input v-model="l.tdp_obs" maxlength="120" placeholder="—" class="h-8 text-xs" :disabled="bloqueado" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="linhas.length > 0 && !bloqueado" class="flex justify-end">
            <Button type="button" :disabled="processing" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="salvar">
                <Loader2 v-if="processing" class="mr-2 size-4 animate-spin" /> Gravar chamada
            </Button>
        </div>
    </div>
</template>
