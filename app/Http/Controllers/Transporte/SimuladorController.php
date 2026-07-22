<?php

namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Models\Transporte\Ponto;
use App\Models\Transporte\Rota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SimuladorController extends Controller
{
    public function index(Request $request): Response
    {
        $rota = null;
        if ($rotId = $request->integer('rot_id')) {
            $rota = Rota::with([
                'anoLetivo:anl_id,anl_ano',
                'veiculo:vei_id,vei_placa,vei_capacidade',
                'pontos.escola:esc_id,esc_nome',
            ])->find($rotId);
        }

        return Inertia::render('transporte/simulador/Index', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'rota'        => $rota ? [
                'rot_id'        => $rota->rot_id,
                'rot_nome'      => $rota->rot_nome,
                'rot_km'        => $rota->rot_km,
                'rot_tempo_min' => $rota->rot_tempo_min,
                'rot_trajeto'   => $rota->rot_trajeto,
                'capacidade'    => $rota->veiculo?->vei_capacidade,
                'total_passageiros' => $rota->passageiros()->count(),
                'pontos'        => $rota->pontos->map(fn ($p) => [
                    'pto_id'        => $p->pto_id,
                    'pto_ordem'     => $p->pto_ordem,
                    'pto_nome'      => $p->pto_nome,
                    'pto_latitude'  => $p->pto_latitude,
                    'pto_longitude' => $p->pto_longitude,
                    'escola'        => $p->escola ? ['esc_id' => $p->escola->esc_id, 'esc_nome' => $p->escola->esc_nome] : null,
                ])->values(),
            ] : null,
            'escolas' => Escola::query()
                ->where('esc_fl_ativo', true)
                ->whereNotNull('esc_latitude')
                ->whereNotNull('esc_longitude')
                ->orderBy('esc_nome')
                ->limit(500)
                ->get(['esc_id', 'esc_nome', 'esc_latitude', 'esc_longitude']),
        ]);
    }

    /**
     * Efetiva a simulação: grava coordenadas/ordem dos pontos, o trajeto
     * (GeoJSON) e km/tempo estimados da rota. Não altera passageiros nem
     * exclui pontos (remoção continua na aba Passageiros/Pontos da rota).
     */
    public function efetivar(Request $request, Rota $rota): RedirectResponse
    {
        $data = $request->validate([
            'pontos'                => ['present', 'array'],
            'pontos.*.pto_id'       => ['nullable', 'integer'],
            'pontos.*.pto_nome'     => ['required', 'string', 'max:120'],
            'pontos.*.pto_ordem'    => ['required', 'integer', 'min:1'],
            'pontos.*.pto_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pontos.*.pto_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'rot_km'                => ['nullable', 'numeric', 'min:0'],
            'rot_tempo_min'         => ['nullable', 'integer', 'min:0'],
            'rot_trajeto'           => ['nullable', 'array'], // GeoJSON LineString
        ], [], [
            'pontos.*.pto_nome' => 'nome do ponto',
        ]);

        $idsRota = $rota->pontos()->pluck('pto_id')->all();

        DB::transaction(function () use ($data, $rota, $idsRota) {
            foreach ($data['pontos'] as $p) {
                $payload = [
                    'pto_ordem'     => $p['pto_ordem'],
                    'pto_latitude'  => $p['pto_latitude'] ?? null,
                    'pto_longitude' => $p['pto_longitude'] ?? null,
                ];

                if (! empty($p['pto_id']) && in_array((int) $p['pto_id'], $idsRota, true)) {
                    // Ponto existente da rota → atualiza coordenadas/ordem.
                    Ponto::where('pto_id', $p['pto_id'])->update($payload);
                } elseif (empty($p['pto_id'])) {
                    // Ponto novo criado no mapa.
                    $rota->pontos()->create(array_merge($payload, [
                        'pto_rot_id' => $rota->rot_id,
                        'pto_nome'   => $p['pto_nome'],
                    ]));
                }
            }

            $rota->update([
                'rot_km'        => $data['rot_km'] ?? $rota->rot_km,
                'rot_tempo_min' => $data['rot_tempo_min'] ?? $rota->rot_tempo_min,
                'rot_trajeto'   => $data['rot_trajeto'] ?? $rota->rot_trajeto,
            ]);
        });

        return to_route('transporte.simulador', ['rot_id' => $rota->rot_id])
            ->with('success', 'Simulação efetivada: pontos, trajeto e estimativas foram gravados na rota.');
    }
}
