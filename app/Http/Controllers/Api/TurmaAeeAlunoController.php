<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aluno\Aluno;
use App\Models\Matricula\Matricula;
use App\Models\Turma\Turma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TurmaAeeAlunoController extends Controller
{
    /** Alunos já alocados na turma AEE. */
    public function index(int $turId): JsonResponse
    {
        $turma = $this->aeeTurma($turId);

        $matriculas = Matricula::where('tma_tur_id', $turma->tur_id)
            ->where('tma_modalidade', Turma::MODALIDADE_AEE)
            ->whereNull('tma_deleted_at')
            ->with([
                'aluno:aln_id,aln_nome,aln_nr_matricula',
                'aluno.matriculas' => fn ($q) => $q->where('tma_anl_id', $turma->tur_anl_id)
                    ->where('tma_modalidade', Turma::MODALIDADE_REGULAR)
                    ->whereNull('tma_deleted_at')
                    ->with(['turma:tur_id,tur_nome,tur_esc_id', 'turma.escola:esc_id,esc_nome']),
            ])
            ->orderBy('tma_dt_matricula')
            ->get();

        return response()->json($matriculas->map(function (Matricula $m) use ($turma) {
            $regular = $m->aluno?->matriculas->first();
            $crossEscola = $regular?->turma?->tur_esc_id && $regular->turma->tur_esc_id !== $turma->tur_esc_id;

            return [
                'tma_id'           => $m->tma_id,
                'tma_dt_matricula' => $m->tma_dt_matricula?->format('Y-m-d'),
                'tma_situacao'     => $m->tma_situacao,
                'aln_id'           => $m->aluno?->aln_id,
                'aln_nome'         => $m->aluno?->aln_nome,
                'aln_nr_matricula' => $m->aluno?->aln_nr_matricula,
                'esc_regular_nome' => $regular?->turma?->escola?->esc_nome,
                'cross_escola'     => $crossEscola,
            ];
        }));
    }

    /** Busca alunos elegíveis: flag PCD/TGD/AH (als_fl_pcd) + matriculado no ano vigente. */
    public function elegiveis(Request $request, int $turId): JsonResponse
    {
        $turma  = $this->aeeTurma($turId);
        $search = trim((string) $request->input('search', ''));

        $jaAlocados = Matricula::where('tma_tur_id', $turma->tur_id)
            ->where('tma_modalidade', Turma::MODALIDADE_AEE)
            ->whereNull('tma_deleted_at')
            ->pluck('tma_aln_id');

        $alunos = Aluno::query()
            ->publicoAee()
            ->with([
                'saude:als_id,als_aln_id,als_fl_pcd,als_fl_altas_habilidades,als_deficiencias,als_transtornos_globais,als_transtornos_aprendizagem,als_cid',
                'matriculas' => fn ($q) => $this->matriculaNoAno($q, $turma)
                    ->with([
                        'turma:tur_id,tur_nome,tur_esc_id,tur_ser_id,tur_modalidade',
                        'turma.escola:esc_id,esc_nome',
                        'turma.serie:ser_id,ser_nome',
                    ]),
            ])
            ->whereHas('matriculas', fn ($q) => $this->matriculaNoAno($q, $turma))
            ->whereNotIn('aln_id', $jaAlocados)
            ->when($search !== '', fn ($q) => $q->whereRaw('aln_nome ilike ?', ["%{$search}%"]))
            ->orderBy('aln_nome')
            ->limit(50)
            ->get(['aln_id', 'aln_nome', 'aln_nr_matricula', 'aln_foto']);

        return response()->json($alunos->map(function (Aluno $a) {
            $regular = $a->matriculas
                ->firstWhere('tma_modalidade', Turma::MODALIDADE_REGULAR)
                ?? $a->matriculas->first();

            return [
                'aln_id'           => $a->aln_id,
                'aln_nome'         => $a->aln_nome,
                'aln_nr_matricula' => $a->aln_nr_matricula,
                'aln_foto_url'     => $a->aln_foto_url,
                'matricula_regular' => $regular ? [
                    'tma_id'    => $regular->tma_id,
                    'esc_nome'  => $regular->turma?->escola?->esc_nome,
                    'esc_id'    => $regular->turma?->tur_esc_id,
                    'ser_nome'  => $regular->turma?->serie?->ser_nome,
                    'tur_nome'  => $regular->turma?->tur_nome,
                    'modalidade'=> $regular->turma?->tur_modalidade,
                ] : null,
                'saude' => $a->saude ? [
                    'als_fl_pcd'                   => (bool) $a->saude->als_fl_pcd,
                    'als_fl_altas_habilidades'     => (bool) $a->saude->als_fl_altas_habilidades,
                    'als_deficiencias'             => $a->saude->als_deficiencias ?? [],
                    'als_transtornos_globais'      => $a->saude->als_transtornos_globais ?? [],
                    'als_transtornos_aprendizagem' => $a->saude->als_transtornos_aprendizagem ?? [],
                    'als_cid'                      => $a->saude->als_cid,
                ] : null,
            ];
        }));
    }

    /** Aloca aluno na turma AEE (dupla matrícula). */
    public function store(Request $request, int $turId): JsonResponse
    {
        $turma = $this->aeeTurma($turId);

        $data = $request->validate([
            'aln_id' => ['required', 'integer', 'exists:edu_aluno,aln_id'],
        ], [], ['aln_id' => 'aluno']);

        $aluno = Aluno::findOrFail($data['aln_id']);

        // Pré-requisito 1: flag "Aluno com Deficiência (PCD), TGD ou Altas Habilidades" marcada.
        if (! Aluno::publicoAee()->whereKey($aluno->aln_id)->exists()) {
            throw ValidationException::withMessages([
                'aln_id' => 'Aluno não está marcado como PCD/TGD/Altas Habilidades no quadro de saúde.',
            ]);
        }

        // Pré-requisito 2: matriculado no ano letivo vigente da turma AEE.
        $matriculadoNoAno = Matricula::query()
            ->where('tma_aln_id', $aluno->aln_id)
            ->where(fn ($q) => $this->matriculaNoAno($q, $turma))
            ->exists();

        if (! $matriculadoNoAno) {
            throw ValidationException::withMessages([
                'aln_id' => 'Aluno precisa estar matriculado no ano letivo vigente para ser alocado no AEE.',
            ]);
        }

        // Já alocado?
        $jaAlocado = Matricula::where('tma_tur_id', $turma->tur_id)
            ->where('tma_aln_id', $aluno->aln_id)
            ->where('tma_modalidade', Turma::MODALIDADE_AEE)
            ->whereNull('tma_deleted_at')
            ->exists();

        if ($jaAlocado) {
            throw ValidationException::withMessages([
                'aln_id' => 'Aluno já está alocado nesta turma AEE.',
            ]);
        }

        // Capacidade
        if ($turma->tur_capacidade) {
            $ativos = Matricula::where('tma_tur_id', $turma->tur_id)
                ->where('tma_modalidade', Turma::MODALIDADE_AEE)
                ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->whereNull('tma_deleted_at')
                ->count();

            if ($ativos >= $turma->tur_capacidade) {
                throw ValidationException::withMessages([
                    'aln_id' => "Turma sem vagas disponíveis ({$ativos}/{$turma->tur_capacidade}).",
                ]);
            }
        }

        Matricula::create([
            'tma_aln_id'          => $aluno->aln_id,
            'tma_tur_id'          => $turma->tur_id,
            'tma_anl_id'          => $turma->tur_anl_id,
            'tma_modalidade'      => Turma::MODALIDADE_AEE,
            'tma_situacao'        => Matricula::SITUACAO_ATIVA,
            'tma_tas_cod_entrada' => Matricula::TAS_ENTRADA_NOVO,
            'tma_dt_matricula'    => now()->toDateString(),
            'tma_created_by_id'   => auth()->id(),
        ]);

        return response()->json(['ok' => true], 201);
    }

    /** Remove alocação do aluno na turma AEE. */
    public function destroy(int $turId, int $tmaId): JsonResponse
    {
        $turma = $this->aeeTurma($turId);

        $matricula = Matricula::where('tma_tur_id', $turma->tur_id)
            ->where('tma_id', $tmaId)
            ->where('tma_modalidade', Turma::MODALIDADE_AEE)
            ->firstOrFail();

        $matricula->delete();

        return response()->json(['ok' => true]);
    }

    private function aeeTurma(int $turId): Turma
    {
        return Turma::aee()->findOrFail($turId);
    }

    /** Filtro: aluno matriculado no ano letivo da turma AEE (qualquer matrícula vigente). */
    private function matriculaNoAno($q, Turma $turma)
    {
        return $q->where('tma_anl_id', $turma->tur_anl_id)
            ->whereNull('tma_deleted_at');
    }
}
