<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aluno\Aluno;
use App\Models\Matricula\Matricula;
use App\Models\Turma\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TurmaAtividadeAlunoController extends Controller
{
    public function index(int $turId): JsonResponse
    {
        $turma = $this->turma($turId);

        $matriculas = Matricula::where('tma_tur_id', $turma->tur_id)
            ->where('tma_modalidade', Turma::MODALIDADE_ATIVIDADE)
            ->whereNull('tma_deleted_at')
            ->with(['aluno:aln_id,aln_nome,aln_nr_matricula'])
            ->orderBy('tma_dt_matricula')
            ->get();

        return response()->json($matriculas->map(fn (Matricula $m) => [
            'tma_id'           => $m->tma_id,
            'tma_dt_matricula' => $m->tma_dt_matricula?->format('Y-m-d'),
            'tma_situacao'     => $m->tma_situacao,
            'aln_id'           => $m->aluno?->aln_id,
            'aln_nome'         => $m->aluno?->aln_nome,
            'aln_nr_matricula' => $m->aluno?->aln_nr_matricula,
        ]));
    }

    /** Alunos com matrícula REGULAR ATIVA no ano letivo da turma de atividade. */
    public function elegiveis(Request $request, int $turId): JsonResponse
    {
        $turma  = $this->turma($turId);
        $search = trim((string) $request->input('search', ''));

        $jaAlocados = Matricula::where('tma_tur_id', $turma->tur_id)
            ->where('tma_modalidade', Turma::MODALIDADE_ATIVIDADE)
            ->whereNull('tma_deleted_at')
            ->pluck('tma_aln_id');

        $alunos = Aluno::query()
            ->whereHas('matriculas', fn ($q) => $this->matriculaRegularAtiva($q, $turma))
            ->whereNotIn('aln_id', $jaAlocados)
            ->when($search !== '', fn ($q) => $q->whereRaw('aln_nome ilike ?', ["%{$search}%"]))
            ->orderBy('aln_nome')
            ->limit(50)
            ->get(['aln_id', 'aln_nome', 'aln_nr_matricula', 'aln_dt_nascimento', 'aln_cpf']);

        return response()->json($alunos);
    }

    public function store(Request $request, int $turId): JsonResponse
    {
        $turma = $this->turma($turId);

        $data = $request->validate([
            'aln_id' => ['required', 'integer', 'exists:edu_aluno,aln_id'],
        ], [], ['aln_id' => 'aluno']);

        $aluno = Aluno::findOrFail($data['aln_id']);

        // Pré-requisito único: matrícula regular ativa no mesmo ano letivo.
        $temRegular = Matricula::query()
            ->where('tma_aln_id', $aluno->aln_id)
            ->where(fn ($q) => $this->matriculaRegularAtiva($q, $turma))
            ->exists();

        if (! $temRegular) {
            throw ValidationException::withMessages([
                'aln_id' => 'Aluno precisa ter matrícula regular ativa neste ano letivo para ser alocado em turma de atividade.',
            ]);
        }

        $jaAlocado = Matricula::where('tma_tur_id', $turma->tur_id)
            ->where('tma_aln_id', $aluno->aln_id)
            ->where('tma_modalidade', Turma::MODALIDADE_ATIVIDADE)
            ->whereNull('tma_deleted_at')
            ->exists();

        if ($jaAlocado) {
            throw ValidationException::withMessages([
                'aln_id' => 'Aluno já está alocado nesta turma de atividade.',
            ]);
        }

        Matricula::create([
            'tma_aln_id'          => $aluno->aln_id,
            'tma_tur_id'          => $turma->tur_id,
            'tma_anl_id'          => $turma->tur_anl_id,
            'tma_modalidade'      => Turma::MODALIDADE_ATIVIDADE,
            'tma_situacao'        => Matricula::SITUACAO_ATIVA,
            'tma_tas_cod_entrada' => Matricula::TAS_ENTRADA_NOVO,
            'tma_dt_matricula'    => now()->toDateString(),
            'tma_created_by_id'   => auth()->id(),
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function destroy(int $turId, int $tmaId): JsonResponse
    {
        $turma = $this->turma($turId);

        $matricula = Matricula::where('tma_tur_id', $turma->tur_id)
            ->where('tma_id', $tmaId)
            ->where('tma_modalidade', Turma::MODALIDADE_ATIVIDADE)
            ->firstOrFail();

        $matricula->delete();

        return response()->json(['ok' => true]);
    }

    private function turma(int $turId): Turma
    {
        return Turma::atividade()->findOrFail($turId);
    }

    private function matriculaRegularAtiva($q, Turma $turma)
    {
        return $q->where('tma_modalidade', Turma::MODALIDADE_REGULAR)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->where('tma_anl_id', $turma->tur_anl_id)
            ->whereNull('tma_deleted_at');
    }
}
