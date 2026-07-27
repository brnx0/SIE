import { ref } from 'vue';
import { apiFetch } from '@/lib/apiFetch';

export interface GeocodeResultado {
    nome: string;
    lat: number;
    lon: number;
}

export interface RotaCalculada {
    distance_m: number | null;
    duration_s: number | null;
    geometry: { type: 'LineString'; coordinates: [number, number][] } | null;
}

/** Proxies de geocodificação (Nominatim) e roteirização (OSRM) via backend. */
export function useGeoTransporte() {
    const geocoding = ref(false);
    const roteando = ref(false);
    const erro = ref<string | null>(null);

    async function geocode(q: string): Promise<GeocodeResultado[]> {
        erro.value = null;
        if (q.trim().length < 3) return [];
        geocoding.value = true;
        try {
            const res = await apiFetch(`/api/transporte/geo/geocode?q=${encodeURIComponent(q.trim())}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                erro.value = 'Serviço de busca de endereço indisponível.';
                return [];
            }
            return await res.json();
        } catch {
            erro.value = 'Falha ao buscar endereço.';
            return [];
        } finally {
            geocoding.value = false;
        }
    }

    /** Calcula distância/tempo/polyline para a sequência de coordenadas. */
    async function rotear(coords: { lat: number; lng: number }[]): Promise<RotaCalculada | null> {
        erro.value = null;
        if (coords.length < 2) return null;
        roteando.value = true;
        try {
            const res = await apiFetch('/api/transporte/geo/rotear', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ coords }),
            });
            if (!res.ok) {
                erro.value = 'Não foi possível calcular a rota.';
                return null;
            }
            return await res.json();
        } catch {
            erro.value = 'Falha ao calcular a rota.';
            return null;
        } finally {
            roteando.value = false;
        }
    }

    return { geocoding, roteando, erro, geocode, rotear };
}
