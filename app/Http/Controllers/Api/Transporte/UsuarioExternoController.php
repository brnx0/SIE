<?php

namespace App\Http\Controllers\Api\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\UsuarioExterno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioExternoController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $incluirIds = $this->incluirIds($request);

        $query = UsuarioExterno::query()
            ->with('instituicao:tin_id,tin_nome')
            ->orderBy('tue_nome');

        if ($q = trim((string) $request->input('q', ''))) {
            $query->search($q);
        }

        $this->filtroAtivoOuIncluso($query, 'tue_fl_ativo', 'tue_id', $incluirIds);

        return response()->json(
            $query->limit(50)->get(['tue_id', 'tue_nome', 'tue_tin_id', 'tue_fl_ativo'])
        );
    }
}
