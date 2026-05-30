<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Disciplina\Disciplina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DisciplinaController extends Controller
{
    /**
     * GET /api/disciplinas/search?q=...
     * Lista todas disciplinas ativas, com busca opcional.
     */
    public function search(Request $request): JsonResponse
    {
        $q          = trim((string) $request->input('q', ''));
        $incluirIds = $this->incluirIds($request);

        $query = Disciplina::query();
        $this->filtroAtivoOuIncluso($query, 'dis_fl_ativo', 'dis_id', $incluirIds);

        if (strlen($q) >= 2) {
            $qNorm = trim(preg_replace('/\s+/', ' ', $q));
            $query->where(function ($w) use ($qNorm) {
                $w->whereRaw('dis_nome ilike ?', ["%{$qNorm}%"])
                  ->orWhereRaw('dis_nome_mec ilike ?', ["%{$qNorm}%"])
                  ->orWhereRaw('dis_sigla ilike ?', ["%{$qNorm}%"]);
            });
        }

        return response()->json(
            $query->orderBy('dis_nome')
                ->limit(500)
                ->get(['dis_id', 'dis_nome', 'dis_sigla'])
        );
    }
}
