<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\Cargo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term       = $request->string('q')->trim()->toString();
        $incluirIds = $this->incluirIds($request);

        $query = Cargo::query()->orderBy('crg_nome');
        $this->filtroAtivoOuIncluso($query, 'crg_fl_ativo', 'crg_id', $incluirIds);

        if ($term) {
            $query->where('crg_nome', 'ilike', "%{$term}%");
        }

        return response()->json(
            $query->limit(30)->get(['crg_id', 'crg_nome'])
        );
    }
}
