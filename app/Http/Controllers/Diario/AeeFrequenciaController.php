<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\AeeFrequencia;
use App\Models\Matricula\Matricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Frequência do AEE — chamada por DIA de atendimento (dias da semana da turma AEE).
 * Separada da frequência da turma regular (tabela edu_diario_aee_frequencia).
 */
class AeeFrequenciaController extends Controller
{
    private const DOW = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];

    public function contexto(Request $request): JsonResponse
    {
        $this->abortIfNotProfessorAee();

        $turId = (int) $request->input('tur_id');
        $uniId = (int) $request->input('uni_id');

        if (! $turId || ! $uniId) {
            return response()->json(['dias' => [], 'alunos' => [], 'presencas' => [], 'periodo' => null, 'periodo_aberto' => false, 'turma_aberta' => false]);
        }

        $this->assertProfessorDaTurmaAee($request, $turId);

        $turma = DB::table('edu_turma')->where('tur_id', $turId)->first(['tur_dias_funcionamento']);
        $dias = $turma ? (json_decode((string) $turma->tur_dias_funcionamento, true) ?: []) : [];

        $uni = DB::table('cfg_unidade')->where('uni_id', $uniId)->first(['uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao']);
        $periodo = $uni && $uni->uni_dt_inicio && $uni->uni_dt_fim ? [
            'dt_inicio' => Carbon::parse($uni->uni_dt_inicio)->toDateString(),
            'dt_fim'    => Carbon::parse($uni->uni_dt_fim)->addDays((int) $uni->uni_dias_extensao)->toDateString(),
        ] : null;

        // Alunos da turma AEE: ativos + os que saíram (frontend bloqueia após a saída).
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
            ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values()
            ->map(fn ($m) => [
                'aln_id'           => $m->aluno->aln_id,
                'aln_nome'         => $m->aluno->aln_nome,
                'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                'dt_saida'         => $m->tma_dt_saida?->toDateString(),
            ]);

        $presencas = DB::table('edu_diario_aee_frequencia')
            ->where('afr_tur_id', $turId)
            ->where('afr_uni_id', $uniId)
            ->whereNull('afr_deleted_at')
            ->get(['afr_aln_id', 'afr_dt', 'afr_fl_presente'])
            ->map(fn ($r) => [
                'aln_id'   => (int) $r->afr_aln_id,
                'dt'       => Carbon::parse($r->afr_dt)->toDateString(),
                'presente' => (bool) $r->afr_fl_presente,
            ]);

        return response()->json([
            'dias'           => array_values($dias),
            'periodo'        => $periodo,
            'periodo_aberto' => $this->periodoAberto($uniId),
            'turma_aberta'   => $this->turmaAberta($turId),
            'alunos'         => $alunos,
            'presencas'      => $presencas,
        ]);
    }

    /** Marca presença/ausência de 1 aluno em 1 dia (autosave). */
    public function salvarPresenca(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'   => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'   => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'aln_id'   => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'dt'       => ['required', 'date'],
            'presente' => ['required', 'boolean'],
        ]);

        $this->abortIfNotProfessorAee();
        $this->assertProfessorDaTurmaAee($request, (int) $data['tur_id']);
        $this->assertTurmaAberta((int) $data['tur_id']);
        $this->assertPeriodoAberto((int) $data['uni_id']);
        $this->assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);
        $this->assertDiaDeAtendimento((int) $data['tur_id'], $data['dt']);
        $this->assertSemSaidaNaData((int) $data['tur_id'], (int) $data['aln_id'], $data['dt']);

        $this->upsert($request, $data, (int) $data['aln_id'], (bool) $data['presente']);

        return response()->json(['ok' => true]);
    }

    /** Marca todos os alunos ativos da turma em 1 dia (lote). */
    public function salvarLote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'   => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'   => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'dt'       => ['required', 'date'],
            'presente' => ['required', 'boolean'],
        ]);

        $this->abortIfNotProfessorAee();
        $this->assertProfessorDaTurmaAee($request, (int) $data['tur_id']);
        $this->assertTurmaAberta((int) $data['tur_id']);
        $this->assertPeriodoAberto((int) $data['uni_id']);
        $this->assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);
        $this->assertDiaDeAtendimento((int) $data['tur_id'], $data['dt']);

        // Ativos + os que ainda não tinham saído na data.
        $alnIds = Matricula::query()
            ->where('tma_tur_id', $data['tur_id'])
            ->whereNull('tma_deleted_at')
            ->where(function ($q) use ($data) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                  ->orWhere(function ($w) use ($data) {
                      $w->whereNotNull('tma_tas_cod_saida')->whereDate('tma_dt_saida', '>=', $data['dt']);
                  });
            })
            ->pluck('tma_aln_id')
            ->unique();

        DB::transaction(function () use ($alnIds, $request, $data) {
            foreach ($alnIds as $alnId) {
                $this->upsert($request, $data, (int) $alnId, (bool) $data['presente']);
            }
        });

        return response()->json(['ok' => true]);
    }

    // ============ Helpers ============

    private function upsert(Request $request, array $data, int $alnId, bool $presente): void
    {
        $turma = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->first(['tur_esc_id', 'tur_anl_id']);

        AeeFrequencia::updateOrCreate(
            ['afr_tur_id' => $data['tur_id'], 'afr_aln_id' => $alnId, 'afr_dt' => $data['dt']],
            [
                'afr_user_id'     => (int) $request->user()->id,
                'afr_esc_id'      => $turma->tur_esc_id,
                'afr_anl_id'      => $turma->tur_anl_id,
                'afr_uni_id'      => $data['uni_id'],
                'afr_fl_presente' => $presente,
            ],
        );
    }

    private function abortIfNotProfessorAee(): void
    {
        $user = request()->user();
        abort_unless($user, 403, 'Acesso restrito.');
        if ($user->isAdmin()) {
            return;
        }
        abort_unless($user->hasRole('professor_aee'), 403, 'Acesso restrito ao professor AEE.');
    }

    /** Professor deve estar vinculado à turma AEE (tup_dis_id NULL). Admin passa. */
    private function assertProfessorDaTurmaAee(Request $request, int $turId): void
    {
        $modAee = DB::table('edu_turma')->where('tur_id', $turId)->value('tur_modalidade') === 'AEE';
        abort_unless($modAee, 422, 'Turma não é de AEE.');

        $user = $request->user();
        if ($user->isAdmin()) {
            return;
        }
        $funId = (int) $user->fun_id;
        $tem = $funId && DB::table('edu_turma_professor')
            ->where('tup_tur_id', $turId)
            ->where('tup_fun_id', $funId)
            ->whereNull('tup_dis_id')
            ->whereNull('tup_deleted_at')
            ->exists();
        abort_unless($tem, 403, 'Você não atende esta turma de AEE.');
    }

    private function turmaAberta(int $turId): bool
    {
        return DB::table('edu_turma')->where('tur_id', $turId)->value('tur_situacao') === 'ABERTA';
    }

    private function assertTurmaAberta(int $turId): void
    {
        abort_unless($this->turmaAberta($turId), 422, 'A turma não está aberta.');
    }

    private function periodoAberto(int $uniId): bool
    {
        $uni = DB::table('cfg_unidade')->where('uni_id', $uniId)->first(['uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao']);
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
        abort_unless($this->periodoAberto($uniId), 422, 'Fora do período de lançamento (inclui a extensão).');
    }

    private function assertDataNoPeriodo(int $uniId, string $dt): void
    {
        $uni = DB::table('cfg_unidade')->where('uni_id', $uniId)->first(['uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao']);
        abort_unless($uni && $uni->uni_dt_inicio && $uni->uni_dt_fim, 422, 'Período inválido.');

        $d = Carbon::parse($dt)->toDateString();
        $inicio = Carbon::parse($uni->uni_dt_inicio)->toDateString();
        $fim = Carbon::parse($uni->uni_dt_fim)->addDays((int) $uni->uni_dias_extensao)->toDateString();
        abort_unless($d >= $inicio && $d <= $fim, 422, 'A data está fora do período selecionado.');
    }

    /** A data precisa cair num dia de atendimento da turma AEE. */
    private function assertDiaDeAtendimento(int $turId, string $dt): void
    {
        $dias = json_decode((string) DB::table('edu_turma')->where('tur_id', $turId)->value('tur_dias_funcionamento'), true) ?: [];
        $code = self::DOW[(int) Carbon::parse($dt)->dayOfWeek] ?? null;
        abort_unless($code && in_array($code, $dias, true), 422, 'A data não é um dia de atendimento desta turma.');
    }
}
