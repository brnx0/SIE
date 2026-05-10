<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bairro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BairroController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();
        $munId = $request->integer('mun_id') ?: null;

        $items = Bairro::query()
            ->search($term ?: null, $munId)
            ->where('bai_fl_ativo', true)
            ->limit(30)
            ->get(['bai_id', 'bai_nome', 'bai_mun_id']);

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bai_mun_id' => ['required', 'integer', 'exists:edu_municipio,mun_id'],
            'bai_nome' => ['required', 'string', 'max:100'],
        ]);

        $bairro = Bairro::firstOrCreate(
            ['bai_mun_id' => $data['bai_mun_id'], 'bai_nome' => $data['bai_nome']],
            ['bai_fl_ativo' => true],
        );

        return response()->json($bairro->only(['bai_id', 'bai_nome', 'bai_mun_id']));
    }
}
