<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diario\SalvarNotaRequest;
use App\Http\Requests\Diario\StoreAvaliacaoRequest;
use App\Models\Diario\DiarioAvaliacao;
use App\Models\Diario\DiarioNota;
use App\Models\Diario\InstrumentoAvaliativo;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\Conceito;
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

        // Notas: [ava_id][aln_id] => ['valor'=>, 'cnc_id'=>]
        $notas = DiarioNota::query()
            ->whereIn('nta_ava_id', $avaIds ?: [0])
            ->get(['nta_ava_id', 'nta_aln_id', 'nta_valor', 'nta_cnc_id']);

        $notaMap = [];
        foreach ($notas as $n) {
            $notaMap[$n->nta_ava_id][$n->nta_aln_id] = [
                'valor'  => $n->nta_valor === null ? null : (float) $n->nta_valor,
                'cnc_id' => $n->nta_cnc_id,
            ];
        }

        // Alunos já com notas do OUTRO tipo nesta matéria (exclusão mútua) → bloqueados.
        $outroTipo = $tipo === DiarioAvaliacao::TIPO_CONCEITUAL
            ? DiarioAvaliacao::TIPO_NUMERICA
            : DiarioAvaliacao::TIPO_CONCEITUAL;
        $bloqueados = array_flip(
            DiarioNota::query()
                ->where(fn ($q) => $q->whereNotNull('nta_valor')->orWhereNotNull('nta_cnc_id'))
                ->whereHas('avaliacao', fn ($q) => $q
                    ->where('ava_tur_id', $turId)
                    ->where('ava_dis_id', $disId)
                    ->where('ava_tipo', $outroTipo))
                ->pluck('nta_aln_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->all()
        );

        // Ativos + alunos que saíram (tma_tas_cod_saida); frontend bloqueia as
        // avaliações com data posterior à saída (dt_saida).
        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where(function ($q) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                  ->orWhereNotNull('tma_tas_cod_saida');
            })
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->unique(fn ($m) => $m->aluno->aln_id)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values()
            ->map(function ($m) use ($avaliacoes, $notaMap, $bloqueados) {
                $notas = [];
                foreach ($avaliacoes as $a) {
                    $notas[$a->ava_id] = $notaMap[$a->ava_id][$m->aluno->aln_id] ?? ['valor' => null, 'cnc_id' => null];
                }

                return [
                    'aln_id'           => $m->aluno->aln_id,
                    'aln_nome'         => $m->aluno->aln_nome,
                    'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                    'notas'            => $notas,
                    'bloqueado'        => isset($bloqueados[$m->aluno->aln_id]),
                    'dt_saida'         => $m->tma_dt_saida?->toDateString(),
                ];
            });

        $instrumentos = InstrumentoAvaliativo::query()
            ->where('iav_fl_ativo', true)
            ->orderBy('iav_nome')
            ->get(['iav_id', 'iav_nome', 'iav_fl_recuperacao']);

        $conceitos = Conceito::query()
            ->orderBy('cnc_peso')
            ->get(['cnc_id', 'cnc_sigla', 'cnc_descricao', 'cnc_limite_inferior', 'cnc_limite_superior', 'cnc_peso']);

        return response()->json([
            'tipo_disponivel' => true,
            'periodo_aberto'  => $this->periodoAberto($uniId),
            'turma_aberta'    => $this->turmaAberta($turId),
            'modo'            => $this->conceitoModo($uniId),
            'instrumentos'    => $instrumentos,
            'conceitos'       => $conceitos,
            'avaliacoes'      => $avaliacoes->map(fn ($a) => $this->mapAvaliacao($a)),
            'alunos'          => $alunos,
        ]);
    }

    public function storeAvaliacao(StoreAvaliacaoRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->abortIfNotLeciona($request, (int) $data['ava_tur_id'], (int) $data['ava_dis_id']);
        $this->assertTurmaAberta((int) $data['ava_tur_id']);
        $this->assertTipoSerie((int) $data['ava_tur_id'], $data['ava_tipo']);
        $this->assertPeriodoAberto((int) $data['ava_uni_id']);

        $recuperacao = $this->instrumentoRecuperacao((int) $data['ava_iav_id']);
        $valor = $this->resolverValor($data['ava_tipo'], (int) $data['ava_uni_id'], $data['ava_valor'] ?? null);
        if ($valor !== null && ! $recuperacao) {
            $this->assertSomaValores($data, null, (float) $valor);
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
            'ava_valor'          => $valor,
            'ava_fl_recuperacao' => $recuperacao,
        ]);

        return response()->json(['ok' => true, 'avaliacao' => $this->mapAvaliacao($avaliacao->load('instrumento:iav_id,iav_nome,iav_fl_recuperacao'))]);
    }

    public function updateAvaliacao(StoreAvaliacaoRequest $request, DiarioAvaliacao $avaliacao): JsonResponse
    {
        $data = $request->validated();
        $this->abortIfNotLeciona($request, (int) $avaliacao->ava_tur_id, (int) $avaliacao->ava_dis_id);
        $this->assertTurmaAberta((int) $avaliacao->ava_tur_id);
        $this->assertPeriodoAberto((int) $avaliacao->ava_uni_id);

        $recuperacao = $this->instrumentoRecuperacao((int) $data['ava_iav_id']);
        $valor = $this->resolverValor($avaliacao->ava_tipo, (int) $avaliacao->ava_uni_id, $data['ava_valor'] ?? null);
        if ($valor !== null && ! $recuperacao) {
            $this->assertSomaValores([
                'ava_tur_id' => $avaliacao->ava_tur_id,
                'ava_dis_id' => $avaliacao->ava_dis_id,
                'ava_uni_id' => $avaliacao->ava_uni_id,
                'ava_tipo'   => $avaliacao->ava_tipo,
            ], (int) $avaliacao->ava_id, (float) $valor);
        }

        $avaliacao->update([
            'ava_iav_id'         => $data['ava_iav_id'],
            'ava_descricao'      => $data['ava_descricao'] ?? null,
            'ava_dt'             => $data['ava_dt'],
            'ava_valor'          => $valor,
            'ava_fl_recuperacao' => $recuperacao,
        ]);

        return response()->json(['ok' => true, 'avaliacao' => $this->mapAvaliacao($avaliacao->fresh()->load('instrumento:iav_id,iav_nome,iav_fl_recuperacao'))]);
    }

    public function destroyAvaliacao(\Illuminate\Http\Request $request, DiarioAvaliacao $avaliacao): JsonResponse
    {
        $this->abortIfNotLeciona($request, (int) $avaliacao->ava_tur_id, (int) $avaliacao->ava_dis_id);
        $this->assertTurmaAberta((int) $avaliacao->ava_tur_id);
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
        $this->assertTurmaAberta((int) $avaliacao->ava_tur_id);
        $this->assertPeriodoAberto((int) $avaliacao->ava_uni_id);

        abort_if(
            $avaliacao->ava_dt && Carbon::parse($avaliacao->ava_dt)->startOfDay()->gt(now()->startOfDay()),
            422,
            'Não é possível lançar notas em uma avaliação com data futura.'
        );

        // Bloqueia nota para aluno que saiu antes da data da avaliação.
        if ($avaliacao->ava_dt) {
            $this->assertSemSaidaNaData(
                (int) $avaliacao->ava_tur_id,
                (int) $data['nta_aln_id'],
                Carbon::parse($avaliacao->ava_dt)->toDateString(),
            );
        }

        // Exclusão mútua: aluno não pode ter notas dos dois tipos na mesma matéria.
        $this->assertSemOutroTipo(
            (int) $avaliacao->ava_tur_id,
            (int) $avaliacao->ava_dis_id,
            (int) $data['nta_aln_id'],
            $avaliacao->ava_tipo,
        );

        $modo = $this->conceitoModo((int) $avaliacao->ava_uni_id);
        $conceitoDireto = $avaliacao->ava_tipo === DiarioAvaliacao::TIPO_CONCEITUAL && $modo === 'conceito';

        if ($conceitoDireto) {
            $payload = [
                'nta_cnc_id'     => $data['nta_cnc_id'] ?? null,
                'nta_valor'      => null,
                'nta_deleted_at' => null,
            ];
        } else {
            $valor = $data['nta_valor'] === null || $data['nta_valor'] === '' ? null : (float) $data['nta_valor'];
            if ($valor !== null && $valor > (float) $avaliacao->ava_valor) {
                throw ValidationException::withMessages([
                    'nta_valor' => "A nota não pode ser maior que o valor da avaliação ({$avaliacao->ava_valor}).",
                ]);
            }
            $payload = [
                'nta_valor'      => $valor,
                'nta_cnc_id'     => null,
                'nta_deleted_at' => null,
            ];
        }

        $registro = DiarioNota::withTrashed()->updateOrCreate(
            [
                'nta_ava_id' => $avaliacao->ava_id,
                'nta_aln_id' => $data['nta_aln_id'],
            ],
            $payload,
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

    private function turmaAberta(int $turId): bool
    {
        return DB::table('edu_turma')->where('tur_id', $turId)->value('tur_situacao') === 'ABERTA';
    }

    private function assertTurmaAberta(int $turId): void
    {
        abort_unless(
            $this->turmaAberta($turId),
            422,
            'A turma não está aberta. O lançamento só é permitido com a turma aberta.'
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
            ->where('ava_fl_migrada', false)
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

    /** Modo do conceito no ano letivo da unidade: 'faixa' (número) ou 'conceito' (direto). */
    private function conceitoModo(int $uniId): string
    {
        $modo = DB::table('cfg_unidade as u')
            ->join('cfg_ano_letivo as a', 'a.anl_id', '=', 'u.uni_anl_id')
            ->where('u.uni_id', $uniId)
            ->value('a.anl_conceito_modo');

        return $modo === 'conceito' ? 'conceito' : 'faixa';
    }

    /**
     * Define o valor da avaliação conforme o tipo/modo.
     * Numérica e conceitual-faixa exigem valor; conceitual-conceito não usa valor.
     */
    private function resolverValor(string $tipo, int $uniId, $valorInput): ?float
    {
        $usaValor = $tipo === DiarioAvaliacao::TIPO_NUMERICA
            || ($tipo === DiarioAvaliacao::TIPO_CONCEITUAL && $this->conceitoModo($uniId) === 'faixa');

        if (! $usaValor) {
            return null;
        }

        $valor = $valorInput === null || $valorInput === '' ? null : (float) $valorInput;
        abort_if($valor === null || $valor <= 0, 422, 'Informe o valor da avaliação.');

        return $valor;
    }

    /** Exclusão mútua numérica × conceitual por aluno na mesma turma+disciplina. */
    private function assertSemOutroTipo(int $turId, int $disId, int $alnId, string $tipo): void
    {
        $outro = $tipo === DiarioAvaliacao::TIPO_CONCEITUAL
            ? DiarioAvaliacao::TIPO_NUMERICA
            : DiarioAvaliacao::TIPO_CONCEITUAL;

        $existe = DiarioNota::query()
            ->where('nta_aln_id', $alnId)
            ->where(fn ($q) => $q->whereNotNull('nta_valor')->orWhereNotNull('nta_cnc_id'))
            ->whereHas('avaliacao', fn ($q) => $q
                ->where('ava_tur_id', $turId)
                ->where('ava_dis_id', $disId)
                ->where('ava_tipo', $outro))
            ->exists();

        if ($existe) {
            $labels = ['numerica' => 'numérica', 'conceitual' => 'conceitual'];
            throw ValidationException::withMessages([
                'nta_valor' => "Aluno já possui notas como {$labels[$outro]} nesta matéria. Remova-as para lançar como {$labels[$tipo]}.",
            ]);
        }
    }

    private function mapAvaliacao(DiarioAvaliacao $a): array
    {
        return [
            'ava_id'             => $a->ava_id,
            'ava_iav_id'         => $a->ava_iav_id,
            'iav_nome'           => $a->instrumento?->iav_nome,
            'ava_descricao'      => $a->ava_descricao,
            'ava_dt'             => optional($a->ava_dt)->format('Y-m-d'),
            'ava_valor'          => $a->ava_valor === null ? null : (float) $a->ava_valor,
            'ava_fl_recuperacao' => (bool) $a->ava_fl_recuperacao,
            'ava_fl_migrada'     => (bool) $a->ava_fl_migrada,
        ];
    }
}
