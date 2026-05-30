<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Escola\EscolaSegmento;
use App\Models\Serie\Serie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SerieController extends Controller
{
    public function bySegmento(Request $request): JsonResponse
    {
        $segId = (int) $request->input('seg_id');

        $series = Serie::where('seg_id', $segId)
            ->where('ser_fl_ativo', true)
            ->orderBy('ser_ordem_no_segmento')
            ->get(['ser_id', 'ser_nome']);

        return response()->json($series);
    }

    /**
     * GET api/series/by-escola-segmento?esc_id=X&anl_id=Y&seg_id=Z
     * Séries da escola para o segmento e ano letivo, respeitando o range ser_id_inicio/fim.
     */
    public function byEscolaSegmento(Request $request): JsonResponse
    {
        $escId = (int) $request->input('esc_id');
        $anlId = (int) $request->input('anl_id');
        $segId = (int) $request->input('seg_id');

        if (! $escId || ! $anlId || ! $segId) {
            return response()->json([]);
        }

        $esg = EscolaSegmento::with([
                'serieInicio:ser_id,ser_ordem_no_segmento',
                'serieFim:ser_id,ser_ordem_no_segmento',
            ])
            ->where('esc_id', $escId)
            ->where('seg_id', $segId)
            ->vigente($anlId)
            ->first(['esg_id', 'seg_id', 'ser_id_inicio', 'ser_id_fim']);

        if (! $esg) {
            return response()->json([]);
        }

        $ordemInicio = $esg->serieInicio?->ser_ordem_no_segmento ?? 0;
        $ordemFim    = $esg->serieFim?->ser_ordem_no_segmento ?? PHP_INT_MAX;

        $series = Serie::where('seg_id', $segId)
            ->where('ser_fl_ativo', true)
            ->whereBetween('ser_ordem_no_segmento', [$ordemInicio, $ordemFim])
            ->orderBy('ser_ordem_no_segmento')
            ->get(['ser_id', 'ser_nome']);

        return response()->json($series);
    }

    public function search(Request $request): JsonResponse
    {
        $q       = trim($request->input('q', ''));
        $exclude = $request->input('exclude');

        // Normaliza ordinais (ª, º) e espaços extras tanto da query quanto do nome no banco
        $qNorm = trim(preg_replace('/\s+/', ' ', str_replace(['ª', 'º'], '', $q)));

        $series = Serie::where('ser_fl_ativo', true)
            ->when(strlen($qNorm) >= 2, fn ($query) => $query->whereRaw(
                "regexp_replace(replace(replace(ser_nome, 'ª', ''), 'º', ''), '\\s+', ' ', 'g') ilike ?",
                ["%{$qNorm}%"]
            ))
            ->when($exclude, fn ($query) => $query->where('ser_id', '!=', (int) $exclude))
            ->orderBy('ser_ordem_no_segmento')
            ->limit(50)
            ->get(['ser_id', 'ser_nome']);

        return response()->json($series);
    }
}
