<?php

namespace App\Http\Controllers\Encerramento;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Confirmação de Renovação — a partir das turmas ENCERRADAS, matricula no ano
 * seguinte os alunos que marcaram interesse em renovar (tma_fl_renovado = true).
 * Aprovado → série de promoção (ser_promo_*); Reprovado → série de conservação
 * (ser_cons_*). A nova matrícula referencia a origem (tma_origem_tma_id).
 */
class ConfirmacaoRenovacaoController extends Controller
{
    /** Situações de saída consideradas APROVAÇÃO (promoção). Demais = conservação. */
    private const APROVADO = [6, 7];

    /** Situação de entrada da matrícula renovada: Renovado(a). */
    private const TAS_RENOVADO = 17;

    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('confirmacao-renovacao/Index', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    /** Turmas ENCERRADAS do ano/escola + segmentos (p/ o filtro) + se o próximo ano existe. */
    public function turmas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);
        $this->autorizarEscola((int) $data['esc_id']);

        $ano  = AnoLetivo::findOrFail($data['anl_id']);
        $prox = AnoLetivo::where('anl_ano', $ano->anl_ano + 1)->first(['anl_id', 'anl_ano']);

        $turmas = DB::table('edu_turma as t')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->leftJoin('edu_segmento as seg', 'seg.seg_id', '=', 's.seg_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->where('t.tur_situacao', 'ENCERRADA')
            ->where('t.tur_anl_id', $data['anl_id'])
            ->where('t.tur_esc_id', $data['esc_id'])
            ->orderBy('seg.seg_ordem')->orderBy('s.ser_nome')->orderBy('t.tur_nome')
            ->get(['t.tur_id', 't.tur_nome', 's.ser_nome', 's.seg_id', 'seg.seg_nome_reduzido']);

        $segmentos = $turmas->filter(fn ($t) => $t->seg_id !== null)->unique('seg_id')->sortBy('seg_nome_reduzido')
            ->map(fn ($t) => ['seg_id' => (int) $t->seg_id, 'seg_nome' => $t->seg_nome_reduzido])->values();

        return response()->json([
            'tem_prox_ano' => (bool) $prox,
            'prox_ano'     => $prox?->anl_ano,
            'segmentos'    => $segmentos,
            'turmas'       => $turmas->map(fn ($t) => [
                'tur_id'   => (int) $t->tur_id,
                'turma'    => $t->tur_nome,
                'serie'    => $t->ser_nome,
                'seg_id'   => $t->seg_id !== null ? (int) $t->seg_id : null,
                'segmento' => $t->seg_nome_reduzido,
            ])->values(),
        ]);
    }

    /** Alunos da turma que querem renovar + resultado + séries de destino + se já renovado. */
    public function alunos(Request $request): JsonResponse
    {
        $data = $request->validate(['tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id']]);

        $tur = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->first(['tur_esc_id', 'tur_ser_id']);
        abort_unless($tur, 404);
        $this->autorizarEscola((int) $tur->tur_esc_id);

        // Todos os alunos ENCERRADOS (têm resultado). Os que marcaram interesse em renovar
        // são o lote padrão; os demais aparecem indicados e podem ser renovados manualmente.
        $matriculas = Matricula::query()
            ->where('tma_tur_id', $data['tur_id'])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNotNull('tma_dt_encerramento')
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula', 'situacaoSaida:tas_cod,tas_descricao,tas_descricao_enturmacao')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values();

        // Séries de promoção/conservação por série envolvida.
        $serIds = $matriculas->map(fn ($m) => (int) ($m->tma_ser_id ?? $tur->tur_ser_id))->unique()->values()->all();
        $series = DB::table('edu_serie')->whereIn('ser_id', $serIds ?: [0])
            ->get(['ser_id', 'ser_promo_ser_id_1', 'ser_promo_ser_id_2', 'ser_cons_ser_id_1', 'ser_cons_ser_id_2'])
            ->keyBy('ser_id');
        $nomes = DB::table('edu_serie')->pluck('ser_nome', 'ser_id');

        // Matrículas filhas já criadas (renovação feita) por origem.
        $filhasPorOrigem = Matricula::whereIn('tma_origem_tma_id', $matriculas->pluck('tma_id'))
            ->whereNull('tma_deleted_at')
            ->get(['tma_origem_tma_id', 'tma_tur_id', 'tma_ser_id'])
            ->keyBy('tma_origem_tma_id');

        $alunos = $matriculas->map(function (Matricula $m) use ($tur, $series, $nomes, $filhasPorOrigem) {
            $serId    = (int) ($m->tma_ser_id ?? $tur->tur_ser_id);
            $cod      = $m->tma_tas_cod_saida !== null ? (int) $m->tma_tas_cod_saida : null;
            $aprovado = $cod !== null && in_array($cod, self::APROVADO, true);
            $s        = $series->get($serId);

            $destIds = $aprovado
                ? [$s->ser_promo_ser_id_1 ?? null, $s->ser_promo_ser_id_2 ?? null]
                : [$s->ser_cons_ser_id_1 ?? null, $s->ser_cons_ser_id_2 ?? null];
            $destinos = collect($destIds)->filter()->unique()->map(fn ($id) => ['ser_id' => (int) $id, 'ser_nome' => $nomes[$id] ?? ('Série '.$id)])->values();

            $filha = $filhasPorOrigem->get($m->tma_id);

            return [
                'tma_id'         => (int) $m->tma_id,
                'aln_id'         => (int) $m->aluno->aln_id,
                'nome'           => $m->aluno->aln_nome,
                'matricula'      => $m->aluno->aln_nr_matricula,
                'situacao_final' => $m->situacaoSaida?->tas_descricao_enturmacao ?: ($m->situacaoSaida?->tas_descricao ?? '—'),
                'quer_renovar'   => $m->tma_fl_renovado === null ? null : (bool) $m->tma_fl_renovado,
                'aprovado'       => $aprovado,
                'destinos'       => $destinos,
                'ja_renovado'    => (bool) $filha,
                'destino_ser_id' => $filha ? (int) $filha->tma_ser_id : null,
                'destino_tur_id' => $filha ? (int) $filha->tma_tur_id : null,
            ];
        });

        return response()->json(['alunos' => $alunos]);
    }

    /** Turmas disponíveis (ano seguinte) de uma série de destino, na escola da turma de origem. */
    public function turmasDestino(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_origem_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'ser_id'        => ['required', 'integer', 'exists:edu_serie,ser_id'],
        ]);

        $org = DB::table('edu_turma')->where('tur_id', $data['tur_origem_id'])->first(['tur_esc_id', 'tur_anl_id']);
        abort_unless($org, 404);
        $this->autorizarEscola((int) $org->tur_esc_id);

        $prox = $this->proximoAno((int) $org->tur_anl_id);
        if (! $prox) {
            return response()->json(['turmas' => [], 'tem_prox_ano' => false]);
        }

        $turmas = DB::table('edu_turma as t')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->where('t.tur_anl_id', $prox->anl_id)
            ->where('t.tur_esc_id', $org->tur_esc_id)
            ->where('t.tur_ser_id', $data['ser_id'])
            ->orderBy('t.tur_nome')
            ->get(['t.tur_id', 't.tur_nome', 's.ser_nome'])
            ->map(fn ($t) => ['tur_id' => (int) $t->tur_id, 'turma' => $t->tur_nome, 'serie' => $t->ser_nome]);

        return response()->json(['turmas' => $turmas, 'tem_prox_ano' => true, 'prox_ano' => $prox->anl_ano]);
    }

    /** Confirma a renovação de 1 aluno: cria a matrícula no ano seguinte. */
    public function confirmar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origem_tma_id' => ['required', 'integer', 'exists:edu_turma_aluno,tma_id'],
            'ser_id'        => ['required', 'integer', 'exists:edu_serie,ser_id'],
            'tur_destino_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
        ]);

        $origem = Matricula::where('tma_id', $data['origem_tma_id'])->whereNull('tma_deleted_at')->first();
        abort_unless($origem, 404);

        $turOrigem = DB::table('edu_turma')->where('tur_id', $origem->tma_tur_id)->first(['tur_esc_id', 'tur_anl_id']);
        abort_unless($turOrigem, 404);
        $this->autorizarEscola((int) $turOrigem->tur_esc_id);

        abort_if($origem->tma_dt_encerramento === null, 422, 'A matrícula de origem não está encerrada.');

        if (Matricula::where('tma_origem_tma_id', $origem->tma_id)->whereNull('tma_deleted_at')->exists()) {
            return response()->json(['ok' => false, 'message' => 'Este aluno já foi renovado.'], 422);
        }

        $prox = $this->proximoAno((int) $turOrigem->tur_anl_id);
        if (! $prox) {
            return response()->json(['ok' => false, 'message' => 'O ano letivo seguinte ainda não existe.'], 422);
        }

        // Turma destino: do ano seguinte, mesma escola e série escolhida.
        $turDest = DB::table('edu_turma')->where('tur_id', $data['tur_destino_id'])->whereNull('tur_deleted_at')->first(['tur_id', 'tur_esc_id', 'tur_anl_id', 'tur_ser_id']);
        abort_unless($turDest, 404);
        abort_if((int) $turDest->tur_anl_id !== (int) $prox->anl_id, 422, 'A turma destino não é do ano seguinte.');
        abort_if((int) $turDest->tur_esc_id !== (int) $turOrigem->tur_esc_id, 422, 'A turma destino é de outra escola.');
        abort_if((int) $turDest->tur_ser_id !== (int) $data['ser_id'], 422, 'A turma destino não é da série escolhida.');

        $nova = $this->criarMatriculaRenovacao($origem, (int) $data['ser_id'], $turDest, (int) $prox->anl_id, (int) $request->user()->id);

        return response()->json(['ok' => true, 'tma_id' => (int) $nova->tma_id, 'tur_destino_id' => (int) $turDest->tur_id, 'ser_id' => (int) $data['ser_id']]);
    }

    /**
     * Confirma em LOTE: matricula no destino todos os alunos pendentes da turma de origem
     * cuja série de destino é a escolhida (mesmo da turma destino). Pergunta vinda da tela.
     */
    public function confirmarLote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_origem_id'  => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'ser_id'         => ['required', 'integer', 'exists:edu_serie,ser_id'],
            'tur_destino_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
        ]);

        $turOrigem = DB::table('edu_turma')->where('tur_id', $data['tur_origem_id'])->whereNull('tur_deleted_at')->first(['tur_id', 'tur_esc_id', 'tur_anl_id', 'tur_ser_id']);
        abort_unless($turOrigem, 404);
        $this->autorizarEscola((int) $turOrigem->tur_esc_id);

        $prox = $this->proximoAno((int) $turOrigem->tur_anl_id);
        if (! $prox) {
            return response()->json(['ok' => false, 'message' => 'O ano letivo seguinte ainda não existe.'], 422);
        }

        $turDest = DB::table('edu_turma')->where('tur_id', $data['tur_destino_id'])->whereNull('tur_deleted_at')->first(['tur_id', 'tur_esc_id', 'tur_anl_id', 'tur_ser_id']);
        abort_unless($turDest, 404);
        abort_if((int) $turDest->tur_anl_id !== (int) $prox->anl_id, 422, 'A turma destino não é do ano seguinte.');
        abort_if((int) $turDest->tur_esc_id !== (int) $turOrigem->tur_esc_id, 422, 'A turma destino é de outra escola.');
        abort_if((int) $turDest->tur_ser_id !== (int) $data['ser_id'], 422, 'A turma destino não é da série escolhida.');

        $matriculas = Matricula::where('tma_tur_id', $turOrigem->tur_id)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNotNull('tma_dt_encerramento')
            ->whereNull('tma_deleted_at')
            ->get();

        $jaRenovado = Matricula::whereIn('tma_origem_tma_id', $matriculas->pluck('tma_id'))->whereNull('tma_deleted_at')->pluck('tma_origem_tma_id')->flip();

        $serIds = $matriculas->map(fn ($m) => (int) ($m->tma_ser_id ?? $turOrigem->tur_ser_id))->unique()->values()->all();
        $series = DB::table('edu_serie')->whereIn('ser_id', $serIds ?: [0])->get(['ser_id', 'ser_promo_ser_id_1', 'ser_promo_ser_id_2', 'ser_cons_ser_id_1', 'ser_cons_ser_id_2'])->keyBy('ser_id');

        $serId = (int) $data['ser_id'];
        $criadas = 0;
        DB::transaction(function () use ($matriculas, $jaRenovado, $series, $turOrigem, $turDest, $prox, $serId, $request, &$criadas) {
            foreach ($matriculas as $m) {
                if (isset($jaRenovado[$m->tma_id])) {
                    continue;
                }
                $sid = (int) ($m->tma_ser_id ?? $turOrigem->tur_ser_id);
                $cod = $m->tma_tas_cod_saida !== null ? (int) $m->tma_tas_cod_saida : null;
                $aprovado = $cod !== null && in_array($cod, self::APROVADO, true);
                $s = $series->get($sid);
                $destIds = $aprovado
                    ? [$s->ser_promo_ser_id_1 ?? null, $s->ser_promo_ser_id_2 ?? null]
                    : [$s->ser_cons_ser_id_1 ?? null, $s->ser_cons_ser_id_2 ?? null];
                $destIds = array_map('intval', array_filter($destIds, fn ($x) => $x !== null));

                // Só inclui quem PODE ir para a série escolhida.
                if (! in_array($serId, $destIds, true)) {
                    continue;
                }

                $this->criarMatriculaRenovacao($m, $serId, $turDest, (int) $prox->anl_id, (int) $request->user()->id);
                $criadas++;
            }
        });

        return response()->json(['ok' => true, 'criadas' => $criadas]);
    }

    private function criarMatriculaRenovacao(Matricula $origem, int $serId, object $turDest, int $proxAnlId, int $userId): Matricula
    {
        return Matricula::create([
            'tma_aln_id'          => $origem->tma_aln_id,
            'tma_tur_id'          => $turDest->tur_id,
            'tma_ser_id'          => $serId,
            'tma_anl_id'          => $proxAnlId,
            'tma_modalidade'      => $origem->tma_modalidade,
            'tma_situacao'        => Matricula::SITUACAO_ATIVA,
            'tma_tas_cod_entrada' => self::TAS_RENOVADO,
            'tma_dt_matricula'    => Carbon::now()->toDateString(),
            'tma_origem_tma_id'   => $origem->tma_id,
            'tma_created_by_id'   => $userId,
        ]);
    }

    private function proximoAno(int $anlId): ?object
    {
        $ano = AnoLetivo::find($anlId);

        return $ano ? AnoLetivo::where('anl_ano', $ano->anl_ano + 1)->first(['anl_id', 'anl_ano']) : null;
    }

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
