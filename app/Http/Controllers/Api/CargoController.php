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
        $term = $request->string('q')->trim()->toString();

        $query = Cargo::query()
            ->where('crg_fl_ativo', true)
            ->orderBy('crg_nome');

        if ($term) {
            $query->where('crg_nome', 'ilike', "%{$term}%");
        }

        return response()->json(
            $query->limit(30)->get(['crg_id', 'crg_nome'])
        );
    }
}
