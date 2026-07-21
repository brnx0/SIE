<?php

namespace App\Http\Controllers\Api\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\Motorista;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MotoristaController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $incluirIds = $this->incluirIds($request);

        $query = Motorista::query()->orderBy('mot_nome');

        if ($q = trim((string) $request->input('q', ''))) {
            $query->search($q);
        }

        $this->filtroAtivoOuIncluso($query, 'mot_fl_ativo', 'mot_id', $incluirIds);

        return response()->json(
            $query->limit(50)->get(['mot_id', 'mot_nome', 'mot_cnh_categoria', 'mot_cnh_dt_validade', 'mot_fl_ativo'])
        );
    }
}
