<script setup lang="ts">
import FormLabel from '@/components/common/FormLabel.vue';
import MapaRota from '@/components/transporte/MapaRota.vue';
import RotaCombobox from '@/components/transporte/RotaCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissao } from '@/composables/usePermissao';
import { useGeoTransporte } from '@/composables/useGeoTransporte';
import { type BreadcrumbItem } from '@/types';
import type { EscolaGeo, GeoJsonLineString, SimuladorPonto, SimuladorRota } from '@/types/transporte';
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Check, Copy, ExternalLink, Loader2, MapPin, Navigation, Route as RouteIcon, Save, Search, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    anosLetivos: { anl_id: number; anl_ano: number }[];
    rota: SimuladorRota | null;
    escolas: EscolaGeo[];
}>();

const { isReadOnly } = usePermissao();
const { roteando, geocoding, geocode, rotear } = useGeoTransporte();

// ── Seleção de rota ────────────────────────────────────────────────────────
const rotIdSel = ref<number | null>(props.rota?.rot_id ?? null);
const selecionarRota = (id: number | null) => {
    rotIdSel.value = id;
    router.get('/transporte/simulador', id ? { rot_id: id } : {}, { preserveState: false });
};

// ── Estado da simulação (em memória) ───────────────────────────────────────
const pontos = ref<SimuladorPonto[]>(props.rota ? props.rota.pontos.map((p) => ({ ...p })) : []);
const trajeto = ref<GeoJsonLineString | null>(props.rota?.rot_trajeto ?? null);
const km = ref<number | null>(props.rota?.rot_km ?? null);
const tempoMin = ref<number | null>(props.rota?.rot_tempo_min ?? null);
const alterado = ref(false);

watch(() => props.rota, (r) => {
    pontos.value = r ? r.pontos.map((p) => ({ ...p })) : [];
    trajeto.value = r?.rot_trajeto ?? null;
    km.value = r?.rot_km ?? null;
    tempoMin.value = r?.rot_tempo_min ?? null;
    alterado.value = false;
});

const pontosComCoords = computed(() =>
    pontos.value.filter((p) => p.pto_latitude != null && p.pto_longitude != null),
);

// URL de direções do Google Maps com todos os pontos na ordem (origem → destino).
// Formato de caminho /maps/dir/lat,lng/lat,lng/... aceita vários paradas.
const googleMapsUrl = computed(() => {
    const pts = pontosComCoords.value;
    if (pts.length < 2) return null;
    const path = pts.map((p) => `${p.pto_latitude},${p.pto_longitude}`).join('/');
    return `https://www.google.com/maps/dir/${path}`;
});

const copiado = ref(false);
const copiarLink = async () => {
    if (!googleMapsUrl.value) return;
    try {
        await navigator.clipboard.writeText(googleMapsUrl.value);
        copiado.value = true;
        setTimeout(() => { copiado.value = false; }, 1800);
    } catch {
        // clipboard indisponível (ex.: contexto não seguro) — ignora silenciosamente
    }
};

const ocupacao = computed(() => {
    const cap = props.rota?.capacidade ?? null;
    const tot = props.rota?.total_passageiros ?? 0;
    return { cap, tot, excede: cap != null && cap > 0 && tot > cap };
});

const reordenarOrdens = () => {
    pontos.value.forEach((p, i) => { p.pto_ordem = i + 1; });
};

// ── Recalcular via OSRM ────────────────────────────────────────────────────
const recalcular = async () => {
    reordenarOrdens();
    const coords = pontosComCoords.value.map((p) => ({ lat: p.pto_latitude as number, lng: p.pto_longitude as number }));
    if (coords.length < 2) {
        trajeto.value = null;
        km.value = null;
        tempoMin.value = null;
        return;
    }
    const r = await rotear(coords);
    if (r) {
        km.value = r.distance_m != null ? Math.round((r.distance_m / 1000) * 10) / 10 : null;
        tempoMin.value = r.duration_s != null ? Math.round(r.duration_s / 60) : null;
        trajeto.value = r.geometry ?? null;
    }
};

// ── Interações do mapa ─────────────────────────────────────────────────────
const onMarkerDrag = (index: number, lat: number, lng: number) => {
    const p = pontos.value[index];
    if (!p) return;
    p.pto_latitude = Math.round(lat * 1e7) / 1e7;
    p.pto_longitude = Math.round(lng * 1e7) / 1e7;
    alterado.value = true;
    recalcular();
};

const onMapClick = (lat: number, lng: number) => {
    pontos.value.push({
        pto_id: null,
        pto_ordem: pontos.value.length + 1,
        pto_nome: `Novo ponto ${pontos.value.length + 1}`,
        pto_latitude: Math.round(lat * 1e7) / 1e7,
        pto_longitude: Math.round(lng * 1e7) / 1e7,
        escola: null,
    });
    alterado.value = true;
    recalcular();
};

const mover = (index: number, delta: number) => {
    const alvo = index + delta;
    if (alvo < 0 || alvo >= pontos.value.length) return;
    const arr = pontos.value;
    [arr[index], arr[alvo]] = [arr[alvo], arr[index]];
    alterado.value = true;
    recalcular();
};

// ── Buscar endereço → adiciona ponto ───────────────────────────────────────
const enderecoBusca = ref('');
const resultadosGeo = ref<{ nome: string; lat: number; lon: number }[]>([]);
const buscarEndereco = async () => {
    resultadosGeo.value = await geocode(enderecoBusca.value);
};
const adicionarDoEndereco = (r: { nome: string; lat: number; lon: number }) => {
    pontos.value.push({
        pto_id: null,
        pto_ordem: pontos.value.length + 1,
        pto_nome: r.nome.split(',').slice(0, 2).join(',').slice(0, 120) || `Ponto ${pontos.value.length + 1}`,
        pto_latitude: r.lat,
        pto_longitude: r.lon,
        escola: null,
    });
    resultadosGeo.value = [];
    enderecoBusca.value = '';
    alterado.value = true;
    recalcular();
};

// ── Efetivar ───────────────────────────────────────────────────────────────
const efetivando = ref(false);
const efetivar = () => {
    if (!props.rota) return;
    efetivando.value = true;
    router.put(`/transporte/simulador/${props.rota.rot_id}/efetivar`, {
        pontos: pontos.value.map((p) => ({
            pto_id: p.pto_id,
            pto_nome: p.pto_nome,
            pto_ordem: p.pto_ordem,
            pto_latitude: p.pto_latitude,
            pto_longitude: p.pto_longitude,
        })),
        rot_km: km.value,
        rot_tempo_min: tempoMin.value,
        rot_trajeto: trajeto.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { alterado.value = false; },
        onFinish: () => { efetivando.value = false; },
    });
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Simulador de Rotas', href: '/transporte/simulador' }];
</script>

<template>
    <Head title="Simulador de Rotas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Simulador de Rotas</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Posicione os pontos no mapa e calcule distância e tempo antes de efetivar.</p>
                </div>
                <div class="grid min-w-[18rem] gap-1.5">
                    <FormLabel>Rota</FormLabel>
                    <RotaCombobox
                        :model-value="rotIdSel"
                        :initial="rota ? { rot_id: rota.rot_id, rot_nome: rota.rot_nome } : null"
                        @update:model-value="selecionarRota"
                    />
                </div>
            </div>

            <div v-if="!rota" class="rounded-xl border-2 border-dashed bg-card py-16 text-center text-sm text-muted-foreground shadow-sm">
                Selecione uma rota para carregar seus pontos no mapa.
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <!-- Mapa -->
                <MapaRota
                    :pontos="pontos"
                    :escolas="escolas"
                    :trajeto="trajeto"
                    :editable="!isReadOnly"
                    height="620px"
                    @marker-drag="onMarkerDrag"
                    @map-click="onMapClick"
                />

                <!-- Painel -->
                <div class="flex flex-col gap-4">
                    <!-- Estimativas -->
                    <div class="grid grid-cols-3 gap-2 rounded-xl border bg-card p-4 shadow-sm">
                        <div class="text-center">
                            <div class="flex items-center justify-center gap-1 text-xs text-muted-foreground"><RouteIcon class="size-3" /> Distância</div>
                            <div class="mt-1 text-lg font-semibold tabular-nums">{{ km != null ? `${km} km` : '—' }}</div>
                        </div>
                        <div class="text-center">
                            <div class="flex items-center justify-center gap-1 text-xs text-muted-foreground"><Navigation class="size-3" /> Tempo</div>
                            <div class="mt-1 text-lg font-semibold tabular-nums">{{ tempoMin != null ? `${tempoMin} min` : '—' }}</div>
                        </div>
                        <div class="text-center">
                            <div class="flex items-center justify-center gap-1 text-xs text-muted-foreground"><Users class="size-3" /> Ocupação</div>
                            <div :class="['mt-1 text-lg font-semibold tabular-nums', ocupacao.excede ? 'text-rose-600' : '']">
                                {{ ocupacao.tot }}<template v-if="ocupacao.cap != null">/{{ ocupacao.cap }}</template>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button type="button" variant="outline" size="sm" :disabled="roteando" class="flex-1" @click="recalcular">
                            <Loader2 v-if="roteando" class="mr-1 size-4 animate-spin" /><Navigation v-else class="mr-1 size-4" /> Recalcular
                        </Button>
                        <Button v-if="!isReadOnly" type="button" size="sm" :disabled="efetivando || !alterado" class="flex-1 bg-indigo-600 text-white hover:bg-indigo-700" @click="efetivar">
                            <Loader2 v-if="efetivando" class="mr-1 size-4 animate-spin" /><Save v-else class="mr-1 size-4" /> Efetivar
                        </Button>
                    </div>
                    <div v-if="googleMapsUrl" class="flex items-center gap-2">
                        <a
                            :href="googleMapsUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-md border border-input bg-background px-3 text-sm font-medium shadow-sm transition hover:bg-muted"
                        >
                            <ExternalLink class="size-4" /> Abrir no Google Maps
                        </a>
                        <button
                            type="button"
                            title="Copiar link da rota"
                            class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-md border border-input bg-background px-3 text-sm font-medium shadow-sm transition hover:bg-muted"
                            @click="copiarLink"
                        >
                            <Check v-if="copiado" class="size-4 text-emerald-600" />
                            <Copy v-else class="size-4" />
                            {{ copiado ? 'Copiado!' : 'Copiar link' }}
                        </button>
                    </div>

                    <p v-if="!isReadOnly" class="text-xs text-muted-foreground">Clique no mapa para adicionar um ponto; arraste os marcadores para reposicionar. "Efetivar" grava pontos, trajeto e estimativas na rota.</p>

                    <!-- Buscar endereço -->
                    <div v-if="!isReadOnly" class="rounded-xl border bg-card p-3 shadow-sm">
                        <FormLabel class="mb-1.5">Adicionar por endereço</FormLabel>
                        <div class="flex items-center gap-2">
                            <div class="flex flex-1 items-center rounded-md border border-input bg-background px-2">
                                <Search class="size-4 text-muted-foreground" />
                                <input v-model="enderecoBusca" type="text" class="h-9 w-full bg-transparent px-2 text-sm focus:outline-none" placeholder="Rua, bairro, cidade..." @keyup.enter="buscarEndereco" />
                            </div>
                            <Button type="button" size="sm" variant="outline" :disabled="geocoding" @click="buscarEndereco">
                                <Loader2 v-if="geocoding" class="size-4 animate-spin" /><Search v-else class="size-4" />
                            </Button>
                        </div>
                        <ul v-if="resultadosGeo.length" class="mt-2 max-h-40 overflow-y-auto rounded-md border text-sm">
                            <li v-for="(r, i) in resultadosGeo" :key="i" class="cursor-pointer px-2 py-1.5 hover:bg-muted" @click="adicionarDoEndereco(r)">
                                {{ r.nome }}
                            </li>
                        </ul>
                    </div>

                    <!-- Lista de pontos -->
                    <div class="rounded-xl border bg-card shadow-sm">
                        <div class="border-b px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Pontos ({{ pontos.length }})</div>
                        <ul class="max-h-[22rem] divide-y overflow-y-auto">
                            <li v-if="pontos.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">Nenhum ponto. Clique no mapa ou busque um endereço.</li>
                            <li v-for="(p, i) in pontos" :key="p.pto_id ?? `novo-${i}`" class="flex items-center gap-2 px-3 py-2 text-sm">
                                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">{{ i + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">{{ p.pto_nome }}</div>
                                    <div class="flex items-center gap-1 text-xs" :class="p.pto_latitude != null ? 'text-emerald-600' : 'text-amber-600'">
                                        <MapPin class="size-3" />
                                        <template v-if="p.pto_latitude != null">{{ p.pto_latitude?.toFixed(5) }}, {{ p.pto_longitude?.toFixed(5) }}</template>
                                        <template v-else>Sem coordenada</template>
                                    </div>
                                </div>
                                <div v-if="!isReadOnly" class="flex shrink-0 flex-col">
                                    <button type="button" class="rounded p-0.5 hover:bg-muted disabled:opacity-30" :disabled="i === 0" title="Subir" @click="mover(i, -1)"><ArrowUp class="size-3.5" /></button>
                                    <button type="button" class="rounded p-0.5 hover:bg-muted disabled:opacity-30" :disabled="i === pontos.length - 1" title="Descer" @click="mover(i, 1)"><ArrowDown class="size-3.5" /></button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
