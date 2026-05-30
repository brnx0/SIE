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

        // IDs já vinculados (segmentos atualmente salvos no registro que está sendo editado)
        // devem ser listados mesmo se inativos — mantém histórico
        $incluirIds = array_filter(array_map('intval', explode(',', (string) $request->input('incluir_ids', ''))));

        $segmentos = EscolaSegmento::with('segmento:seg_id,seg_nome_reduzido,seg_nome_completo,seg_fl_ativo')
            ->where('esc_id', $escId)
            ->vigente($anlId)
            ->get(['esg_id', 'seg_id', 'ser_id_inicio', 'ser_id_fim'])
            ->filter(function ($esg) use ($incluirIds) {
                // Lista se segmento ativo OU se já estava vinculado
                return $esg->segmento?->seg_fl_ativo || in_array($esg->seg_id, $incluirIds, true);
            })
            ->values()
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
