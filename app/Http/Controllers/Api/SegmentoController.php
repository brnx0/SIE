<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Escola\EscolaSegmento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SegmentoController extends Controller
{
    /**
     * GET api/segmentos/by-escola?esc_id=X&anl_id=Y
     * Retorna segmentos vigentes da escola para o ano letivo informado.
     */
    public function byEscola(Request $request): JsonResponse
    {
        $escId = (int) $request->input('esc_id');
        $anlId = (int) $request->input('anl_id');

        if (! $escId || ! $anlId) {
            return response()->json([]);
        }

        $segmentos = EscolaSegmento::with('segmento:seg_id,seg_nome_reduzido,seg_nome_completo')
            ->where('esc_id', $escId)
            ->vigente($anlId)
            ->get(['esg_id', 'seg_id', 'ser_id_inicio', 'ser_id_fim'])
            ->map(fn ($esg) => [
                'esg_id'        => $esg->esg_id,
                'seg_id'        => $esg->seg_id,
                'seg_nome'      => $esg->segmento?->seg_nome_reduzido,
                'ser_id_inicio' => $esg->ser_id_inicio,
                'ser_id_fim'    => $esg->ser_id_fim,
            ]);

        return response()->json($segmentos);
    }
}
