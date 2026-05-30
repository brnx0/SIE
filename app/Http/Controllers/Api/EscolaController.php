<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
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

        if ($term) {
            $query->where('esc_nome', 'ilike', "%{$term}%");
        }

        return response()->json(
            $query->limit(30)->get(['esc_id', 'esc_nome'])
        );
    }
}
