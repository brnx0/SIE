<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\DiarioAula;
use App\Models\Diario\DiarioConteudo;
use App\Models\Diario\DiarioFalta;
use App\Models\Diario\DiarioPlanoAula;
use App\Models\Matricula\Matricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lançamento de frequência (faltas/presenças) no Diário de Classe.
 * Por tempo do quadro de horário: o professor marca apenas os tempos onde está
 * lotado (edu_turma_horario.trh_fun_id). Unidocente fica em todos os tempos.
 * Sem seleção de disciplina (vem do slot).
 */
class FaltaController extends Controller
{
    public function contexto(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $turId = (int) $request->input('tur_id');
        $uniId = (int) $request->input('uni_id');

        if (! $turId || ! $uniId) {
            return response()->json(['tempos' => [], 'alunos' => [], 'presencas' => [], 'periodo' => null, 'periodo_aberto' => false, 'turma_aberta' => false]);
        }

        $user  = $request->user();
        $funId = (int) $user->fun_id;

        // TODOS os tempos da turma; o professor edita só os seus (pode_editar).
        $tempos = DB::table('edu_turma_horario as trh')
            ->leftJoin('edu_disciplina as d', 'd.dis_id', '=', 'trh.trh_dis_id')
            ->leftJoin('edu_funcionario as f', 'f.fun_id', '=', 'trh.trh_fun_id')
            ->where('trh.trh_tur_id', $turId)
            ->whereNull('trh.trh_deleted_at')
            ->orderBy('trh.trh_dia')
            ->orderBy('trh.trh_tempo')
            ->get(['trh.trh_id', 'trh.trh_dia', 'trh.trh_tempo', 'trh.trh_hora', 'trh.trh_dis_id', 'd.dis_nome', 'trh.trh_fun_id', 'f.fun_nome'])
            ->map(fn ($t) => [
                'trh_id'      => (int) $t->trh_id,
                'trh_dia'     => $t->trh_dia,
                'trh_tempo'   => (int) $t->trh_tempo,
                'trh_hora'    => $t->trh_hora,
                'trh_dis_id'  => $t->trh_dis_id,
                'dis_nome'    => $t->dis_nome,
                'fun_nome'    => $t->fun_nome,
                'pode_editar' => $user->isAdmin() || (int) $t->trh_fun_id === $funId,
            ]);

        // Janela do período (inclui extensão) para a lista de datas no frontend.
        $uni = DB::table('cfg_unidade')->where('uni_id', $uniId)->first(['uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao']);
        $periodo = $uni && $uni->uni_dt_inicio && $uni->uni_dt_fim ? [
            'dt_inicio' => Carbon::parse($uni->uni_dt_inicio)->toDateString(),
            'dt_fim'    => Carbon::parse($uni->uni_dt_fim)->addDays((int) $uni->uni_dias_extensao)->toDateString(),
        ] : null;

        // Sábados letivos do período que valem para a escola desta turma (fora os em exceção).
        // Cada um espelha um dia útil (1=Seg..5=Sex) → frontend usa os tempos daquele dia.
        $turmaInfo = DB::table('edu_turma')->where('tur_id', $turId)->first(['tur_esc_id', 'tur_anl_id']);
        $sabadosLetivos = [];
        if ($periodo && $turmaInfo) {
            $diaRef = [1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex'];
            $sabadosLetivos = DB::table('cfg_sabado_letivo as s')
                ->where('s.sbl_anl_id', $turmaInfo->tur_anl_id)
                ->whereBetween('s.sbl_dt_sabado', [$periodo['dt_inicio'], $periodo['dt_fim']])
                ->whereNotExists(function ($q) use ($turmaInfo) {
                    $q->select(DB::raw(1))
                      ->from('cfg_sabado_letivo_excecao as e')
                      ->whereColumn('e.sle_sbl_id', 's.sbl_id')
                      ->where('e.sle_esc_id', $turmaInfo->tur_esc_id);
                })
                ->orderBy('s.sbl_dt_sabado')
                ->get(['s.sbl_dt_sabado', 's.sbl_dia_semana'])
                ->map(fn ($r) => [
                    'dt'      => Carbon::parse($r->sbl_dt_sabado)->toDateString(),
                    'dia_ref' => $diaRef[(int) $r->sbl_dia_semana] ?? null,
                ])
                ->filter(fn ($x) => $x['dia_ref'] !== null)
                ->values();
        }

        // Ativos + alunos que saíram (tma_tas_cod_saida) — estes aparecem para
        // lançar o que é anterior à saída; o frontend bloqueia após tma_dt_saida.
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

        // Presenças já lançadas no período, p/ os tempos visíveis.
        $presencas = [];
        if ($tempos->isNotEmpty()) {
            $presencas = DB::table('edu_diario_aula as a')
                ->join('edu_diario_falta as f', 'f.fal_aul_id', '=', 'a.aul_id')
                ->where('a.aul_tur_id', $turId)
                ->where('a.aul_uni_id', $uniId)
                ->whereIn('a.aul_trh_id', $tempos->pluck('trh_id'))
                ->whereNull('a.aul_deleted_at')
                ->whereNull('f.fal_deleted_at')
                ->get(['a.aul_trh_id', 'a.aul_dt', 'f.fal_aln_id', 'f.fal_fl_presente'])
                ->map(fn ($r) => [
                    'trh_id'   => (int) $r->aul_trh_id,
                    'dt'       => Carbon::parse($r->aul_dt)->toDateString(),
                    'aln_id'   => (int) $r->fal_aln_id,
                    'presente' => (bool) $r->fal_fl_presente,
                ]);
        }

        // Justificativas de falta (intervalo por aluno) da turma — sinaliza no diário
        // que a falta foi justificada, com o motivo e se ele abona a falta.
        $justificativas = DB::table('edu_justificativa_falta as j')
            ->leftJoin('cfg_motivo_baixa_frequencia as m', 'm.mbf_id', '=', 'j.jfa_mbf_id')
            ->where('j.jfa_tur_id', $turId)
            ->whereNull('j.jfa_deleted_at')
            ->get(['j.jfa_aln_id', 'j.jfa_dt_inicio', 'j.jfa_dt_fim', 'm.mbf_descricao', 'm.mbf_fl_abona'])
            ->map(fn ($r) => [
                'aln_id'    => (int) $r->jfa_aln_id,
                'dt_inicio' => Carbon::parse($r->jfa_dt_inicio)->toDateString(),
                'dt_fim'    => Carbon::parse($r->jfa_dt_fim)->toDateString(),
                'motivo'    => $r->mbf_descricao,
                'abona'     => (bool) $r->mbf_fl_abona,
            ]);

        // Conteúdo/metodologia por (data, disciplina).
        $conteudos = DB::table('edu_diario_conteudo')
            ->where('dco_tur_id', $turId)
            ->where('dco_uni_id', $uniId)
            ->whereNull('dco_deleted_at')
            ->get(['dco_dt', 'dco_dis_id', 'dco_conteudo', 'dco_metodologia', 'dco_fl_plano_executado', 'dco_dpa_id'])
            ->map(fn ($r) => [
                'dt'              => Carbon::parse($r->dco_dt)->toDateString(),
                'dis_id'          => $r->dco_dis_id !== null ? (int) $r->dco_dis_id : null,
                'conteudo'        => $r->dco_conteudo,
                'metodologia'     => $r->dco_metodologia,
                'plano_executado' => (bool) $r->dco_fl_plano_executado,
                'dpa_id'          => $r->dco_dpa_id !== null ? (int) $r->dco_dpa_id : null,
            ]);

        // Planos de aula elegíveis (pendente/aprovado) da turma — base do "planejamento executado".
        // Frontend casa por disciplina + data dentro do período do plano.
        $planosQ = DB::table('edu_diario_plano_aula')
            ->where('dpa_tur_id', $turId)
            ->whereIn('dpa_status', [DiarioPlanoAula::STATUS_PENDENTE, DiarioPlanoAula::STATUS_APROVADO])
            ->whereNull('dpa_deleted_at');
        if (! $user->isAdmin()) {
            $planosQ->where('dpa_user_id', (int) $user->id);
        }
        $planos = $planosQ
            ->orderBy('dpa_dt_inicio')
            ->get(['dpa_id', 'dpa_dis_id', 'dpa_dt_inicio', 'dpa_dt_fim', 'dpa_objeto_conhecimento', 'dpa_estrategias'])
            ->map(fn ($p) => [
                'dpa_id'      => (int) $p->dpa_id,
                'dis_id'      => (int) $p->dpa_dis_id,
                'dt_inicio'   => Carbon::parse($p->dpa_dt_inicio)->toDateString(),
                'dt_fim'      => Carbon::parse($p->dpa_dt_fim)->toDateString(),
                'conteudo'    => $p->dpa_objeto_conhecimento,
                'metodologia' => $p->dpa_estrategias,
            ]);

        return response()->json([
            'tempos'         => $tempos,
            'alunos'         => $alunos,
            'periodo'        => $periodo,
            'periodo_aberto' => $this->periodoAberto($uniId),
            'turma_aberta'   => $this->turmaAberta($turId),
            'presencas'      => $presencas,
            'conteudos'      => $conteudos,
            'planos'         => $planos,
            'sabados_letivos' => $sabadosLetivos,
            'justificativas' => $justificativas,
        ]);
    }

    /**
     * Salva conteúdo/metodologia por (turma + data + disciplina).
     * Se "planejamento executado", espelha (snapshot) o conteúdo do plano de aula
     * elegível (pendente/aprovado) que cobre a data — campos travados no cliente.
     */
    public function salvarConteudo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'          => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'          => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'dis_id'          => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'dt'              => ['required', 'date'],
            'conteudo'        => ['nullable', 'string', 'max:5000'],
            'metodologia'     => ['nullable', 'string', 'max:5000'],
            'plano_executado' => ['boolean'],
            'dpa_id'          => ['nullable', 'integer'],
        ]);

        $this->abortIfNotProfessor();
        $this->assertLotadoNaTurma($request, (int) $data['tur_id']);
        $this->assertDisciplinaDoProfessor($request, (int) $data['tur_id'], (int) $data['dis_id']);
        $this->assertTurmaAberta((int) $data['tur_id']);
        $this->assertPeriodoAberto((int) $data['uni_id']);
        $this->assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);

        $executado   = (bool) ($data['plano_executado'] ?? false);
        $conteudo    = $data['conteudo'] ?? null;
        $metodologia = $data['metodologia'] ?? null;
        $dpaId       = null;

        if ($executado) {
            // Travado: conteúdo/metodologia vêm do plano (snapshot), não do cliente.
            $plano       = $this->planoExecutavel($request, (int) $data['tur_id'], (int) $data['dis_id'], $data['dt'], $data['dpa_id'] ?? null);
            $dpaId       = (int) $plano->dpa_id;
            $conteudo    = $plano->dpa_objeto_conhecimento;
            $metodologia = $plano->dpa_estrategias;
        }

        DiarioConteudo::updateOrCreate(
            ['dco_tur_id' => $data['tur_id'], 'dco_dt' => $data['dt'], 'dco_dis_id' => $data['dis_id']],
            [
                'dco_user_id'            => (int) $request->user()->id,
                'dco_uni_id'             => $data['uni_id'],
                'dco_conteudo'           => $conteudo,
                'dco_metodologia'        => $metodologia,
                'dco_fl_plano_executado' => $executado,
                'dco_dpa_id'             => $dpaId,
            ],
        );

        return response()->json([
            'ok'              => true,
            'plano_executado' => $executado,
            'dpa_id'          => $dpaId,
            'conteudo'        => $conteudo,
            'metodologia'     => $metodologia,
        ]);
    }

    /** Marca presença/ausência de 1 aluno em 1 tempo/data (autosave). */
    public function salvarPresenca(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'   => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'   => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'trh_id'   => ['required', 'integer', 'exists:edu_turma_horario,trh_id'],
            'aln_id'   => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'dt'       => ['required', 'date'],
            'presente' => ['required', 'boolean'],
        ]);

        $this->abortIfNotProfessor();
        $slot = $this->slotDoProfessor($request, (int) $data['tur_id'], (int) $data['trh_id']);
        $this->assertTurmaAberta((int) $data['tur_id']);
        $this->assertPeriodoAberto((int) $data['uni_id']);
        $this->assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);
        $this->assertSemSaidaNaData((int) $data['tur_id'], (int) $data['aln_id'], $data['dt']);

        $aula = $this->resolverAula($request, $data, $slot);

        DiarioFalta::updateOrCreate(
            ['fal_aul_id' => $aula->aul_id, 'fal_aln_id' => $data['aln_id']],
            ['fal_fl_presente' => (bool) $data['presente']],
        );

        return response()->json(['ok' => true]);
    }

    /** Marca todos os alunos ativos da turma em 1 tempo/data (presença em lote). */
    public function salvarLote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id'   => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'   => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'trh_id'   => ['required', 'integer', 'exists:edu_turma_horario,trh_id'],
            'dt'       => ['required', 'date'],
            'presente' => ['required', 'boolean'],
        ]);

        $this->abortIfNotProfessor();
        $slot = $this->slotDoProfessor($request, (int) $data['tur_id'], (int) $data['trh_id']);
        $this->assertTurmaAberta((int) $data['tur_id']);
        $this->assertPeriodoAberto((int) $data['uni_id']);
        $this->assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);

        $aula = $this->resolverAula($request, $data, $slot);

        // Ativos + alunos que ainda não tinham saído na data do lote.
        $alnIds = Matricula::query()
            ->where('tma_tur_id', $data['tur_id'])
            ->whereNull('tma_deleted_at')
            ->where(function ($q) use ($data) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                  ->orWhere(function ($w) use ($data) {
                      $w->whereNotNull('tma_tas_cod_saida')
                        ->whereDate('tma_dt_saida', '>=', $data['dt']);
                  });
            })
            ->pluck('tma_aln_id');

        DB::transaction(function () use ($alnIds, $aula, $data) {
            foreach ($alnIds as $alnId) {
                DiarioFalta::updateOrCreate(
                    ['fal_aul_id' => $aula->aul_id, 'fal_aln_id' => $alnId],
                    ['fal_fl_presente' => (bool) $data['presente']],
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    // ============ Helpers ============

    /** Cria/recupera a aula (turma, tempo, data). */
    private function resolverAula(Request $request, array $data, object $slot): DiarioAula
    {
        $turma = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->first(['tur_esc_id', 'tur_anl_id']);

        return DiarioAula::updateOrCreate(
            [
                'aul_tur_id' => $data['tur_id'],
                'aul_trh_id' => $data['trh_id'],
                'aul_dt'     => $data['dt'],
            ],
            [
                'aul_user_id' => (int) $request->user()->id,
                'aul_esc_id'  => $turma->tur_esc_id,
                'aul_anl_id'  => $turma->tur_anl_id,
                'aul_uni_id'  => $data['uni_id'],
                'aul_dis_id'  => $slot->trh_dis_id,
            ],
        );
    }

    /** Garante que o professor está lotado em algum tempo da turma (ou admin). */
    private function assertLotadoNaTurma(Request $request, int $turId): void
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return;
        }
        $funId = (int) $user->fun_id;
        $tem = $funId && DB::table('edu_turma_horario')
            ->where('trh_tur_id', $turId)
            ->where('trh_fun_id', $funId)
            ->whereNull('trh_deleted_at')
            ->exists();
        abort_unless($tem, 403, 'Você não está lotado nesta turma.');
    }

    /** Garante que o professor leciona a disciplina nesta turma (tem slot) — admin passa. */
    private function assertDisciplinaDoProfessor(Request $request, int $turId, int $disId): void
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return;
        }
        $funId = (int) $user->fun_id;
        $tem = $funId && DB::table('edu_turma_horario')
            ->where('trh_tur_id', $turId)
            ->where('trh_dis_id', $disId)
            ->where('trh_fun_id', $funId)
            ->whereNull('trh_deleted_at')
            ->exists();
        abort_unless($tem, 403, 'Você não leciona esta disciplina nesta turma.');
    }

    /** Localiza/valida o plano elegível (pendente/aprovado) que cobre a data p/ a disciplina. */
    private function planoExecutavel(Request $request, int $turId, int $disId, string $dt, ?int $dpaId): object
    {
        $user = $request->user();
        $d = Carbon::parse($dt)->toDateString();

        $plano = DB::table('edu_diario_plano_aula')
            ->where('dpa_tur_id', $turId)
            ->where('dpa_dis_id', $disId)
            ->whereIn('dpa_status', [DiarioPlanoAula::STATUS_PENDENTE, DiarioPlanoAula::STATUS_APROVADO])
            ->whereDate('dpa_dt_inicio', '<=', $d)
            ->whereDate('dpa_dt_fim', '>=', $d)
            ->whereNull('dpa_deleted_at')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('dpa_user_id', (int) $user->id))
            ->when($dpaId, fn ($q) => $q->where('dpa_id', $dpaId))
            ->orderBy('dpa_dt_inicio')
            ->first(['dpa_id', 'dpa_objeto_conhecimento', 'dpa_estrategias']);

        abort_unless($plano, 422, 'Não há planejamento (pendente/aprovado) para esta disciplina nesta data.');

        return $plano;
    }

    /** Garante que o tempo pertence ao professor (ou admin) e à turma. */
    private function slotDoProfessor(Request $request, int $turId, int $trhId): object
    {
        $slot = DB::table('edu_turma_horario')
            ->where('trh_id', $trhId)
            ->where('trh_tur_id', $turId)
            ->whereNull('trh_deleted_at')
            ->first(['trh_id', 'trh_fun_id', 'trh_dis_id', 'trh_dia', 'trh_tempo']);

        abort_unless($slot, 404, 'Tempo não encontrado nesta turma.');

        $user = $request->user();
        if (! $user->isAdmin()) {
            $funId = (int) $user->fun_id;
            abort_unless($funId && (int) $slot->trh_fun_id === $funId, 403, 'Você não está lotado neste tempo.');
        }

        return $slot;
    }

    private function abortIfNotProfessor(): void
    {
        $user = request()->user();
        abort_unless($user, 403, 'Acesso restrito a professores.');
        if ($user->isAdmin()) {
            return;
        }
        abort_unless($user->hasRole('professor'), 403, 'Acesso restrito a professores.');
    }

    private function turmaAberta(int $turId): bool
    {
        return DB::table('edu_turma')->where('tur_id', $turId)->value('tur_situacao') === 'ABERTA';
    }

    private function assertTurmaAberta(int $turId): void
    {
        abort_unless($this->turmaAberta($turId), 422, 'A turma não está aberta. O lançamento só é permitido com a turma aberta.');
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
        abort_unless($this->periodoAberto($uniId), 422, 'Fora do período de lançamento. A edição só é permitida dentro do período selecionado (incluindo a extensão).');
    }

    /** A data lançada deve estar dentro do período (incluindo extensão). */
    private function assertDataNoPeriodo(int $uniId, string $dt): void
    {
        $uni = DB::table('cfg_unidade')->where('uni_id', $uniId)->first(['uni_dt_inicio', 'uni_dt_fim', 'uni_dias_extensao']);
        abort_unless($uni && $uni->uni_dt_inicio && $uni->uni_dt_fim, 422, 'Período inválido.');

        $d = Carbon::parse($dt)->toDateString();
        $inicio = Carbon::parse($uni->uni_dt_inicio)->toDateString();
        $fim = Carbon::parse($uni->uni_dt_fim)->addDays((int) $uni->uni_dias_extensao)->toDateString();

        abort_unless($d >= $inicio && $d <= $fim, 422, 'A data está fora do período selecionado.');
    }
}
