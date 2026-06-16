<?php

namespace App\Http\Controllers\Diario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diario\SalvarAvaliacaoDescritivaRequest;
use App\Models\Diario\DiarioAvaliacaoDescritiva;
use App\Models\Matricula\Matricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lançamento de avaliação descritiva por aluno.
 * Granularidade: turma + disciplina + unidade (bimestre/trimestre) + aluno.
 * Autosave por aluno (upsert individual).
 */
class AvaliacaoDescritivaController extends Controller
{
    /**
     * Roster da turma (alunos ATIVOS) + a descrição já lançada de cada um
     * para o contexto (turma/disciplina/unidade).
     */
    public function alunos(Request $request): JsonResponse
    {
        $this->abortIfNotProfessor();

        $turId = (int) $request->input('tur_id');
        $disId = (int) $request->input('dis_id');
        $uniId = (int) $request->input('uni_id');

        if (! $turId || ! $disId || ! $uniId) {
            return response()->json(['modo' => 'por_disciplina', 'periodo_aberto' => false, 'alunos' => []]);
        }

        $this->abortIfNotLeciona($request, $turId, $disId);

        // Sem tipo de avaliação descritiva configurado na série => não há regra: bloqueia.
        $tipo = $this->tipoDescritivaSerie($turId);
        if (! $tipo) {
            return response()->json([
                'tipo_configurado' => false,
                'modo'             => null,
                'periodo_aberto'   => false,
                'alunos'           => [],
            ]);
        }

        $modo = $tipo === 'por_aluno' ? 'por_aluno' : 'por_disciplina';

        // Lançamentos já existentes para o contexto, conforme a granularidade da série.
        $existentesQuery = DiarioAvaliacaoDescritiva::query()
            ->where('dad_tur_id', $turId)
            ->where('dad_uni_id', $uniId);

        $modo === 'por_aluno'
            ? $existentesQuery->whereNull('dad_dis_id')
            : $existentesQuery->where('dad_dis_id', $disId);

        $existentes = $existentesQuery->pluck('dad_descricao', 'dad_aln_id');

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values()
            ->map(fn ($m) => [
                'aln_id'           => $m->aluno->aln_id,
                'aln_nome'         => $m->aluno->aln_nome,
                'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                'descricao'        => (string) ($existentes[$m->aluno->aln_id] ?? ''),
            ]);

        return response()->json([
            'tipo_configurado' => true,
            'modo'             => $modo,
            'periodo_aberto'   => $this->periodoAberto($uniId),
            'alunos'           => $alunos,
        ]);
    }

    /**
     * Upsert da avaliação de um aluno (autosave). Chave única:
     * turma + disciplina + unidade + aluno.
     */
    public function salvar(SalvarAvaliacaoDescritivaRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->abortIfNotLeciona($request, (int) $data['dad_tur_id'], (int) $data['dad_dis_id']);

        // A série precisa ter o tipo de avaliação descritiva configurado.
        $tipo = $this->tipoDescritivaSerie((int) $data['dad_tur_id']);
        abort_unless(
            $tipo,
            422,
            'Tipo de avaliação descritiva não configurado na série desta turma. Defina em Cadastro de Série.'
        );

        // Só grava se hoje estiver dentro do período selecionado (+ extensão).
        abort_unless(
            $this->periodoAberto((int) $data['dad_uni_id']),
            422,
            'Fora do período de lançamento. A edição só é permitida dentro do período selecionado (incluindo a extensão).'
        );

        // "por_aluno" => 1 registro por período (disciplina nula); "por_disciplina" => usa a disciplina.
        $disId = $tipo === 'por_aluno' ? null : (int) $data['dad_dis_id'];

        $registro = DiarioAvaliacaoDescritiva::updateOrCreate(
            [
                'dad_tur_id' => $data['dad_tur_id'],
                'dad_dis_id' => $disId,
                'dad_uni_id' => $data['dad_uni_id'],
                'dad_aln_id' => $data['dad_aln_id'],
            ],
            [
                'dad_user_id'   => (int) $request->user()->id,
                'dad_esc_id'    => $data['dad_esc_id'],
                'dad_anl_id'    => $data['dad_anl_id'],
                'dad_descricao' => $data['dad_descricao'] ?? null,
            ],
        );

        return response()->json([
            'ok'         => true,
            'dad_id'     => $registro->dad_id,
            'updated_at' => $registro->dad_updated_at,
        ]);
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

    /**
     * Tipo de avaliação descritiva configurado na série da turma
     * ('por_aluno' | 'por_disciplina') ou null se não configurado.
     */
    private function tipoDescritivaSerie(int $turId): ?string
    {
        $tipo = DB::table('edu_turma as t')
            ->join('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('t.tur_id', $turId)
            ->value('s.ser_tipo_avaliacao_descritiva');

        return $tipo ?: null;
    }

    /**
     * Hoje está dentro do período (uni_dt_inicio .. uni_dt_fim + uni_dias_extensao)?
     * Só nessa janela é permitido gravar.
     */
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

    /**
     * Garante que o professor leciona a disciplina na turma (lotação real).
     * Admin passa direto.
     */
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
}
