<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\AtividadeFrequencia;
use App\Models\Matricula\Matricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Frequência das turmas de ATIVIDADE — chamada por dia de atendimento
 * (dias da semana da turma). Espelha AeeFrequenciaController.
 */
class AtividadeFrequenciaController extends Controller
{
    private const DOW = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];

    public function contexto(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $turId = (int) $request->input('tur_id');
        $uniId = (int) $request->input('uni_id');

        if (! $turId || ! $uniId) {
            return response()->json(['dias' => [], 'alunos' => [], 'presencas' => [], 'periodo' => null, 'periodo_aberto' => false, 'turma_aberta' => false]);
        }

        $this->assertProfessorDaTurma($request, $turId);

        $turma = DB::table('edu_turma')->where('tur_id', $turId)->first(['tur_dias_funcionamento']);
        $dias = $turma ? (json_decode((string) $turma->tur_dias_funcionamento, true) ?: []) : [];

        $uni = DB::table('cfg_unidade')->where('uni_id', $uniId)->first(['uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao']);
        $periodo = $uni && $uni->uni_dt_inicio && $uni->uni_dt_fim ? [
            'dt_inicio' => Carbon::parse($uni->uni_dt_inicio)->toDateString(),
            'dt_fim'    => Carbon::parse($uni->uni_dt_fim)->addDays((int) $uni->uni_dias_extensao)->toDateString(),
        ] : null;

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

        $presencas = DB::table('edu_diario_atividade_frequencia')
            ->where('atf_tur_id', $turId)
            ->where('atf_uni_id', $uniId)
            ->whereNull('atf_deleted_at')
            ->get(['atf_aln_id', 'atf_dt', 'atf_fl_presente'])
            ->map(fn ($r) => [
                'aln_id'   => (int) $r->atf_aln_id,
                'dt'       => Carbon::parse($r->atf_dt)->toDateString(),
                'presente' => (bool) $r->atf_fl_presente,
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

    public function salvarPresenca(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'   => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'   => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'aln_id'   => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'dt'       => ['required', 'date'],
            'presente' => ['required', 'boolean'],
        ]);

        $this->abortIfNotProfessor();
        $this->assertProfessorDaTurma($request, (int) $data['tur_id']);
        $this->assertTurmaAberta((int) $data['tur_id']);
        $this->assertPeriodoAberto((int) $data['uni_id']);
        $this->assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);
        $this->assertDiaDeAtendimento((int) $data['tur_id'], $data['dt']);

        $this->upsert($request, $data, (int) $data['aln_id'], (bool) $data['presente']);

        return response()->json(['ok' => true]);
    }

    public function salvarLote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'   => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'   => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'dt'       => ['required', 'date'],
            'presente' => ['required', 'boolean'],
        ]);

        $this->abortIfNotProfessor();
        $this->assertProfessorDaTurma($request, (int) $data['tur_id']);
        $this->assertTurmaAberta((int) $data['tur_id']);
        $this->assertPeriodoAberto((int) $data['uni_id']);
        $this->assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);
        $this->assertDiaDeAtendimento((int) $data['tur_id'], $data['dt']);

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

        AtividadeFrequencia::updateOrCreate(
            ['atf_tur_id' => $data['tur_id'], 'atf_aln_id' => $alnId, 'atf_dt' => $data['dt']],
            [
                'atf_user_id'     => (int) $request->user()->id,
                'atf_esc_id'      => $turma->tur_esc_id,
                'atf_anl_id'      => $turma->tur_anl_id,
                'atf_uni_id'      => $data['uni_id'],
                'atf_fl_presente' => $presente,
            ],
        );
    }

    private function abortIfNotProfessor(): void
    {
        $user = request()->user();
        abort_unless($user, 403, 'Acesso restrito.');
        if ($user->isAdmin()) {
            return;
        }
        abort_unless($user->hasRole('professor'), 403, 'Acesso restrito ao professor/monitor.');
    }

    /** Professor/monitor deve estar vinculado à turma de atividade (tup_dis_id NULL). Admin passa. */
    private function assertProfessorDaTurma(Request $request, int $turId): void
    {
        $modAtiv = DB::table('edu_turma')->where('tur_id', $turId)->value('tur_modalidade') === 'ATIVIDADE';
        abort_unless($modAtiv, 422, 'Turma não é de atividade.');

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
        abort_unless($tem, 403, 'Você não atende esta turma de atividade.');
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

    private function assertDiaDeAtendimento(int $turId, string $dt): void
    {
        $dias = json_decode((string) DB::table('edu_turma')->where('tur_id', $turId)->value('tur_dias_funcionamento'), true) ?: [];
        $code = self::DOW[(int) Carbon::parse($dt)->dayOfWeek] ?? null;
        abort_unless($code && in_array($code, $dias, true), 422, 'A data não é um dia de atendimento desta turma.');
    }
}
