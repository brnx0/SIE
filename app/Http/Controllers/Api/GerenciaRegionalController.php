<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Escola\GerenciaRegional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GerenciaRegionalController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term       = $request->string('q')->trim()->toString();
        $uf         = $request->string('uf')->trim()->upper()->toString();
        $incluirIds = $this->incluirIds($request);

        $query = GerenciaRegional::query()->search($term ?: null, $uf ?: null);
        $this->filtroAtivoOuIncluso($query, 'ger_fl_ativo', 'ger_id', $incluirIds);

        return response()->json(
            $query->limit(20)->get(['ger_id', 'ger_nome', 'ger_sigla', 'ger_uf'])
        );
    }
}
