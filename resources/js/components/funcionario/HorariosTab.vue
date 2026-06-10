<script setup lang="ts">
import { Calendar } from 'lucide-vue-next';
import { computed } from 'vue';

interface HorarioItem {
    trh_id: number;
    matricula: string | null;
    turma: string | null;
    escola: string | null;
    dia: string;
    dia_cod: string;
    horario: string | null;
    disciplina: string | null;
    tempo: number;
    turno: string | null;
}

const props = defineProps<{ horarios: HorarioItem[] }>();

const DIA_ORDEM: Record<string, number> = { dom: 0, seg: 1, ter: 2, qua: 3, qui: 4, sex: 5, sab: 6 };
const TURNOS = ['MATUTINO', 'VESPERTINO', 'NOTURNO', 'INTEGRAL'] as const;
const TURNO_LABEL: Record<string, string> = {
    MATUTINO: 'Matutino', VESPERTINO: 'Vespertino', NOTURNO: 'Noturno', INTEGRAL: 'Integral',
};

const grupos = computed(() =>
    TURNOS.map(turno => ({
        turno,
        label: TURNO_LABEL[turno],
        linhas: [...props.horarios]
            .filter(h => (h.turno ?? '').toUpperCase() === turno)
            .sort((a, b) => {
                const d = (DIA_ORDEM[a.dia_cod] ?? 99) - (DIA_ORDEM[b.dia_cod] ?? 99);
                return d !== 0 ? d : a.tempo - b.tempo;
            }),
    })),
);
</script>

<template>
    <div class="grid gap-6">
        <div class="flex items-center gap-2">
            <Calendar class="size-5 text-indigo-600" />
            <h3 class="text-sm font-semibold">Horários do Professor — Ano Letivo em Exercício</h3>
        </div>

        <div v-if="!horarios.length" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
            Nenhum horário alocado para este professor no ano vigente.
        </div>

        <template v-else>
            <div
                v-for="g in grupos"
                :key="g.turno"
                v-show="g.linhas.length > 0"
                class="overflow-hidden rounded-xl border bg-card shadow-sm"
            >
                <div class="flex items-center justify-between gap-2 border-b border-l-4 px-4 py-2.5"
                    :class="{
                        'border-l-amber-500  bg-amber-50/60  dark:bg-amber-900/10':  g.turno === 'MATUTINO',
                        'border-l-orange-500 bg-orange-50/60 dark:bg-orange-900/10': g.turno === 'VESPERTINO',
                        'border-l-indigo-500 bg-indigo-50/60 dark:bg-indigo-900/10': g.turno === 'NOTURNO',
                        'border-l-emerald-500 bg-emerald-50/60 dark:bg-emerald-900/10': g.turno === 'INTEGRAL',
                    }"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold">{{ g.label }}</span>
                        <span class="rounded-full bg-slate-700 px-2 py-0.5 text-xs font-medium text-white dark:bg-slate-300 dark:text-slate-900">
                            {{ g.linhas.length }} aula(s)
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Matrícula</th>
                                <th class="px-3 py-2 text-left font-semibold">Turma</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Dia</th>
                                <th class="px-3 py-2 text-left font-semibold whitespace-nowrap">Horário</th>
                                <th class="px-3 py-2 text-left font-semibold">Disciplina</th>
                                <th class="px-3 py-2 text-center font-semibold whitespace-nowrap">Tempo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(l, i) in g.linhas" :key="l.trh_id" :class="i % 2 === 0 ? 'bg-white dark:bg-transparent' : 'bg-slate-50 dark:bg-slate-900/40'">
                                <td class="px-3 py-2 text-xs tabular-nums">{{ l.matricula ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs">
                                    <div class="font-medium">{{ l.turma ?? '—' }}</div>
                                    <div v-if="l.escola" class="text-[10px] text-muted-foreground">{{ l.escola }}</div>
                                </td>
                                <td class="px-3 py-2 text-xs">{{ l.dia }}</td>
                                <td class="px-3 py-2 text-xs tabular-nums">{{ l.horario ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs">{{ l.disciplina ?? '—' }}</td>
                                <td class="px-3 py-2 text-center font-semibold tabular-nums">{{ l.tempo }}º</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="grupos.every(g => g.linhas.length === 0)" class="rounded-xl border-2 border-dashed bg-card py-12 text-center text-sm text-muted-foreground shadow-sm">
                Nenhum horário alocado.
            </div>
        </template>
    </div>
</template>
