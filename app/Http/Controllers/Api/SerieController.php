<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
}
