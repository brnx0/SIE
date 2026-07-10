<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\CursoFormacaoSuperior;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CursoFormacaoSuperiorController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term       = $request->string('q')->trim()->toString();
        $incluirIds = $this->incluirIds($request);

        $query = CursoFormacaoSuperior::query()->orderBy('cfs_nome');
        $this->filtroAtivoOuIncluso($query, 'cfs_fl_ativo', 'cfs_id', $incluirIds);

        if ($term) {
            $query->where(function ($w) use ($term) {
                $w->where('cfs_nome', 'ilike', "%{$term}%")
                    ->orWhere('cfs_codigo', 'ilike', "%{$term}%");
            });
        }

        return response()->json(
            $query->limit(30)->get(['cfs_id', 'cfs_codigo', 'cfs_nome', 'cfs_grau'])
        );
    }
}
