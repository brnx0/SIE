<?php

namespace App\Http\Controllers\Api\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\Rota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RotaController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $incluirIds = $this->incluirIds($request);

        $query = Rota::query()->orderBy('rot_nome');

        if ($q = trim((string) $request->input('q', ''))) {
            $query->search($q);
        }

        if ($anlId = $request->integer('anl_id')) {
            $query->where('rot_anl_id', $anlId);
        }
        if ($turno = $request->string('turno')->toString()) {
            $query->where('rot_turno', $turno);
        }

        $this->filtroAtivoOuIncluso($query, 'rot_fl_ativo', 'rot_id', $incluirIds);

        return response()->json(
            $query->limit(50)->get(['rot_id', 'rot_nome', 'rot_turno', 'rot_anl_id', 'rot_fl_ativo'])
        );
    }
}
