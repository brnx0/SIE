<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Segmento\Segmento;
use App\Models\Turma\Turma;
use App\Services\Aluno\AplicaMovimentacao;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Remanejamento (troca de alunos entre turmas) — Secretaria Escolar.
 * Só turmas REGULAR da mesma escola + segmento + série. Reusa o motor de
 * movimentação (tmv_cod 5 = Remanejamento). Admin vê todas as escolas;
 * secretaria só as de lotação ativa.
 */
class RemanejamentoController extends Controller
{
    private const TMV_REMANEJAMENTO = 5;

    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('remanejamento-turmas/Index', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
            'segmentos'   => Segmento::where('seg_fl_ativo', true)->orderBy('seg_ordem')->get(['seg_id', 'seg_nome_reduzido', 'seg_nome_completo']),
        ]);
    }

    /** Turmas REGULAR abertas da escola+ano+segmento, com vagas e série. */
    public function turmas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'seg_id' => ['nullable', 'integer', 'exists:edu_segmento,seg_id'],
        ]);

        $this->autorizarEscola((int) $data['esc_id']);

        $turmas = Turma::query()
            ->with(['serie:ser_id,ser_nome,ser_fl_multi'])
            ->where('tur_modalidade', Turma::MODALIDADE_REGULAR)
            ->where('tur_esc_id', $data['esc_id'])
            ->where('tur_anl_id', $data['anl_id'])
            ->when(! empty($data['seg_id']), fn ($q) => $q->where('tur_seg_id', $data['seg_id']))
            ->withCount(['matriculas as total_ativos' => fn ($q) => $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)])
            ->orderBy('tur_ser_id')
            ->orderBy('tur_nome')
            ->get();

        return response()->json($turmas->map(function (Turma $t) {
            // Limite efetivo = capacidade + expansão.
            $limite = $t->tur_capacidade !== null ? ((int) $t->tur_capacidade + (int) ($t->tur_qt_expansao ?? 0)) : null;

            return [
                'tur_id'        => $t->tur_id,
                'tur_nome'      => $t->tur_nome,
                'tur_turno'     => $t->tur_turno,
                'tur_situacao'  => $t->tur_situacao,
                'tur_seg_id'    => $t->tur_seg_id,
                'tur_ser_id'    => $t->tur_ser_id,
                'serie'         => $t->serie ? ['ser_id' => $t->serie->ser_id, 'ser_nome' => $t->serie->ser_nome, 'ser_fl_multi' => (bool) $t->serie->ser_fl_multi] : null,
                'capacidade'    => $t->tur_capacidade,
                'expansao'      => $t->tur_qt_expansao,
                'limite'        => $limite,
                'total_ativos'  => $t->total_ativos,
                'vagas'         => $limite !== null ? max(0, $limite - $t->total_ativos) : null,
            ];
        }));
    }

    /** Alunos ATIVA de uma turma (com a série efetiva de cada um). */
    public function alunos(Request $request): JsonResponse
    {
        $turId = (int) $request->input('tur_id');
        if (! $turId) {
            return response()->json([]);
        }

        $turma = Turma::findOrFail($turId);
        $this->autorizarEscola((int) $turma->tur_esc_id);

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->with(['aluno:aln_id,aln_nome,aln_nr_matricula', 'serie:ser_id,ser_nome'])
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values()
            ->map(function (Matricula $m) use ($turma) {
                $serId = $m->tma_ser_id ?? $turma->tur_ser_id;

                return [
                    'tma_id'    => $m->tma_id,
                    'aln_id'    => $m->aluno->aln_id,
                    'aln_nome'  => $m->aluno->aln_nome,
                    'matricula' => $m->aluno->aln_nr_matricula,
                    'ser_id'    => $serId,
                    'ser_nome'  => $m->serie?->ser_nome ?? optional($turma->serie)->ser_nome,
                ];
            });

        return response()->json($alunos);
    }

    /** Remaneja em lote os alunos marcados para a turma destino. */
    /** Move (mesma direção) os alunos marcados para uma turma destino. */
    public function remanejar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id_destino'  => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'tma_ids'         => ['required', 'array', 'min:1'],
            'tma_ids.*'       => ['integer'],
            'dt_movimentacao' => ['required', 'date'],
            'migrar_notas'    => ['boolean'],
            'migrar_faltas'   => ['boolean'],
        ]);

        $destino = Turma::with('serie:ser_id,ser_fl_multi')->findOrFail($data['tur_id_destino']);
        $this->autorizarEscola((int) $destino->tur_esc_id);

        $itens = $this->montarItens((array) $data['tma_ids'], $destino);

        return $this->aplicar($itens, $data);
    }

    /** Troca alunos entre duas turmas: marcados de A vão p/ B e marcados de B vão p/ A. */
    public function trocar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_a'           => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'tur_b'           => ['required', 'integer', 'different:tur_a', 'exists:edu_turma,tur_id'],
            'tma_ids_a'       => ['array'],
            'tma_ids_a.*'     => ['integer'],
            'tma_ids_b'       => ['array'],
            'tma_ids_b.*'     => ['integer'],
            'dt_movimentacao' => ['required', 'date'],
            'migrar_notas'    => ['boolean'],
            'migrar_faltas'   => ['boolean'],
        ]);

        $turA = Turma::with('serie:ser_id,ser_fl_multi')->findOrFail($data['tur_a']);
        $turB = Turma::with('serie:ser_id,ser_fl_multi')->findOrFail($data['tur_b']);
        $this->autorizarEscola((int) $turA->tur_esc_id);
        $this->autorizarEscola((int) $turB->tur_esc_id);

        if (empty($data['tma_ids_a']) && empty($data['tma_ids_b'])) {
            return response()->json(['message' => 'Selecione ao menos um aluno em alguma das turmas.'], 422);
        }

        // A → B  e  B → A
        $itens = array_merge(
            $this->montarItens((array) ($data['tma_ids_a'] ?? []), $turB),
            $this->montarItens((array) ($data['tma_ids_b'] ?? []), $turA),
        );

        return $this->aplicar($itens, $data);
    }

    /** Monta os itens do lote (preserva série de cada aluno) para um destino. */
    private function montarItens(array $tmaIds, Turma $destino): array
    {
        if (empty($tmaIds)) {
            return [];
        }

        $matriculas = Matricula::with('turma:tur_id,tur_ser_id')
            ->whereIn('tma_id', $tmaIds)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->get();

        return $matriculas->map(fn (Matricula $m) => [
            'tma_id'             => (int) $m->tma_id,
            'tur_id_destino'     => (int) $destino->tur_id,
            'tma_ser_id_destino' => (int) ($m->tma_ser_id ?? $m->turma->tur_ser_id),
        ])->all();
    }

    private function aplicar(array $itens, array $data): JsonResponse
    {
        if (empty($itens)) {
            return response()->json(['message' => 'Nenhum aluno ativo selecionado.'], 422);
        }

        try {
            $res = app(AplicaMovimentacao::class)->remanejarLote($itens, [
                'dt_movimentacao' => $data['dt_movimentacao'],
                'migrar_notas'    => ! empty($data['migrar_notas']),
                'migrar_faltas'   => ! empty($data['migrar_faltas']),
            ]);

            return response()->json($res);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->implode(' ')], 422);
        }
    }

    /** Admin vê qualquer escola; secretaria só as da lotação ativa. */
    private function autorizarEscola(int $escId): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }
        $ids = UserAccess::escolasIds($user);
        abort_unless(is_array($ids) && in_array($escId, $ids, true), 403, 'Você não tem acesso a esta escola.');
    }
}
