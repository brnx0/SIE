<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aluno\Aluno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlunoSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = $request->string('q')->toString();

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $alunos = Aluno::query()
            ->where('aln_fl_ativo', true)
            ->where(function ($query) use ($q) {
                $query->whereRaw('aln_nome ilike ?', ["%{$q}%"])
                    ->orWhere('aln_nr_matricula', 'like', "%{$q}%")
                    ->orWhere('aln_cpf', 'like', "%{$q}%");
            })
            ->orderBy('aln_nome')
            ->limit(20)
            ->get(['aln_id', 'aln_nome', 'aln_dt_nascimento', 'aln_nr_matricula', 'aln_cpf']);

        return response()->json($alunos->map(fn ($a) => [
            'aln_id'          => $a->aln_id,
            'aln_nome'        => $a->aln_nome,
            'aln_dt_nascimento' => $a->aln_dt_nascimento?->format('Y-m-d'),
            'aln_nr_matricula'  => $a->aln_nr_matricula,
            'aln_cpf'         => $a->aln_cpf,
        ]));
    }
}
