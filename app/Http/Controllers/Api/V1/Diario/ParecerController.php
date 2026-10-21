<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\DiarioAvaliacaoDescritiva;
use App\Models\Diario\DiarioParecerInicial;
use App\Models\Matricula\Matricula;
use App\Support\Diario\DiarioEscritaGuard as Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Parecer descritivo — API v1 (app do professor).
 *
 * Espelha App\Http\Controllers\Diario\AvaliacaoDescritivaController.
 *
 * A granularidade vem da SÉRIE (`ser_tipo_avaliacao_descritiva`):
 *  · 'por_aluno'      → UM parecer por (turma, unidade, aluno); dad_dis_id NULL;
 *  · 'por_disciplina' → um por (turma, disciplina, unidade, aluno).
 *
 * Duas particularidades preservadas do web:
 *  · o bloqueio por saída usa a data de INÍCIO do período (não a data de hoje):
 *    aluno que saiu antes do período começar não recebe parecer;
 *  · o web NÃO checa turma aberta aqui, só período aberto — mantido igual para
 *    o app não ficar mais restritivo que o sistema.
 *
 * Parecer INICIAL (contextoInicial/salvarInicial): mesma granularidade, mas sem
 * unidade letiva — tabela própria, editável enquanto a turma estiver aberta, e
 * aluno que saiu da turma fica só para consulta.
 */
class ParecerController extends Controller
{
    public function contexto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'uni_id' => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
        ]);

        $turId = (int) $data['tur_id'];
        $disId = (int) $data['dis_id'];
        $uniId = (int) $data['uni_id'];

        Guard::assertRegenciaDisciplina((int) $request->user()->fun_id, $turId, $disId);

        $modo = $this->modoDaSerie($turId);

        if (! $modo) {
            return response()->json([
                'tipo_configurado' => false,
                'modo'             => null,
                'periodo_aberto'   => false,
                'turma_aberta'     => Guard::turmaAberta($turId),
                'alunos'           => [],
            ]);
        }

        // A granularidade decide onde procurar o que já foi lançado.
        $existentes = DiarioAvaliacaoDescritiva::query()
            ->where('dad_tur_id', $turId)
            ->where('dad_uni_id', $uniId)
            ->when($modo === 'por_aluno',
                fn ($q) => $q->whereNull('dad_dis_id'),
                fn ($q) => $q->where('dad_dis_id', $disId),
            )
            ->pluck('dad_descricao', 'dad_aln_id');

        $uniInicio = DB::table('cfg_unidade')->where('uni_id', $uniId)->value('uni_dt_inicio');
        $uniInicio = $uniInicio ? Carbon::parse($uniInicio)->toDateString() : null;

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where(function ($q) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                    ->orWhere(fn ($s) => $s->whereNotNull('tma_tas_cod_saida')->where('tma_tas_cod_saida', '!=', Matricula::TAS_REMANEJADO));
            })
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->unique(fn ($m) => $m->aluno->aln_id)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values()
            ->map(function ($m) use ($existentes, $uniInicio) {
                $saida = $m->tma_dt_saida?->toDateString();

                return [
                    'aln_id'           => (int) $m->aluno->aln_id,
                    'aln_nome'         => $m->aluno->aln_nome,
                    'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                    'descricao'        => (string) ($existentes[$m->aluno->aln_id] ?? ''),
                    'dt_saida'         => $saida,
                    // Saiu ANTES do período começar → não recebe parecer.
                    'bloqueado_saida'  => $saida !== null && $uniInicio !== null && $uniInicio > $saida,
                ];
            });

        return response()->json([
            'tipo_configurado' => true,
            'modo'             => $modo,
            'periodo_aberto'   => Guard::periodoAberto($uniId),
            'turma_aberta'     => Guard::turmaAberta($turId),
            'limite'           => 20000,
            'alunos'           => $alunos,
        ]);
    }

    /** Autosave do parecer de um aluno. */
    public function salvar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'esc_id'    => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'anl_id'    => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'tur_id'    => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id'    => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'uni_id'    => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'aln_id'    => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'descricao' => ['nullable', 'string', 'max:20000'],
        ]);

        $turId = (int) $data['tur_id'];
        $disId = (int) $data['dis_id'];
        $uniId = (int) $data['uni_id'];

        Guard::assertRegenciaDisciplina((int) $request->user()->fun_id, $turId, $disId);

        $modo = $this->modoDaSerie($turId);
        abort_unless(
            $modo,
            422,
            'Tipo de avaliação descritiva não configurado na série desta turma. Defina em Cadastro de Série.',
        );

        Guard::assertPeriodoAberto($uniId);

        // O corte é o INÍCIO do período, não hoje.
        $uniInicio = DB::table('cfg_unidade')->where('uni_id', $uniId)->value('uni_dt_inicio');
        if ($uniInicio) {
            $this->assertSemSaidaNaData($turId, (int) $data['aln_id'], Carbon::parse($uniInicio)->toDateString());
        }

        $registro = DiarioAvaliacaoDescritiva::updateOrCreate(
            [
                'dad_tur_id' => $turId,
                'dad_dis_id' => $modo === 'por_aluno' ? null : $disId,
                'dad_uni_id' => $uniId,
                'dad_aln_id' => (int) $data['aln_id'],
            ],
            [
                'dad_user_id'   => (int) $request->user()->id,
                'dad_esc_id'    => $data['esc_id'],
                'dad_anl_id'    => $data['anl_id'],
                'dad_descricao' => $data['descricao'] ?? null,
            ],
        );

        return response()->json(['ok' => true, 'dad_id' => (int) $registro->dad_id]);
    }

    /** Parecer inicial: roster + texto de cada aluno. Sem unidade letiva. */
    public function contextoInicial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
        ]);

        $turId = (int) $data['tur_id'];
        $disId = (int) $data['dis_id'];

        Guard::assertRegenciaDisciplina((int) $request->user()->fun_id, $turId, $disId);

        $modo = $this->modoDaSerie($turId);
        $turmaAberta = Guard::turmaAberta($turId);

        if (! $modo) {
            return response()->json([
                'tipo_configurado' => false,
                'modo'             => null,
                'turma_aberta'     => $turmaAberta,
                'alunos'           => [],
            ]);
        }

        $existentes = DiarioParecerInicial::query()
            ->where('dpr_tur_id', $turId)
            ->when($modo === 'por_aluno',
                fn ($q) => $q->whereNull('dpr_dis_id'),
                fn ($q) => $q->where('dpr_dis_id', $disId),
            )
            ->pluck('dpr_descricao', 'dpr_aln_id');

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turId)
            ->where(function ($q) {
                $q->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                    ->orWhere(fn ($s) => $s->whereNotNull('tma_tas_cod_saida')->where('tma_tas_cod_saida', '!=', Matricula::TAS_REMANEJADO));
            })
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->unique(fn ($m) => $m->aluno->aln_id)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values()
            ->map(function ($m) use ($existentes) {
                $saida = $m->tma_dt_saida?->toDateString();

                return [
                    'aln_id'           => (int) $m->aluno->aln_id,
                    'aln_nome'         => $m->aluno->aln_nome,
                    'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                    'descricao'        => (string) ($existentes[$m->aluno->aln_id] ?? ''),
                    'dt_saida'         => $saida,
                    // Saiu da turma → só consulta.
                    'bloqueado_saida'  => $saida !== null,
                ];
            });

        return response()->json([
            'tipo_configurado' => true,
            'modo'             => $modo,
            'turma_aberta'     => $turmaAberta,
            'limite'           => 20000,
            'alunos'           => $alunos,
        ]);
    }

    /** Autosave do parecer inicial de um aluno. */
    public function salvarInicial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'esc_id'    => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'anl_id'    => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'tur_id'    => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id'    => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'aln_id'    => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'descricao' => ['nullable', 'string', 'max:20000'],
        ]);

        $turId = (int) $data['tur_id'];
        $disId = (int) $data['dis_id'];
        $alnId = (int) $data['aln_id'];

        Guard::assertRegenciaDisciplina((int) $request->user()->fun_id, $turId, $disId);

        $modo = $this->modoDaSerie($turId);
        abort_unless(
            $modo,
            422,
            'Tipo de avaliação descritiva não configurado na série desta turma. Defina em Cadastro de Série.',
        );

        Guard::assertTurmaAberta($turId);

        abort_if(
            $this->dataSaidaAluno($turId, $alnId) !== null,
            422,
            'Aluno saiu da turma. O parecer inicial fica disponível apenas para consulta.',
        );

        $registro = DiarioParecerInicial::updateOrCreate(
            [
                'dpr_tur_id' => $turId,
                'dpr_dis_id' => $modo === 'por_aluno' ? null : $disId,
                'dpr_aln_id' => $alnId,
            ],
            [
                'dpr_user_id'   => (int) $request->user()->id,
                'dpr_esc_id'    => $data['esc_id'],
                'dpr_anl_id'    => $data['anl_id'],
                'dpr_descricao' => $data['descricao'] ?? null,
            ],
        );

        return response()->json(['ok' => true, 'dpr_id' => (int) $registro->dpr_id]);
    }

    /** 'por_aluno' | 'por_disciplina' | null (não configurado). */
    private function modoDaSerie(int $turId): ?string
    {
        $tipo = DB::table('edu_turma as t')
            ->join('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('t.tur_id', $turId)
            ->value('s.ser_tipo_avaliacao_descritiva');

        return $tipo ?: null;
    }
}
