<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\AeeAvaliacao;
use App\Models\Matricula\Matricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Avaliações descritivas do AEE — o professor escolhe o aluno, registra uma data
 * e escreve uma descrição em texto rico (negrito/itálico/listas), até 2500 caracteres.
 * Várias avaliações por aluno, vinculadas à unidade (bimestre/trimestre).
 */
class AeeAvaliacaoController extends Controller
{
    private const MAX_TEXTO = 2500;

    public function contexto(Request $request): JsonResponse
    {
        $this->abortIfNotProfessorAee();

        $turId = (int) $request->input('tur_id');
        $uniId = (int) $request->input('uni_id');

        if (! $turId || ! $uniId) {
            return response()->json(['alunos' => [], 'avaliacoes' => [], 'periodo_aberto' => false, 'turma_aberta' => false]);
        }

        $this->assertProfessorDaTurmaAee($request, $turId);

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
            ]);

        $avaliacoes = AeeAvaliacao::query()
            ->where('dav_tur_id', $turId)
            ->where('dav_uni_id', $uniId)
            ->orderByDesc('dav_dt')
            ->orderByDesc('dav_id')
            ->get(['dav_id', 'dav_aln_id', 'dav_dt', 'dav_descricao'])
            ->map(fn ($a) => [
                'dav_id'        => $a->dav_id,
                'aln_id'        => (int) $a->dav_aln_id,
                'dt'            => Carbon::parse($a->dav_dt)->toDateString(),
                'descricao'     => $a->dav_descricao,
            ]);

        return response()->json([
            'alunos'         => $alunos,
            'avaliacoes'     => $avaliacoes,
            'periodo_aberto' => $this->periodoAberto($uniId),
            'turma_aberta'   => $this->turmaAberta($turId),
        ]);
    }

    public function salvar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dav_id'    => ['nullable', 'integer', 'exists:edu_diario_aee_avaliacao,dav_id'],
            'tur_id'    => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'uni_id'    => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'aln_id'    => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'dt'        => ['required', 'date'],
            'descricao' => ['required', 'string', 'max:20000'],
        ], [], [
            'aln_id'    => 'aluno',
            'dt'        => 'data',
            'descricao' => 'descrição',
        ]);

        // Limite de 2500 caracteres de TEXTO (ignora as tags HTML de formatação).
        $textoPuro = trim(html_entity_decode(strip_tags($data['descricao']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($textoPuro === '') {
            throw ValidationException::withMessages(['descricao' => 'A descrição é obrigatória.']);
        }
        if (mb_strlen($textoPuro) > self::MAX_TEXTO) {
            throw ValidationException::withMessages(['descricao' => 'A descrição excede o limite de '.self::MAX_TEXTO.' caracteres.']);
        }

        $this->abortIfNotProfessorAee();
        $this->assertProfessorDaTurmaAee($request, (int) $data['tur_id']);
        $this->assertTurmaAberta((int) $data['tur_id']);
        $this->assertPeriodoAberto((int) $data['uni_id']);
        $this->assertDataNoPeriodo((int) $data['uni_id'], $data['dt']);

        $turma = DB::table('edu_turma')->where('tur_id', $data['tur_id'])->first(['tur_esc_id', 'tur_anl_id']);

        if (! empty($data['dav_id'])) {
            $av = AeeAvaliacao::where('dav_id', $data['dav_id'])->where('dav_tur_id', $data['tur_id'])->firstOrFail();
            $av->update([
                'dav_aln_id'    => $data['aln_id'],
                'dav_uni_id'    => $data['uni_id'],
                'dav_dt'        => $data['dt'],
                'dav_descricao' => $data['descricao'],
            ]);
        } else {
            AeeAvaliacao::create([
                'dav_user_id'   => (int) $request->user()->id,
                'dav_esc_id'    => $turma->tur_esc_id,
                'dav_anl_id'    => $turma->tur_anl_id,
                'dav_tur_id'    => $data['tur_id'],
                'dav_uni_id'    => $data['uni_id'],
                'dav_aln_id'    => $data['aln_id'],
                'dav_dt'        => $data['dt'],
                'dav_descricao' => $data['descricao'],
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, AeeAvaliacao $avaliacao): JsonResponse
    {
        $this->abortIfNotProfessorAee();
        $this->assertProfessorDaTurmaAee($request, (int) $avaliacao->dav_tur_id);
        $this->assertTurmaAberta((int) $avaliacao->dav_tur_id);
        $this->assertPeriodoAberto((int) $avaliacao->dav_uni_id);

        $avaliacao->delete();

        return response()->json(['ok' => true]);
    }

    // ============ Helpers (espelham AeeFrequenciaController) ============

    private function abortIfNotProfessorAee(): void
    {
        $user = request()->user();
        abort_unless($user, 403, 'Acesso restrito.');
        if ($user->isAdmin()) {
            return;
        }
        abort_unless($user->hasRole('professor_aee'), 403, 'Acesso restrito ao professor AEE.');
    }

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
}
