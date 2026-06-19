<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ParecerDescritivoController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/ParecerDescritivo/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    /** Unidades (períodos) do ano letivo. */
    public function unidades(Request $request): JsonResponse
    {
        $anlId = (int) $request->input('anl_id');
        if (! $anlId) {
            return response()->json([]);
        }

        return response()->json(
            DB::table('cfg_unidade')
                ->where('uni_anl_id', $anlId)
                ->orderBy('uni_numero')
                ->get(['uni_id', 'uni_numero', 'uni_tipo'])
        );
    }

    /**
     * Turmas escopadas por perfil: admin vê todas da escola; secretaria vê todas
     * da(s) escola(s) onde tem lotação; professor (com fun_id) vê só as que leciona.
     */
    public function turmas(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $funId = (int) $user->fun_id;
        $anlId = (int) $request->input('anl_id');
        $escId = (int) $request->input('esc_id');

        if (! $anlId || ! $escId) {
            return response()->json([]);
        }

        $porEscola = $user->isAdmin() || UserAccess::acessoPorEscola($user);

        // Secretaria só enxerga turmas de escolas onde tem lotação.
        if (UserAccess::acessoPorEscola($user)) {
            $ids = UserAccess::escolasIds($user);
            if ($ids !== null && ! in_array($escId, $ids, true)) {
                return response()->json([]);
            }
        }

        if ($porEscola) {
            $base = DB::table('edu_turma as t');
        } elseif ($funId) {
            $base = DB::table('edu_turma_professor as tp')
                ->join('edu_turma as t', 't.tur_id', '=', 'tp.tup_tur_id')
                ->where('tp.tup_fun_id', $funId)
                ->whereNull('tp.tup_deleted_at');
        } else {
            return response()->json([]);
        }

        return response()->json(
            $base
                ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
                ->whereNull('t.tur_deleted_at')
                ->where('t.tur_modalidade', 'REGULAR')
                ->where('t.tur_anl_id', $anlId)
                ->where('t.tur_esc_id', $escId)
                ->where('t.tur_situacao', 'ABERTA')
                ->select('t.tur_id', 't.tur_nome', 's.ser_nome')
                ->distinct()
                ->orderBy('s.ser_nome')
                ->orderBy('t.tur_nome')
                ->get()
        );
    }

    /** Disciplinas da turma (grade da série). Vazio se a série avalia "por aluno". */
    public function disciplinas(Request $request): JsonResponse
    {
        $turId = (int) $request->input('tur_id');
        if (! $turId) {
            return response()->json([]);
        }

        $turma = DB::table('edu_turma as t')
            ->join('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->where('t.tur_id', $turId)
            ->first(['t.tur_ser_id', 's.ser_tipo_avaliacao_descritiva']);

        // "por aluno" => parecer geral, sem disciplina.
        if (! $turma || $turma->ser_tipo_avaliacao_descritiva === 'por_aluno') {
            return response()->json([]);
        }

        return response()->json(
            DB::table('edu_grade_disciplinar as gd')
                ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
                ->where('gd.grd_ser_id', $turma->tur_ser_id)
                ->where('gd.grd_fl_ativo', true)
                ->select('d.dis_id', 'd.dis_nome')
                ->distinct()
                ->orderBy('d.dis_nome')
                ->get()
        );
    }

    /** Alunos ativos da turma (para o filtro opcional de aluno). */
    public function alunos(Request $request): JsonResponse
    {
        $turId = (int) $request->input('tur_id');
        if (! $turId) {
            return response()->json([]);
        }

        return response()->json(
            Matricula::query()
                ->where('tma_tur_id', $turId)
                ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->whereNull('tma_deleted_at')
                ->with('aluno:aln_id,aln_nome')
                ->get()
                ->filter(fn ($m) => $m->aluno)
                ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
                ->values()
                ->map(fn ($m) => ['aln_id' => $m->aluno->aln_id, 'aln_nome' => $m->aluno->aln_nome])
        );
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'dis_id' => ['nullable', 'integer', 'exists:edu_disciplina,dis_id'],
            'aln_id' => ['nullable', 'integer', 'exists:edu_aluno,aln_id'],
            'uni_id' => ['nullable', 'integer', 'exists:cfg_unidade,uni_id'],
        ]);

        $ano    = AnoLetivo::findOrFail($data['anl_id']);
        $escola = Escola::findOrFail($data['esc_id']);
        $turma  = Turma::with('serie:ser_id,ser_nome,ser_tipo_avaliacao_descritiva')->findOrFail($data['tur_id']);

        // Admin sem restrição; secretaria pela lotação; professor só onde leciona.
        $user = auth()->user();
        if (! $user->isAdmin()) {
            if (UserAccess::acessoPorEscola($user)) {
                $ids = UserAccess::escolasIds($user);
                abort_unless(is_array($ids) && in_array((int) $turma->tur_esc_id, $ids, true), 403, 'Você não tem lotação na escola desta turma.');
            } else {
                $funId = (int) $user->fun_id;
                $ensina = $funId && DB::table('edu_turma_professor')
                    ->where('tup_fun_id', $funId)
                    ->where('tup_tur_id', $turma->tur_id)
                    ->whereNull('tup_deleted_at')
                    ->exists();
                abort_unless($ensina, 403, 'Você não leciona nesta turma.');
            }
        }

        $tipo  = $turma->serie?->ser_tipo_avaliacao_descritiva; // 'por_aluno' | 'por_disciplina' | null
        $disId = ! empty($data['dis_id']) ? (int) $data['dis_id'] : null;
        $alnId = ! empty($data['aln_id']) ? (int) $data['aln_id'] : null;
        $uniId = ! empty($data['uni_id']) ? (int) $data['uni_id'] : null;

        // Mapa de rótulos de período (ex.: "1º Trimestre").
        $uniMap = DB::table('cfg_unidade')
            ->where('uni_anl_id', $data['anl_id'])
            ->get(['uni_id', 'uni_numero', 'uni_tipo'])
            ->mapWithKeys(fn ($u) => [$u->uni_id => $u->uni_numero . 'º ' . ucfirst((string) $u->uni_tipo)]);

        // Alunos ativos (filtra por aluno, se informado).
        $alunos = Matricula::query()
            ->where('tma_tur_id', $turma->tur_id)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->when($alnId, fn ($q) => $q->where('tma_aln_id', $alnId))
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values();

        // Pareceres do contexto, agrupados por aluno.
        if ($tipo === 'por_aluno') {
            $grouped = DB::table('edu_diario_avaliacao_descritiva')
                ->where('dad_tur_id', $turma->tur_id)
                ->whereNull('dad_deleted_at')
                ->whereNull('dad_dis_id')
                ->when($uniId, fn ($q) => $q->where('dad_uni_id', $uniId))
                ->get(['dad_aln_id', 'dad_uni_id', 'dad_descricao'])
                ->groupBy('dad_aln_id');
        } else {
            $grouped = DB::table('edu_diario_avaliacao_descritiva as d')
                ->join('edu_disciplina as dis', 'dis.dis_id', '=', 'd.dad_dis_id')
                ->where('d.dad_tur_id', $turma->tur_id)
                ->whereNull('d.dad_deleted_at')
                ->whereNotNull('d.dad_dis_id')
                ->when($uniId, fn ($q) => $q->where('d.dad_uni_id', $uniId))
                ->when($disId, fn ($q) => $q->where('d.dad_dis_id', $disId))
                ->get(['d.dad_aln_id', 'd.dad_uni_id', 'dis.dis_nome', 'd.dad_descricao'])
                ->groupBy('dad_aln_id');
        }

        $linhas = $alunos->map(function (Matricula $m) use ($tipo, $grouped, $uniMap) {
            $aln = $m->aluno;
            $pareceres = [];

            foreach ($grouped[$aln->aln_id] ?? [] as $r) {
                if (trim((string) $r->dad_descricao) === '') {
                    continue;
                }
                $pareceres[] = [
                    'periodo'    => $uniMap[$r->dad_uni_id] ?? '—',
                    'disciplina' => $tipo === 'por_aluno' ? null : ($r->dis_nome ?? null),
                    'descricao'  => $r->dad_descricao,
                ];
            }

            usort($pareceres, fn ($a, $b) => [$a['periodo'], (string) $a['disciplina']] <=> [$b['periodo'], (string) $b['disciplina']]);

            return [
                'aln_id'           => $aln->aln_id,
                'aln_nome'         => $aln->aln_nome,
                'aln_nr_matricula' => $aln->aln_nr_matricula,
                'pareceres'        => $pareceres,
            ];
        });

        $parametros = ParametroEntidade::first();

        return Inertia::render('relatorios/ParecerDescritivo/Resultado', [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'endereco'           => $parametros->par_endereco,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'filtros' => [
                'anl_ano'    => $ano->anl_ano,
                'esc_nome'   => $escola->esc_nome,
                'turma'      => trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' - ' : '') . $turma->tur_nome),
                'modo'       => $tipo,
                'disciplina' => $disId ? DB::table('edu_disciplina')->where('dis_id', $disId)->value('dis_nome') : null,
                'aluno'      => $alnId ? DB::table('edu_aluno')->where('aln_id', $alnId)->value('aln_nome') : null,
                'periodo'    => $uniId ? ($uniMap[$uniId] ?? null) : null,
            ],
            'linhas' => $linhas->values(),
            'total'  => $linhas->count(),
        ]);
    }
}
