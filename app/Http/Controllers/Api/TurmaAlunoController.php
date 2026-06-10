<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Turma\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TurmaAlunoController extends Controller
{
    public function index(int $turId): JsonResponse
    {
        $turma = Turma::with('anoLetivo:anl_id,anl_ano,anl_dt_corte')->findOrFail($turId);

        $dtCorte = $turma->anoLetivo?->anl_dt_corte;

        $matriculas = Matricula::where('tma_tur_id', $turId)
            ->whereNull('tma_deleted_at')
            ->with([
                'aluno:aln_id,aln_nome,aln_nr_matricula,aln_dt_nascimento',
                'situacaoEntrada:tas_cod,tas_descricao_enturmacao',
                'situacaoSaida:tas_cod,tas_descricao_enturmacao',
            ])
            ->orderBy('tma_dt_matricula')
            ->get();

        return response()->json($matriculas->map(function (Matricula $m) use ($dtCorte) {
            $idade = null;
            if ($dtCorte && $m->aluno?->aln_dt_nascimento) {
                $nasc  = \Carbon\Carbon::parse($m->aluno->aln_dt_nascimento);
                $corte = \Carbon\Carbon::parse($dtCorte);
                $idade = (int) $nasc->diffInYears($corte);
            }

            return [
                'tma_id'          => $m->tma_id,
                'tma_dt_matricula'=> $m->tma_dt_matricula?->format('Y-m-d'),
                'tma_fl_renovado' => (bool) $m->tma_fl_renovado,
                'tma_situacao'         => $m->tma_situacao,
                'tma_tas_cod_saida'    => $m->tma_tas_cod_saida,
                'tas_descricao_entrada'=> $m->situacaoEntrada?->tas_descricao_enturmacao,
                'tas_descricao_saida'  => $m->situacaoSaida?->tas_descricao_enturmacao,
                'aln_id'          => $m->aluno?->aln_id,
                'aln_nome'        => $m->aluno?->aln_nome,
                'aln_nr_matricula'=> $m->aluno?->aln_nr_matricula,
                'idade'           => $idade,
            ];
        }));
    }

    public function toggleRenovado(Request $request, int $turId, int $tmaId): JsonResponse
    {
        $matricula = Matricula::where('tma_tur_id', $turId)
            ->where('tma_id', $tmaId)
            ->firstOrFail();

        $matricula->update(['tma_fl_renovado' => $request->boolean('tma_fl_renovado')]);

        return response()->json(['tma_fl_renovado' => (bool) $matricula->tma_fl_renovado]);
    }
}
