<?php

namespace App\Http\Controllers\Api\V1\Diario;

use App\Http\Controllers\Controller;
use App\Models\Diario\DiarioDiagnostico;
use App\Models\Matricula\Matricula;
use App\Support\Diario\DiarioEscritaGuard as Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Diagnóstico por indicador — API v1 (app do professor).
 *
 * Espelha App\Http\Controllers\Diario\DiagnosticoController, com o escopo
 * estrito da v1 (lotação real via DiarioEscritaGuard, sem bypass de admin).
 *
 * Comportamentos preservados do web — NÃO "consertar" sem decisão de produto:
 *  · marcar 'autonomia' propaga para os períodos SEGUINTES do mesmo ano, mas
 *    SOMENTE onde ainda não há registro (qualquer opção já lançada bloqueia);
 *  · a propagação é unidirecional: desmarcar depois NÃO desfaz o que já foi
 *    propagado;
 *  · opção vazia REMOVE a célula (soft delete) e não propaga nada;
 *  · a propagação segue `uni_numero`, não o calendário.
 */
class DiagnosticoController extends Controller
{
    /** Grade aluno × indicador de (turma, disciplina, unidade). */
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

        $disponivel = $this->serieTemDiagnostico($turId);

        if (! $disponivel) {
            return response()->json([
                'tipo_disponivel' => false,
                'periodo_aberto'  => false,
                'turma_aberta'    => Guard::turmaAberta($turId),
                'opcoes'          => $this->opcoes(),
                'indicadores'     => [],
                'alunos'          => [],
                'valores'         => [],
            ]);
        }

        $serId = (int) DB::table('edu_turma')->where('tur_id', $turId)->value('tur_ser_id');

        // Indicadores são da SÉRIE + DISCIPLINA e valem para todas as unidades.
        $indicadores = DB::table('edu_serie_indicador')
            ->where('ind_ser_id', $serId)
            ->where('ind_dis_id', $disId)
            ->where('ind_fl_ativo', true)
            ->whereNull('ind_deleted_at')
            ->orderBy('ind_id')
            ->get(['ind_id', 'ind_descricao'])
            ->map(fn ($i) => ['ind_id' => (int) $i->ind_id, 'ind_descricao' => $i->ind_descricao])
            ->values();

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
            ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values()
            ->map(fn ($m) => [
                'aln_id'           => (int) $m->aluno->aln_id,
                'aln_nome'         => $m->aluno->aln_nome,
                'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
            ]);

        // Lista ESPARSA: só as células preenchidas. Ausência = não avaliado.
        $valores = DiarioDiagnostico::query()
            ->where('dgn_tur_id', $turId)
            ->where('dgn_dis_id', $disId)
            ->where('dgn_uni_id', $uniId)
            ->get(['dgn_aln_id', 'dgn_ind_id', 'dgn_opcao'])
            ->map(fn ($v) => [
                'aln_id' => (int) $v->dgn_aln_id,
                'ind_id' => (int) $v->dgn_ind_id,
                'opcao'  => $v->dgn_opcao,
            ])
            ->values();

        return response()->json([
            'tipo_disponivel' => true,
            'periodo_aberto'  => Guard::periodoAberto($uniId),
            'turma_aberta'    => Guard::turmaAberta($turId),
            // Rótulos vêm do servidor: hardcodar no app quebraria em silêncio.
            'opcoes'          => $this->opcoes(),
            'indicadores'     => $indicadores,
            'alunos'          => $alunos,
            'valores'         => $valores,
        ]);
    }

    /** Autosave de UMA célula (aluno × indicador). */
    public function salvarCelula(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['required', 'integer', 'exists:edu_disciplina,dis_id'],
            'uni_id' => ['required', 'integer', 'exists:cfg_unidade,uni_id'],
            'aln_id' => ['required', 'integer', 'exists:edu_aluno,aln_id'],
            'ind_id' => ['required', 'integer', 'exists:edu_serie_indicador,ind_id'],
            'opcao'  => ['nullable', Rule::in(array_keys(DiarioDiagnostico::OPCOES))],
        ]);

        $turId = (int) $data['tur_id'];
        $disId = (int) $data['dis_id'];
        $uniId = (int) $data['uni_id'];

        Guard::assertRegenciaDisciplina((int) $request->user()->fun_id, $turId, $disId);
        abort_unless($this->serieTemDiagnostico($turId), 422, 'Esta série não possui avaliação por diagnóstico configurada.');
        Guard::assertTurmaAberta($turId);
        Guard::assertPeriodoAberto($uniId);

        $chave = [
            'dgn_tur_id' => $turId,
            'dgn_dis_id' => $disId,
            'dgn_uni_id' => $uniId,
            'dgn_aln_id' => (int) $data['aln_id'],
            'dgn_ind_id' => (int) $data['ind_id'],
        ];

        // Opção vazia = remoção. Não propaga e não desfaz propagação anterior.
        if (blank($data['opcao'] ?? null)) {
            DiarioDiagnostico::where($chave)->delete();

            return response()->json(['ok' => true, 'removido' => true, 'propagado_para' => []]);
        }

        $turma = DB::table('edu_turma')->where('tur_id', $turId)->first(['tur_esc_id', 'tur_anl_id']);

        $propagado = DB::transaction(function () use ($chave, $data, $turma, $request, $uniId) {
            DiarioDiagnostico::updateOrCreate($chave, [
                'dgn_opcao'   => $data['opcao'],
                'dgn_user_id' => (int) $request->user()->id,
                'dgn_esc_id'  => $turma->tur_esc_id,
                'dgn_anl_id'  => $turma->tur_anl_id,
            ]);

            if ($data['opcao'] !== DiarioDiagnostico::OPCAO_AUTONOMIA) {
                return [];
            }

            return $this->propagarAutonomia($chave, $turma, $request, $uniId);
        });

        return response()->json(['ok' => true, 'propagado_para' => $propagado]);
    }

    // ============ Helpers ============

    /**
     * Preenche os períodos SEGUINTES do mesmo ano com 'autonomia', somente
     * onde a célula está vazia. Devolve os uni_id afetados para o app poder
     * invalidar o cache daqueles períodos (o web não devolve isso).
     */
    private function propagarAutonomia(array $chave, object $turma, Request $request, int $uniId): array
    {
        $atual = DB::table('cfg_unidade')->where('uni_id', $uniId)->first(['uni_anl_id', 'uni_numero']);
        if (! $atual) {
            return [];
        }

        $seguintes = DB::table('cfg_unidade')
            ->where('uni_anl_id', $atual->uni_anl_id)
            ->where('uni_numero', '>', $atual->uni_numero)
            ->orderBy('uni_numero')
            ->pluck('uni_id');

        $afetados = [];

        foreach ($seguintes as $proximo) {
            $existe = DiarioDiagnostico::where(array_merge($chave, ['dgn_uni_id' => (int) $proximo]))->exists();
            if ($existe) {
                continue; // já avaliado ali: não sobrescreve
            }

            DiarioDiagnostico::create(array_merge($chave, [
                'dgn_uni_id'  => (int) $proximo,
                'dgn_opcao'   => DiarioDiagnostico::OPCAO_AUTONOMIA,
                'dgn_user_id' => (int) $request->user()->id,
                'dgn_esc_id'  => $turma->tur_esc_id,
                'dgn_anl_id'  => $turma->tur_anl_id,
            ]));

            $afetados[] = (int) $proximo;
        }

        return $afetados;
    }

    private function serieTemDiagnostico(int $turId): bool
    {
        $bruto = DB::table('edu_turma as t')
            ->join('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('t.tur_id', $turId)
            ->value(DB::raw('s.ser_tipo_avaliacao::text'));

        $tipos = json_decode((string) $bruto, true);

        return is_array($tipos) && in_array('diagnostico', $tipos, true);
    }

    /** Ordem das chaves = ordem de exibição esperada. */
    private function opcoes(): array
    {
        return collect(DiarioDiagnostico::OPCOES)
            ->map(fn ($rotulo, $valor) => ['valor' => $valor, 'rotulo' => $rotulo])
            ->values()
            ->all();
    }
}
