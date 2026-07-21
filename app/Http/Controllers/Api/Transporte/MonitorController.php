<?php

namespace App\Http\Controllers\Api\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $incluirIds = $this->incluirIds($request);

        $query = Monitor::query()->orderBy('tmo_nome');

        if ($q = trim((string) $request->input('q', ''))) {
            $query->search($q);
        }

        $this->filtroAtivoOuIncluso($query, 'tmo_fl_ativo', 'tmo_id', $incluirIds);

        return response()->json(
            $query->limit(50)->get(['tmo_id', 'tmo_nome', 'tmo_fl_ativo'])
        );
    }
}
