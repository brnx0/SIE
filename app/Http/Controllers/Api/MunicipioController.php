<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Municipio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MunicipioController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();
        $uf = $request->string('uf')->trim()->upper()->toString();

        $items = Municipio::query()
            ->search($term ?: null, $uf ?: null)
            ->limit(20)
            ->get(['mun_id', 'mun_nome', 'mun_uf', 'mun_codigo_ibge']);

        return response()->json($items);
    }

    public function byIbge(string $codigo): JsonResponse
    {
        $municipio = Municipio::where('mun_codigo_ibge', $codigo)
            ->first(['mun_id', 'mun_nome', 'mun_uf', 'mun_codigo_ibge']);

        if (! $municipio) {
            return response()->json(['error' => 'Município não encontrado'], 404);
        }

        return response()->json($municipio);
    }
}
