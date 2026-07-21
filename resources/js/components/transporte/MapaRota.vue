<script setup lang="ts">
import type { GeoJsonLineString, InstituicaoGeo, SimuladorPonto } from '@/types/transporte';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{
    pontos: SimuladorPonto[];
    instituicoes?: InstituicaoGeo[];
    trajeto?: GeoJsonLineString | null;
    editable?: boolean;
    height?: string;
}>(), {
    instituicoes: () => [],
    trajeto: null,
    editable: false,
    height: '520px',
});

const emit = defineEmits<{
    (e: 'marker-drag', index: number, lat: number, lng: number): void;
    (e: 'map-click', lat: number, lng: number): void;
}>();

// Centro padrão (Fortaleza/CE) quando não há pontos georreferenciados.
const CENTRO_PADRAO: [number, number] = [-3.7319, -38.5267];

const el = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let pontosLayer: L.LayerGroup | null = null;
let instLayer: L.LayerGroup | null = null;
let trajetoLayer: L.Polyline | null = null;
let resizeObs: ResizeObserver | null = null;

const numberedIcon = (n: number) =>
    L.divIcon({
        className: 'mapa-rota-pin',
        html: `<div style="background:#4f46e5;color:#fff;width:28px;height:28px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.4);border:2px solid #fff"><span style="transform:rotate(45deg);font-size:12px;font-weight:700">${n}</span></div>`,
        iconSize: [28, 28],
        iconAnchor: [14, 28],
    });

const instIcon = () =>
    L.divIcon({
        className: 'mapa-inst-pin',
        html: `<div style="background:#64748b;color:#fff;width:18px;height:18px;border-radius:4px;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 3px rgba(0,0,0,.4);border:1px solid #fff;font-size:10px">🏫</div>`,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
    });

const desenharInstituicoes = () => {
    if (!map || !instLayer) return;
    instLayer.clearLayers();
    for (const i of props.instituicoes ?? []) {
        L.marker([i.tin_latitude, i.tin_longitude], { icon: instIcon(), interactive: true })
            .bindTooltip(i.tin_nome, { direction: 'top' })
            .addTo(instLayer);
    }
};

const desenharPontos = () => {
    if (!map || !pontosLayer) return;
    pontosLayer.clearLayers();

    const comCoords = props.pontos
        .map((p, index) => ({ p, index }))
        .filter(({ p }) => p.pto_latitude != null && p.pto_longitude != null);

    comCoords.forEach(({ p, index }, i) => {
        const marker = L.marker([p.pto_latitude as number, p.pto_longitude as number], {
            icon: numberedIcon(i + 1),
            draggable: props.editable,
        }).bindTooltip(`${i + 1}. ${p.pto_nome}`, { direction: 'top' });

        if (props.editable) {
            marker.on('dragend', () => {
                const ll = marker.getLatLng();
                emit('marker-drag', index, ll.lat, ll.lng);
            });
        }
        marker.addTo(pontosLayer!);
    });

    ajustarBounds();
};

const desenharTrajeto = () => {
    if (!map) return;
    if (trajetoLayer) {
        trajetoLayer.remove();
        trajetoLayer = null;
    }
    const coords = props.trajeto?.coordinates;
    if (coords && coords.length > 1) {
        // GeoJSON é [lon, lat]; Leaflet quer [lat, lon].
        const latlngs = coords.map(([lon, lat]) => [lat, lon] as [number, number]);
        trajetoLayer = L.polyline(latlngs, { color: '#4f46e5', weight: 4, opacity: 0.8 }).addTo(map);
    }
};

const ajustarBounds = () => {
    if (!map) return;
    const pts = props.pontos
        .filter((p) => p.pto_latitude != null && p.pto_longitude != null)
        .map((p) => [p.pto_latitude as number, p.pto_longitude as number] as [number, number]);
    if (pts.length === 1) {
        map.setView(pts[0], 15);
    } else if (pts.length > 1) {
        map.fitBounds(L.latLngBounds(pts).pad(0.2));
    }
};

onMounted(() => {
    if (!el.value) return;

    map = L.map(el.value, { scrollWheelZoom: true }).setView(CENTRO_PADRAO, 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    instLayer = L.layerGroup().addTo(map);
    pontosLayer = L.layerGroup().addTo(map);

    if (props.editable) {
        map.on('click', (e: L.LeafletMouseEvent) => emit('map-click', e.latlng.lat, e.latlng.lng));
    }

    desenharInstituicoes();
    desenharTrajeto();
    desenharPontos();

    // Recalcula tamanho quando o container muda (ex.: aba que estava oculta).
    resizeObs = new ResizeObserver(() => map?.invalidateSize());
    resizeObs.observe(el.value);
});

onBeforeUnmount(() => {
    resizeObs?.disconnect();
    map?.remove();
    map = null;
});

watch(() => props.pontos, desenharPontos, { deep: true });
watch(() => props.instituicoes, desenharInstituicoes, { deep: true });
watch(() => props.trajeto, desenharTrajeto, { deep: true });

// Permite ao pai forçar recálculo de tamanho (ao ativar a aba do mapa).
defineExpose({ invalidate: () => map?.invalidateSize() });
</script>

<template>
    <div ref="el" :style="{ height, width: '100%' }" class="overflow-hidden rounded-xl border shadow-sm"></div>
</template>
