<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GerenciaRegional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GerenciaRegionalController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();
        $uf = $request->string('uf')->trim()->upper()->toString();

        $items = GerenciaRegional::query()
            ->search($term ?: null, $uf ?: null)
            ->limit(20)
            ->get(['ger_id', 'ger_nome', 'ger_sigla', 'ger_uf']);

        return response()->json($items);
    }
}
