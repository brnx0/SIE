<?php

namespace App\Http\Controllers\Api\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\Veiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VeiculoController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $incluirIds = $this->incluirIds($request);

        $query = Veiculo::query()->orderBy('vei_placa');

        if ($q = trim((string) $request->input('q', ''))) {
            $query->search($q);
        }

        $this->filtroAtivoOuIncluso($query, 'vei_fl_ativo', 'vei_id', $incluirIds);

        return response()->json(
            $query->limit(50)->get(['vei_id', 'vei_placa', 'vei_tipo', 'vei_capacidade', 'vei_fl_ativo'])
        );
    }
}
