<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diario\SalvarNotaRequest;
use App\Http\Requests\Diario\StoreAvaliacaoRequest;
use App\Models\Diario\DiarioAvaliacao;
use App\Models\Diario\DiarioNota;
use App\Models\Diario\InstrumentoAvaliativo;
use App\Models\Matricula\Matricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lançamento de notas (avaliação numérica / conceitual).
 * Granularidade: turma + disciplina + unidade + tipo.
 * Soma dos valores das avaliações regulares ≤ 10; recuperação à parte (≤ 10).
 */
class NotaController extends Controller
{
    public function contexto(\Illuminate\Http\Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $turId = (int) $request->input('tur_id');
        $disId = (int) $request->input('dis_id');
        $uniId = (int) $request->input('uni_id');
        $tipo  = $request->input('tipo', DiarioAvaliacao::TIPO_NUMERICA);

        if (! $turId || ! $disId || ! $uniId) {
            return response()->json(['tipo_disponivel' => false, 'periodo_aberto' => false, 'avaliacoes' => [], 'alunos' => []]);
        }

        $this->abortIfNotLeciona($request, $turId, $disId);

        $tipos = $this->tiposSerie($turId);
        if (! in_array($tipo, $tipos, true)) {
            return response()->json([
                'tipo_disponivel' => false,
                'periodo_aberto'  => false,
                'avaliacoes'      => [],
                'alunos'          => [],
            ]);
        }

        $avaliacoes = DiarioAvaliacao::query()
            ->with('instrumento:iav_id,iav_nome,iav_fl_recuperacao')
            ->where('ava_tur_id', $turId)
            ->where('ava_dis_id', $disId)
            ->where('ava_uni_id', $uniId)
            ->where('ava_tipo', $tipo)
            ->orderBy('ava_fl_recuperacao')
            ->orderBy('ava_dt')
            ->orderBy('ava_id')
            ->get();

        $avaIds = $avaliacoes->pluck('ava_id')->all();

        // Notas: [ava_id][aln_id] => valor
        $notas = DiarioNota::query()
            ->whereIn('nta_ava_id', $avaIds ?: [0])
            ->get(['nta_ava_id', 'nta_aln_id', 'nta_valor']);

        $notaMap = [];
        foreach ($notas as $n) {
            $notaMap[$n->nta_ava_id][$n->nta_aln_id] = $n->nta_valor;
        }

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values()
            ->map(function ($m) use ($avaliacoes, $notaMap) {
                $notas = [];
                foreach ($avaliacoes as $a) {
                    $notas[$a->ava_id] = $notaMap[$a->ava_id][$m->aluno->aln_id] ?? null;
                }

                return [
                    'aln_id'           => $m->aluno->aln_id,
                    'aln_nome'         => $m->aluno->aln_nome,
                    'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                    'notas'            => $notas,
                ];
            });

        $instrumentos = InstrumentoAvaliativo::query()
            ->where('iav_fl_ativo', true)
            ->orderBy('iav_nome')
            ->get(['iav_id', 'iav_nome', 'iav_fl_recuperacao']);

        return response()->json([
            'tipo_disponivel' => true,
            'periodo_aberto'  => $this->periodoAberto($uniId),
            'instrumentos'    => $instrumentos,
            'avaliacoes'      => $avaliacoes->map(fn ($a) => $this->mapAvaliacao($a)),
            'alunos'          => $alunos,
        ]);
    }

    public function storeAvaliacao(StoreAvaliacaoRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->abortIfNotLeciona($request, (int) $data['ava_tur_id'], (int) $data['ava_dis_id']);
        $this->assertTipoSerie((int) $data['ava_tur_id'], $data['ava_tipo']);
        $this->assertPeriodoAberto((int) $data['ava_uni_id']);

        $recuperacao = $this->instrumentoRecuperacao((int) $data['ava_iav_id']);
        if (! $recuperacao) {
            $this->assertSomaValores($data, null, (float) $data['ava_valor']);
        }

        $avaliacao = DiarioAvaliacao::create([
            'ava_user_id'        => (int) $request->user()->id,
            'ava_esc_id'         => $data['ava_esc_id'],
            'ava_anl_id'         => $data['ava_anl_id'],
            'ava_tur_id'         => $data['ava_tur_id'],
            'ava_dis_id'         => $data['ava_dis_id'],
            'ava_uni_id'         => $data['ava_uni_id'],
            'ava_iav_id'         => $data['ava_iav_id'],
            'ava_tipo'           => $data['ava_tipo'],
            'ava_descricao'      => $data['ava_descricao'] ?? null,
            'ava_dt'             => $data['ava_dt'],
            'ava_valor'          => $data['ava_valor'],
            'ava_fl_recuperacao' => $recuperacao,
        ]);

        return response()->json(['ok' => true, 'avaliacao' => $this->mapAvaliacao($avaliacao->load('instrumento:iav_id,iav_nome,iav_fl_recuperacao'))]);
    }

    public function updateAvaliacao(StoreAvaliacaoRequest $request, DiarioAvaliacao $avaliacao): JsonResponse
    {
        $data = $request->validated();
        $this->abortIfNotLeciona($request, (int) $avaliacao->ava_tur_id, (int) $avaliacao->ava_dis_id);
        $this->assertPeriodoAberto((int) $avaliacao->ava_uni_id);

        $recuperacao = $this->instrumentoRecuperacao((int) $data['ava_iav_id']);
        if (! $recuperacao) {
            $this->assertSomaValores([
                'ava_tur_id' => $avaliacao->ava_tur_id,
                'ava_dis_id' => $avaliacao->ava_dis_id,
                'ava_uni_id' => $avaliacao->ava_uni_id,
                'ava_tipo'   => $avaliacao->ava_tipo,
            ], (int) $avaliacao->ava_id, (float) $data['ava_valor']);
        }

        $avaliacao->update([
            'ava_iav_id'         => $data['ava_iav_id'],
            'ava_descricao'      => $data['ava_descricao'] ?? null,
            'ava_dt'             => $data['ava_dt'],
            'ava_valor'          => $data['ava_valor'],
            'ava_fl_recuperacao' => $recuperacao,
        ]);

        return response()->json(['ok' => true, 'avaliacao' => $this->mapAvaliacao($avaliacao->fresh()->load('instrumento:iav_id,iav_nome,iav_fl_recuperacao'))]);
    }

    public function destroyAvaliacao(\Illuminate\Http\Request $request, DiarioAvaliacao $avaliacao): JsonResponse
    {
        $this->abortIfNotLeciona($request, (int) $avaliacao->ava_tur_id, (int) $avaliacao->ava_dis_id);
        $this->assertPeriodoAberto((int) $avaliacao->ava_uni_id);

        DB::transaction(function () use ($avaliacao) {
            $avaliacao->notas()->delete();
            $avaliacao->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function salvarNota(SalvarNotaRequest $request): JsonResponse
    {
        $data = $request->validated();

        $avaliacao = DiarioAvaliacao::findOrFail($data['nta_ava_id']);
        $this->abortIfNotLeciona($request, (int) $avaliacao->ava_tur_id, (int) $avaliacao->ava_dis_id);
        $this->assertPeriodoAberto((int) $avaliacao->ava_uni_id);

        $valor = $data['nta_valor'] === null || $data['nta_valor'] === '' ? null : (float) $data['nta_valor'];
        if ($valor !== null && $valor > (float) $avaliacao->ava_valor) {
            throw ValidationException::withMessages([
                'nta_valor' => "A nota não pode ser maior que o valor da avaliação ({$avaliacao->ava_valor}).",
            ]);
        }

        $registro = DiarioNota::withTrashed()->updateOrCreate(
            [
                'nta_ava_id' => $avaliacao->ava_id,
                'nta_aln_id' => $data['nta_aln_id'],
            ],
            [
                'nta_valor'      => $valor,
                'nta_deleted_at' => null,
            ],
        );

        return response()->json(['ok' => true, 'nta_id' => $registro->nta_id, 'updated_at' => $registro->nta_updated_at]);
    }

    // ============ Helpers ============

    private function abortIfNotProfessor(): void
    {
        $user = request()->user();
        abort_unless($user, 403, 'Acesso restrito a professores.');
        if ($user->isAdmin()) {
            return;
        }
        abort_unless($user->hasRole('professor'), 403, 'Acesso restrito a professores.');
    }

    private function abortIfNotLeciona(\Illuminate\Http\Request $request, int $turId, int $disId): void
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return;
        }

        $funId = (int) $user->fun_id;
        abort_unless($funId, 403, 'Seu usuário não possui vínculo de funcionário.');

        $leciona = DB::table('edu_turma_professor')
            ->where('tup_fun_id', $funId)
            ->where('tup_tur_id', $turId)
            ->where('tup_dis_id', $disId)
            ->whereNull('tup_deleted_at')
            ->exists();

        abort_unless($leciona, 403, 'Você não leciona esta disciplina nesta turma.');
    }

    /** Tipos de avaliação configurados na série da turma (array de strings). */
    private function tiposSerie(int $turId): array
    {
        $raw = DB::table('edu_turma as t')
            ->join('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('t.tur_id', $turId)
            ->value('s.ser_tipo_avaliacao');

        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function assertTipoSerie(int $turId, string $tipo): void
    {
        abort_unless(
            in_array($tipo, $this->tiposSerie($turId), true),
            422,
            'Esta série não possui o tipo de avaliação selecionado configurado.'
        );
    }

    private function periodoAberto(int $uniId): bool
    {
        $uni = DB::table('cfg_unidade')
            ->where('uni_id', $uniId)
            ->first(['uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao']);

        if (! $uni || ! $uni->uni_dt_inicio || ! $uni->uni_dt_fim) {
            return false;
        }

        $hoje = now()->startOfDay();
        $inicio = Carbon::parse($uni->uni_dt_inicio)->startOfDay();
        $fim = Carbon::parse($uni->uni_dt_fim)->startOfDay()->addDays((int) $uni->uni_dias_extensao);

        return $hoje->gte($inicio) && $hoje->lte($fim);
    }

    private function assertPeriodoAberto(int $uniId): void
    {
        abort_unless(
            $this->periodoAberto($uniId),
            422,
            'Fora do período de lançamento. A edição só é permitida dentro do período selecionado (incluindo a extensão).'
        );
    }

    /**
     * Garante que a soma dos valores das avaliações regulares (mesmo contexto)
     * + o novo valor não ultrapasse 10. Recuperação não entra na soma.
     */
    private function assertSomaValores(array $ctx, ?int $exceptId, float $novoValor): void
    {
        $soma = DiarioAvaliacao::query()
            ->where('ava_tur_id', $ctx['ava_tur_id'])
            ->where('ava_dis_id', $ctx['ava_dis_id'])
            ->where('ava_uni_id', $ctx['ava_uni_id'])
            ->where('ava_tipo', $ctx['ava_tipo'])
            ->where('ava_fl_recuperacao', false)
            ->when($exceptId, fn ($q) => $q->where('ava_id', '!=', $exceptId))
            ->sum('ava_valor');

        if (round($soma + $novoValor, 2) > 10) {
            $restante = round(10 - $soma, 2);
            throw ValidationException::withMessages([
                'ava_valor' => "A soma dos valores das avaliações não pode passar de 10. Disponível: {$restante}.",
            ]);
        }
    }

    /** O instrumento define se a avaliação é de recuperação. */
    private function instrumentoRecuperacao(int $iavId): bool
    {
        $inst = InstrumentoAvaliativo::find($iavId);
        abort_unless($inst && $inst->iav_fl_ativo, 422, 'Instrumento avaliativo inválido ou inativo.');

        return (bool) $inst->iav_fl_recuperacao;
    }

    private function mapAvaliacao(DiarioAvaliacao $a): array
    {
        return [
            'ava_id'             => $a->ava_id,
            'ava_iav_id'         => $a->ava_iav_id,
            'iav_nome'           => $a->instrumento?->iav_nome,
            'ava_descricao'      => $a->ava_descricao,
            'ava_dt'             => optional($a->ava_dt)->format('Y-m-d'),
            'ava_valor'          => (float) $a->ava_valor,
            'ava_fl_recuperacao' => (bool) $a->ava_fl_recuperacao,
        ];
    }
}
