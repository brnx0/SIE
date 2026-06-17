<script setup lang="ts">
import { DIAS_SEMANA } from '@/types/turma';
import { CalendarClock, CalendarDays, Loader2, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface SlotHorario {
    trh_id: number;
    trh_tempo: number;
    trh_dia: string;
    trh_hora: string | null;
    trh_fl_tc: boolean;
    funcionario?: { fun_id: number; fun_nome: string } | null;
    disciplina?: { dis_id: number; dis_nome: string } | null;
}
interface GradeHorario {
    grh_id: number;
    grh_hora: string;
    grh_ordem: number;
}
interface SabadoLetivo {
    sbl_id: number;
    sbl_dt_sabado: string;
    sbl_dia_semana: number; // 1=Seg..5=Sex
}
interface TurmaGrade {
    tur_id: number;
    tur_nome: string;
    tur_turno: string;
    tur_dias_funcionamento: string[] | null;
    serie?: { ser_id: number; ser_nome: string } | null;
    escola?: { esc_id: number; esc_nome: string } | null;
}

const props = defineProps<{ turId: number }>();

const carregando = ref(true);
const erro = ref<string | null>(null);
const turma = ref<TurmaGrade | null>(null);
const horarios = ref<SlotHorario[]>([]);
const gradeHorarios = ref<GradeHorario[]>([]);
const sabadosLetivos = ref<SabadoLetivo[]>([]);

const carregar = async () => {
    carregando.value = true;
    erro.value = null;
    try {
        const url = new URL('/api/diario/quadro-horario/grade', window.location.origin);
        url.searchParams.set('tur_id', String(props.turId));
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!r.ok) {
            erro.value = 'Não foi possível carregar o quadro de horário.';
            return;
        }
        const json = await r.json();
        turma.value = json.turma;
        horarios.value = json.horarios ?? [];
        gradeHorarios.value = json.gradeHorarios ?? [];
        sabadosLetivos.value = json.sabadosLetivos ?? [];
    } catch {
        erro.value = 'Falha de comunicação ao carregar o quadro.';
    } finally {
        carregando.value = false;
    }
};

onMounted(carregar);
watch(() => props.turId, carregar);

// Dias ativos da turma (na ordem da semana).
const diasAtivos = computed(() =>
    DIAS_SEMANA.filter((d) => turma.value?.tur_dias_funcionamento?.includes(d.value as any)),
);

// Sábado letivo espelha um dia útil: 1=Seg..5=Sex → código trh_dia.
const ESPELHO: Record<number, string> = { 1: 'seg', 2: 'ter', 3: 'qua', 4: 'qui', 5: 'sex' };
const fmtDdMm = (iso: string) => {
    const [, m, d] = iso.substring(0, 10).split('-');
    return `${d}/${m}`;
};
const labelDia = (code: string) => DIAS_SEMANA.find((d) => d.value === code)?.label ?? code;

// Sábado da semana atualmente consultada (próximo sábado ≥ hoje; hoje se já for sábado).
const sabadoDaSemana = (): string => {
    const d = new Date();
    d.setDate(d.getDate() + ((6 - d.getDay() + 7) % 7));
    const p = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
};

// Coluna extra: somente o sábado letivo desta semana, e se espelha um dia ativo. Máx 1.
const colunasSabado = computed(() => {
    const alvo = sabadoDaSemana();
    const s = sabadosLetivos.value.find((x) => x.sbl_dt_sabado.substring(0, 10) === alvo);
    if (!s) return [];
    const code = ESPELHO[s.sbl_dia_semana];
    if (!code || !diasAtivos.value.some((d) => d.value === code)) return [];
    return [{ sbl_id: s.sbl_id, data: fmtDdMm(s.sbl_dt_sabado), code, espelha: labelDia(code) }];
});

// Quantidade de tempos: maior entre a grade cadastrada e o maior tempo alocado.
const totalTempos = computed(() => {
    const maxAloc = horarios.value.reduce((m, h) => Math.max(m, h.trh_tempo ?? 0), 0);
    return Math.max(gradeHorarios.value.length, maxAloc);
});
const tempos = computed(() => Array.from({ length: totalTempos.value }, (_, i) => i + 1));

// `${dia}:${tempo}` → slot
const mapa = computed(() => {
    const m = new Map<string, SlotHorario>();
    for (const h of horarios.value) m.set(`${h.trh_dia}:${h.trh_tempo}`, h);
    return m;
});

const horaDoTempo = (tempo: number): string | null => {
    const slotHora = horarios.value.find((h) => h.trh_tempo === tempo && h.trh_hora)?.trh_hora;
    const gradeHora = gradeHorarios.value[tempo - 1]?.grh_hora;
    return (slotHora ?? gradeHora ?? '')?.substring(0, 5) || null;
};

const subtitulo = computed(() => {
    if (!turma.value) return '';
    const partes = [
        turma.value.serie?.ser_nome,
        turma.value.tur_nome,
        turma.value.escola?.esc_nome,
    ].filter(Boolean);
    return partes.join(' · ');
});

const vazio = computed(() => !carregando.value && !erro.value && horarios.value.length === 0);
</script>

<template>
    <section class="rounded-xl border bg-card shadow-sm">
        <!-- Cabeçalho -->
        <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
            <div class="flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950">
                    <CalendarClock class="size-5" />
                </span>
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Quadro de Horário</h2>
                    <p v-if="subtitulo" class="text-xs text-muted-foreground">{{ subtitulo }}</p>
                </div>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md border border-input px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted"
                :disabled="carregando"
                @click="carregar"
            >
                <RefreshCw :class="['size-3.5', carregando && 'animate-spin']" /> Atualizar
            </button>
        </div>

        <div class="p-4">
            <!-- Carregando -->
            <div v-if="carregando" class="flex items-center justify-center gap-2 py-12 text-sm text-muted-foreground">
                <Loader2 class="size-4 animate-spin" /> Carregando quadro...
            </div>

            <!-- Erro -->
            <div v-else-if="erro" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-8 text-center text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                {{ erro }}
            </div>

            <!-- Sem dias ou sem grade -->
            <div v-else-if="diasAtivos.length === 0 || totalTempos === 0" class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                Esta turma não possui dias de funcionamento ou grade de horário configurada.
            </div>

            <!-- Matriz tempos × dias -->
            <div v-else class="overflow-x-auto">
                <table class="w-full border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 w-24 rounded-tl-lg border-b border-r bg-muted/60 px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                Tempo
                            </th>
                            <th
                                v-for="dia in diasAtivos"
                                :key="dia.value"
                                class="border-b border-r bg-muted/60 px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-muted-foreground last:rounded-tr-lg"
                            >
                                {{ dia.label }}
                            </th>
                            <!-- Sábados letivos (espelham um dia útil) -->
                            <th
                                v-for="sab in colunasSabado"
                                :key="`sh-${sab.sbl_id}`"
                                class="border-b border-r border-amber-200 bg-amber-100/70 px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-amber-800 last:rounded-tr-lg dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                            >
                                <div class="flex items-center justify-center gap-1">
                                    <CalendarDays class="size-3.5" /> Sáb {{ sab.data }}
                                </div>
                                <div class="mt-0.5 text-[10px] font-normal normal-case opacity-80">espelha {{ sab.espelha }}</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="tempo in tempos" :key="tempo" class="group">
                            <!-- Coluna tempo -->
                            <td class="sticky left-0 z-10 border-b border-r bg-card px-3 py-2 align-top">
                                <div class="font-semibold text-slate-700 dark:text-slate-200">{{ tempo }}º</div>
                                <div v-if="horaDoTempo(tempo)" class="text-xs tabular-nums text-indigo-600 dark:text-indigo-400">
                                    {{ horaDoTempo(tempo) }}
                                </div>
                            </td>

                            <!-- Células por dia -->
                            <td
                                v-for="dia in diasAtivos"
                                :key="dia.value"
                                class="border-b border-r px-3 py-2 align-top"
                            >
                                <template v-if="mapa.has(`${dia.value}:${tempo}`)">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-medium leading-tight text-slate-900 dark:text-slate-100">
                                            {{ mapa.get(`${dia.value}:${tempo}`)?.disciplina?.dis_nome ?? '—' }}
                                        </span>
                                        <span class="text-xs leading-tight text-muted-foreground">
                                            {{ mapa.get(`${dia.value}:${tempo}`)?.funcionario?.fun_nome ?? '—' }}
                                        </span>
                                        <span
                                            v-if="mapa.get(`${dia.value}:${tempo}`)?.trh_fl_tc"
                                            class="mt-0.5 inline-flex w-fit rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700 dark:bg-sky-900/40 dark:text-sky-300"
                                        >TC</span>
                                    </div>
                                </template>
                                <span v-else class="text-muted-foreground/40">·</span>
                            </td>

                            <!-- Células de sábado letivo: espelham o dia útil referenciado -->
                            <td
                                v-for="sab in colunasSabado"
                                :key="`sc-${sab.sbl_id}`"
                                class="border-b border-r border-amber-200 bg-amber-50/60 px-3 py-2 align-top dark:border-amber-900/60 dark:bg-amber-950/20"
                            >
                                <template v-if="mapa.has(`${sab.code}:${tempo}`)">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-medium leading-tight text-amber-900 dark:text-amber-200">
                                            {{ mapa.get(`${sab.code}:${tempo}`)?.disciplina?.dis_nome ?? '—' }}
                                        </span>
                                        <span class="text-xs leading-tight text-amber-700/80 dark:text-amber-300/70">
                                            {{ mapa.get(`${sab.code}:${tempo}`)?.funcionario?.fun_nome ?? '—' }}
                                        </span>
                                        <span
                                            v-if="mapa.get(`${sab.code}:${tempo}`)?.trh_fl_tc"
                                            class="mt-0.5 inline-flex w-fit rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700 dark:bg-sky-900/40 dark:text-sky-300"
                                        >TC</span>
                                    </div>
                                </template>
                                <span v-else class="text-amber-400/40">·</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="colunasSabado.length" class="mt-3 flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                    <span class="inline-block size-3 rounded-sm border border-amber-200 bg-amber-100 dark:border-amber-900 dark:bg-amber-950/40"></span>
                    Colunas em âmbar são sábados letivos, espelhando o horário do dia da semana indicado.
                </p>
                <p v-if="vazio" class="mt-3 text-center text-xs text-muted-foreground">
                    Nenhum horário alocado para esta turma.
                </p>
            </div>
        </div>
    </section>
</template>
