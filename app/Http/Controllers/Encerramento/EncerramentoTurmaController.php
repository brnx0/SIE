<?php

namespace App\Http\Controllers\Encerramento;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Support\CalculoNota;
use App\Support\UserAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Encerramento de Turmas — POR ENQUANTO SOMENTE CONSULTA (sem insert/update).
 * Lista as turmas de uma escola e, em cada turma, os alunos com a situação da
 * matrícula e se todas as notas estão lançadas (todas as disciplinas × unidades).
 */
class EncerramentoTurmaController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('encerramento/turmas/Index', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    /** Dados do encerramento: turmas da escola + alunos com situação e completude de notas. */
    public function dados(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        $this->autorizarEscola((int) $data['esc_id']);

        $unidades = DB::table('cfg_unidade')
            ->where('uni_anl_id', $data['anl_id'])
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo']);
        $uniIds   = $unidades->pluck('uni_id')->map(fn ($v) => (int) $v)->all();
        $uniLabel = $unidades->mapWithKeys(fn ($u) => [(int) $u->uni_id => $u->uni_numero.'º '.ucfirst($u->uni_tipo)])->all();

        $turmas = DB::table('edu_turma as t')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->where('t.tur_anl_id', $data['anl_id'])
            ->where('t.tur_esc_id', $data['esc_id'])
            ->orderBy('s.ser_nome')
            ->orderBy('t.tur_nome')
            ->get(['t.tur_id', 't.tur_nome', 't.tur_ser_id', 's.ser_nome', DB::raw('s.ser_tipo_avaliacao::text as ser_tipo_avaliacao')]);

        $linhas = $turmas->map(function ($t) use ($uniIds, $uniLabel) {
            $tipos = json_decode((string) $t->ser_tipo_avaliacao, true) ?: [];
            $avaliativa = (bool) array_intersect(['numerica', 'conceitual'], is_array($tipos) ? $tipos : []);

            // Disciplinas da grade da série.
            $disciplinas = DB::table('edu_grade_disciplinar as gd')
                ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
                ->where('gd.grd_ser_id', $t->tur_ser_id)
                ->where('gd.grd_fl_ativo', true)
                ->orderBy('gd.grd_ordem')
                ->orderBy('d.dis_nome')
                ->get(['d.dis_id', 'd.dis_nome']);
            $disIds   = $disciplinas->pluck('dis_id')->map(fn ($v) => (int) $v)->all();
            $disNome  = $disciplinas->mapWithKeys(fn ($d) => [(int) $d->dis_id => $d->dis_nome])->all();

            // Células de nota lançadas: manual (precedência) + calculado completo.
            $manualSet = [];
            if ($avaliativa && $disIds && $uniIds) {
                foreach (DB::table('edu_nota_manual')
                    ->where('nmn_tur_id', $t->tur_id)
                    ->whereNull('nmn_deleted_at')
                    ->where(fn ($q) => $q->whereNotNull('nmn_media')->orWhereNotNull('nmn_cnc_id'))
                    ->get(['nmn_dis_id', 'nmn_uni_id', 'nmn_aln_id']) as $m) {
                    $manualSet[(int) $m->nmn_dis_id][(int) $m->nmn_uni_id][(int) $m->nmn_aln_id] = true;
                }
            }
            $calc = ($avaliativa && $disIds && $uniIds)
                ? CalculoNota::calculadoTurma((int) $t->tur_id, $disIds, $uniIds) // [dis][aln][uni]
                : [];

            $lancada = fn (int $dis, int $uni, int $aln): bool => isset($manualSet[$dis][$uni][$aln]) || isset($calc[$dis][$aln][$uni]);

            // Alunos ativos da turma.
            $alunos = Matricula::query()
                ->where('tma_tur_id', $t->tur_id)
                ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->whereNull('tma_deleted_at')
                ->with(['aluno:aln_id,aln_nome,aln_nr_matricula', 'situacaoEntrada:tas_cod,tas_descricao', 'situacaoSaida:tas_cod,tas_descricao'])
                ->get()
                ->filter(fn ($m) => $m->aluno)
                ->unique(fn ($m) => $m->aluno->aln_id)
                ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
                ->values()
                ->map(function (Matricula $m) use ($avaliativa, $disIds, $uniIds, $disNome, $uniLabel, $lancada) {
                    $alnId = (int) $m->aluno->aln_id;

                    $pendencias = [];
                    if ($avaliativa) {
                        foreach ($disIds as $dis) {
                            foreach ($uniIds as $uni) {
                                if (! $lancada($dis, $uni, $alnId)) {
                                    $pendencias[] = ['disciplina' => $disNome[$dis] ?? '—', 'unidade' => $uniLabel[$uni] ?? '—'];
                                }
                            }
                        }
                    }

                    $totalCelulas = $avaliativa ? count($disIds) * count($uniIds) : 0;
                    $status = ! $avaliativa
                        ? 'nao_avaliativa'
                        : ($totalCelulas === 0 ? 'sem_grade' : (empty($pendencias) ? 'completo' : 'pendente'));

                    return [
                        'aln_id'      => $alnId,
                        'nome'        => $m->aluno->aln_nome,
                        'matricula'   => $m->aluno->aln_nr_matricula,
                        'situacao'    => $this->situacaoLabel($m),
                        'status'      => $status,
                        'total'       => $totalCelulas,
                        'lancadas'    => max(0, $totalCelulas - count($pendencias)),
                        'pendencias'  => $pendencias,
                    ];
                });

            return [
                'tur_id'      => (int) $t->tur_id,
                'turma'       => $t->tur_nome,
                'serie'       => $t->ser_nome,
                'avaliativa'  => $avaliativa,
                'encerrada'   => false, // TODO: mockado — virá da persistência do encerramento
                'alunos'      => $alunos,
            ];
        })->values();

        return response()->json([
            'unidades' => $unidades->map(fn ($u) => ['uni_id' => (int) $u->uni_id, 'label' => $uniLabel[(int) $u->uni_id]]),
            'turmas'   => $linhas,
        ]);
    }

    /** Mesmo critério das outras telas (aba Alunos da turma): situação de entrada p/ ativos, de saída para os demais. */
    private function situacaoLabel(Matricula $m): string
    {
        if ($m->tma_situacao === Matricula::SITUACAO_ATIVA) {
            return $m->situacaoEntrada?->tas_descricao ?? 'Matriculado(a)';
        }

        return $m->situacaoSaida?->tas_descricao ?? (string) $m->tma_situacao;
    }

    /** Admin vê qualquer escola; secretaria só as da sua lotação. */
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
