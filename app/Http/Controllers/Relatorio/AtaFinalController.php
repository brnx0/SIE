<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Relatorio\Concerns\NotaLookups;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\CalculoNota;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ata Final do Encerramento — por turma ENCERRADA: matriz alunos × disciplinas
 * com a nota final de cada disciplina + o resultado final do aluno (situação de
 * encerramento). Aluno que saiu da turma é listado sem resultado final.
 */
class AtaFinalController extends Controller
{
    use NotaLookups;

    /** Situações de saída = APROVAÇÃO (para destaque). */
    private const APROVADO = [6, 7];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/AtaFinal/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    /** Turmas ENCERRADAS do ano/escola (a ata só vale para turma encerrada). */
    public function turmasEncerradas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);
        $this->autorizarEscola((int) $data['esc_id']);

        $turmas = DB::table('edu_turma as t')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->where('t.tur_situacao', 'ENCERRADA')
            ->where('t.tur_anl_id', $data['anl_id'])
            ->where('t.tur_esc_id', $data['esc_id'])
            ->orderBy('s.ser_nome')->orderBy('t.tur_nome')
            ->get(['t.tur_id', 't.tur_nome', 's.ser_nome'])
            ->map(fn ($t) => ['tur_id' => (int) $t->tur_id, 'turma' => trim(($t->ser_nome ? $t->ser_nome . ' - ' : '') . $t->tur_nome)]);

        return response()->json(['turmas' => $turmas]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
        ]);

        $this->autorizarTurma((int) $data['tur_id']);

        $ano    = AnoLetivo::findOrFail($data['anl_id']);
        $escola = Escola::findOrFail($data['esc_id']);
        $turma  = Turma::with('serie:ser_id,ser_nome')->findOrFail($data['tur_id']);

        $uniIds = DB::table('cfg_unidade')->where('uni_anl_id', $data['anl_id'])->orderBy('uni_numero')->pluck('uni_id')->map(fn ($v) => (int) $v)->all();

        $disciplinas = DB::table('edu_grade_disciplinar as gd')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
            ->where('gd.grd_ser_id', $turma->tur_ser_id)
            ->where('gd.grd_fl_ativo', true)
            ->distinct()
            ->orderBy('gd.grd_ordem')->orderBy('d.dis_nome')
            ->get(['d.dis_id', 'd.dis_nome', 'gd.grd_ordem']);

        // [dis_id][uni_id] => [aln_id => result] (só alunos ATIVA, conforme CalculoNota).
        $res = [];
        foreach ($disciplinas as $d) {
            foreach ($uniIds as $uniId) {
                $res[$d->dis_id][$uniId] = CalculoNota::resultado((int) $turma->tur_id, (int) $d->dis_id, $uniId);
            }
        }

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turma->tur_id)
            ->whereNull('tma_deleted_at')
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula', 'situacaoSaida:tas_cod,tas_descricao,tas_descricao_enturmacao')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            // 1 por aluno, preferindo a matrícula mais recente (ATIVA/retorno).
            ->sortByDesc(fn ($m) => $m->tma_id)
            ->unique(fn ($m) => $m->aluno->aln_id)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values()
            ->map(function (Matricula $m) use ($disciplinas, $uniIds, $res) {
                $alnId     = (int) $m->aluno->aln_id;
                $ativo     = $m->tma_situacao === Matricula::SITUACAO_ATIVA;
                $encerrado = $ativo && $m->tma_dt_encerramento !== null;
                $cod       = $m->tma_tas_cod_saida !== null ? (int) $m->tma_tas_cod_saida : null;
                $aprovado  = $encerrado && $cod !== null && in_array($cod, self::APROVADO, true);

                $celulas = $disciplinas->map(function ($d) use ($alnId, $uniIds, $res) {
                    $porUni = [];
                    foreach ($uniIds as $u) {
                        $porUni[$u] = $res[$d->dis_id][$u][$alnId] ?? ['tipo' => null, 'valor' => null, 'conceito' => null];
                    }

                    return $this->celula(CalculoNota::consolidar($porUni));
                })->values();

                return [
                    'aln_id'           => $alnId,
                    'aln_nome'         => $m->aluno->aln_nome,
                    'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                    'celulas'          => $celulas,
                    // Resultado final só para quem está encerrado; quem saiu não tem.
                    'resultado'        => $encerrado ? ($m->situacaoSaida?->tas_descricao_enturmacao ?: ($m->situacaoSaida?->tas_descricao ?? '—')) : null,
                    'aprovado'         => $aprovado,
                    'saiu'             => ! $ativo,
                ];
            });

        $p = ParametroEntidade::first();

        return Inertia::render('relatorios/AtaFinal/Resultado', [
            'parametros' => $p ? [
                'nome_entidade'      => $p->par_nome_entidade,
                'msg_cab_secretaria' => $p->par_msg_cab_secretaria,
                'msg_cab_estado'     => $p->par_msg_cab_estado,
                'logomarca_url'      => $p->par_logomarca_url,
                'brasao_url'         => $p->par_brasao_url,
            ] : null,
            'filtros' => [
                'anl_ano'  => $ano->anl_ano,
                'esc_nome' => $escola->esc_nome,
                'turma'    => trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' - ' : '') . $turma->tur_nome),
            ],
            'disciplinas' => $disciplinas->pluck('dis_nome')->values(),
            'alunos'      => $alunos,
        ]);
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
