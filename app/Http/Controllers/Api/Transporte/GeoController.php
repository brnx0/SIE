<?php

namespace App\Http\Controllers\Api\Transporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Proxies de georreferenciamento (Fase 5). Centraliza a troca de provedor,
 * evita CORS no cliente e respeita os limites dos serviços públicos via cache.
 *
 * Provedores padrão: Nominatim (geocodificação) e OSRM (roteirização).
 * Configuráveis por env (services.nominatim.url / services.osrm.url).
 */
class GeoController extends Controller
{
    /** Endereço → coordenadas (Nominatim). */
    public function geocode(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 3) {
            return response()->json([]);
        }

        $resultados = Cache::remember('geo:geocode:' . md5(mb_strtolower($q)), now()->addDays(30), function () use ($q) {
            $base = rtrim((string) config('services.nominatim.url'), '/');

            $resp = Http::withHeaders(['User-Agent' => (string) config('services.nominatim.user_agent')])
                ->timeout(8)
                ->get("{$base}/search", [
                    'q'              => $q,
                    'format'         => 'jsonv2',
                    'limit'          => 6,
                    'addressdetails' => 0,
                ]);

            if (! $resp->successful()) {
                return null; // não cacheia falha
            }

            return collect($resp->json())
                ->map(fn ($r) => [
                    'nome' => $r['display_name'] ?? '',
                    'lat'  => isset($r['lat']) ? (float) $r['lat'] : null,
                    'lon'  => isset($r['lon']) ? (float) $r['lon'] : null,
                ])
                ->filter(fn ($r) => $r['lat'] !== null && $r['lon'] !== null)
                ->values()
                ->all();
        });

        if ($resultados === null) {
            Cache::forget('geo:geocode:' . md5(mb_strtolower($q)));
            return response()->json(['message' => 'Serviço de geocodificação indisponível no momento.'], 502);
        }

        return response()->json($resultados);
    }

    /** Sequência de coordenadas → distância, tempo e polyline (OSRM). */
    public function rotear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coords'       => ['required', 'array', 'min:2', 'max:50'],
            'coords.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'coords.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // OSRM espera lon,lat;lon,lat;...
        $path = collect($data['coords'])
            ->map(fn ($c) => round((float) $c['lng'], 6) . ',' . round((float) $c['lat'], 6))
            ->implode(';');

        $rota = Cache::remember('geo:route:' . md5($path), now()->addDays(7), function () use ($path) {
            $base = rtrim((string) config('services.osrm.url'), '/');

            $resp = Http::timeout(12)->get("{$base}/route/v1/driving/{$path}", [
                'overview'    => 'full',
                'geometries'  => 'geojson',
                'steps'       => 'false',
                'annotations' => 'false',
            ]);

            if (! $resp->successful() || $resp->json('code') !== 'Ok') {
                return null;
            }

            $r = $resp->json('routes.0');
            if (! $r) {
                return null;
            }

            return [
                'distance_m' => $r['distance'] ?? null,  // metros
                'duration_s' => $r['duration'] ?? null,  // segundos
                'geometry'   => $r['geometry'] ?? null,  // GeoJSON LineString
            ];
        });

        if ($rota === null) {
            Cache::forget('geo:route:' . md5($path));
            return response()->json(['message' => 'Não foi possível calcular a rota (serviço indisponível ou trajeto inválido).'], 502);
        }

        return response()->json($rota);
    }
}
