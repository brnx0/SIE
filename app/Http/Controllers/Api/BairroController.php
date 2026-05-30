<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Escola\Bairro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BairroController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term       = $request->string('q')->trim()->toString();
        $munId      = $request->integer('mun_id') ?: null;
        $incluirIds = $this->incluirIds($request);

        $query = Bairro::query()->search($term ?: null, $munId);
        $this->filtroAtivoOuIncluso($query, 'bai_fl_ativo', 'bai_id', $incluirIds);

        return response()->json(
            $query->limit(30)->get(['bai_id', 'bai_nome', 'bai_mun_id'])
        );
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
