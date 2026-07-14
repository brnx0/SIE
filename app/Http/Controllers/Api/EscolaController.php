<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EscolaController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term       = $request->string('q')->trim()->toString();
        $incluirIds = $this->incluirIds($request);

        $query = Escola::query()->orderBy('esc_nome');
        $this->filtroAtivoOuIncluso($query, 'esc_fl_ativo', 'esc_id', $incluirIds);

        // Escopo por lotação: admin/gestor/coord.interno (sem filtro) → null = todas;
        // demais (ex.: secretaria) → só as escolas da lotação (+ ids já salvos).
        $escIds = UserAccess::escolasIds($request->user());
        if ($escIds !== null) {
            $permitidos = array_values(array_unique(array_merge($escIds, $incluirIds))) ?: [-1];
            $query->whereIn('esc_id', $permitidos);
        }

        if ($term) {
            $query->where('esc_nome', 'ilike', "%{$term}%");
        }

        return response()->json(
            $query->limit(30)->get(['esc_id', 'esc_nome'])
        );
    }
}
