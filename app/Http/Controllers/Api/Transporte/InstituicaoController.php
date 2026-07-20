<?php

namespace App\Http\Controllers\Api\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\Instituicao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstituicaoController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $incluirIds = $this->incluirIds($request);

        $query = Instituicao::query()->orderBy('tin_nome');

        if ($q = trim((string) $request->input('q', ''))) {
            $query->search($q);
        }

        $this->filtroAtivoOuIncluso($query, 'tin_fl_ativo', 'tin_id', $incluirIds);

        return response()->json(
            $query->limit(50)->get(['tin_id', 'tin_nome', 'tin_esfera'])
        );
    }
}
