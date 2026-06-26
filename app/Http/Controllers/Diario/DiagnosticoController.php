<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\DiarioDiagnostico;
use App\Models\Matricula\Matricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Avaliação por DIAGNÓSTICO (turmas regulares). Por indicador da disciplina,
 * o professor classifica cada aluno em 1 das 4 opções. Marcar "autonomia"
 * preenche os períodos seguintes (vazios) com a mesma opção — ainda editável.
 */
class DiagnosticoController extends Controller
{
    private const TIPO = 'diagnostico';

    public function contexto(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $turId = (int) $request->input('tur_id');
        $disId = (int) $request->input('dis_id');
        $uniId = (int) $request->input('uni_id');

        if (! $turId || ! $disId || ! $uniId) {
            return response()->json(['indicadores' => [], 'alunos' => [], 'valores' => [], 'opcoes' => DiarioDiagnostico::OPCOES, 'tipo_disponivel' => false, 'periodo_aberto' => false, 'turma_aberta' => false]);
        }

        $this->abortIfNotLeciona($request, $turId, $disId);

        $serId = (int) DB::table('edu_turma')->where('tur_id', $turId)->value('tur_ser_id');

        $indicadores = DB::table('edu_serie_indicador')
            ->where('ind_ser_id', $serId)
            ->where('ind_dis_id', $disId)
            ->where('ind_fl_ativo', true)
            ->whereNull('ind_deleted_at')
            ->orderBy('ind_id')
            ->get(['ind_id', 'ind_descricao']);

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where(function ($q) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)->orWhereNotNull('tma_tas_cod_saida');
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
            ]);

        $valores = DiarioDiagnostico::query()
            ->where('dgn_tur_id', $turId)
            ->where('dgn_dis_id', $disId)
            ->where('dgn_uni_id', $uniId)
            ->get(['dgn_aln_id', 'dgn_ind_id', 'dgn_opcao'])
            ->map(fn ($r) => ['aln_id' => (int) $r->dgn_aln_id, 'ind_id' => (int) $r->dgn_ind_id, 'opcao' => $r->dgn_opcao]);

        return response()->json([
            'indicadores'     => $indicadores,
            'alunos'          => $alunos,
            'valores'         => $valores,
            'opcoes'          => DiarioDiagnostico::OPCOES,
            'tipo_disponivel' => in_array(self::TIPO, $this->tiposSerie($turId), true),
            'periodo_aberto'  => $this->periodoAberto($uniId),
            'turma_aberta'    => $this->turmaAberta($turId),
        ]);
    }

    public function salvar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'uni_id' => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'aln_id' => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'ind_id' => ['required', 'integer', 'exists:edu_serie_indicador,ind_id'],
            'opcao'  => ['nullable', Rule::in(array_keys(DiarioDiagnostico::OPCOES))],
        ]);

        $this->abortIfNotProfessor();
        $this->abortIfNotLeciona($request, (int) $data['tur_id'], (int) $data['dis_id']);
        $this->assertTipoSerie((int) $data['tur_id']);
        $this->assertTurmaAberta((int) $data['tur_id']);
        $this->assertPeriodoAberto((int) $data['uni_id']);

        $turma = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->first(['tur_esc_id', 'tur_anl_id']);
        $uni = DB::table('cfg_unidade')->where('uni_id', $data['uni_id'])->first(['uni_numero', 'uni_anl_id']);

        $chave = [
            'dgn_tur_id' => $data['tur_id'],
            'dgn_dis_id' => $data['dis_id'],
            'dgn_uni_id' => $data['uni_id'],
            'dgn_aln_id' => $data['aln_id'],
            'dgn_ind_id' => $data['ind_id'],
        ];

        // Opção vazia → remove o registro.
        if (empty($data['opcao'])) {
            DiarioDiagnostico::where($chave)->delete();

            return response()->json(['ok' => true, 'removido' => true]);
        }

        DB::transaction(function () use ($chave, $data, $turma, $uni) {
            DiarioDiagnostico::updateOrCreate($chave, [
                'dgn_user_id' => (int) auth()->id(),
                'dgn_esc_id'  => $turma->tur_esc_id,
                'dgn_anl_id'  => $turma->tur_anl_id,
                'dgn_opcao'   => $data['opcao'],
            ]);

            // "Realiza com autonomia" → preenche períodos seguintes (vazios) com a mesma opção.
            if ($data['opcao'] === DiarioDiagnostico::OPCAO_AUTONOMIA) {
                $seguintes = DB::table('cfg_unidade')
                    ->where('uni_anl_id', $uni->uni_anl_id)
                    ->where('uni_numero', '>', $uni->uni_numero)
                    ->pluck('uni_id');

                foreach ($seguintes as $uniSeg) {
                    $existe = DiarioDiagnostico::where('dgn_tur_id', $data['tur_id'])
                        ->where('dgn_dis_id', $data['dis_id'])
                        ->where('dgn_uni_id', $uniSeg)
                        ->where('dgn_aln_id', $data['aln_id'])
                        ->where('dgn_ind_id', $data['ind_id'])
                        ->exists();
                    if ($existe) {
                        continue; // não sobrescreve edições já feitas
                    }
                    DiarioDiagnostico::create(array_merge($chave, [
                        'dgn_uni_id'  => $uniSeg,
                        'dgn_user_id' => (int) auth()->id(),
                        'dgn_esc_id'  => $turma->tur_esc_id,
                        'dgn_anl_id'  => $turma->tur_anl_id,
                        'dgn_opcao'   => DiarioDiagnostico::OPCAO_AUTONOMIA,
                    ]));
                }
            }
        });

        return response()->json(['ok' => true]);
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

    private function abortIfNotLeciona(Request $request, int $turId, int $disId): void
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

    private function assertTipoSerie(int $turId): void
    {
        abort_unless(in_array(self::TIPO, $this->tiposSerie($turId), true), 422, 'Esta série não possui avaliação por diagnóstico configurada.');
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
        $inicio = \Illuminate\Support\Carbon::parse($uni->uni_dt_inicio)->startOfDay();
        $fim = \Illuminate\Support\Carbon::parse($uni->uni_dt_fim)->startOfDay()->addDays((int) $uni->uni_dias_extensao);

        return $hoje->gte($inicio) && $hoje->lte($fim);
    }

    private function assertPeriodoAberto(int $uniId): void
    {
        abort_unless($this->periodoAberto($uniId), 422, 'Fora do período de lançamento (inclui a extensão).');
    }
}
