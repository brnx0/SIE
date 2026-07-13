<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\InstituicaoEnsinoSuperior;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstituicaoEnsinoSuperiorController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term       = $request->string('q')->trim()->toString();
        $incluirIds = $this->incluirIds($request);

        $query = InstituicaoEnsinoSuperior::query()->orderBy('ies_nome');
        $this->filtroAtivoOuIncluso($query, 'ies_fl_ativo', 'ies_id', $incluirIds);

        if ($term) {
            $query->where(function ($w) use ($term) {
                $w->where('ies_nome', 'ilike', "%{$term}%")
                    ->orWhereRaw('CAST(ies_codigo AS TEXT) ILIKE ?', ["%{$term}%"]);
            });
        }

        return response()->json(
            $query->limit(30)->get(['ies_id', 'ies_codigo', 'ies_nome'])
        );
    }
}
