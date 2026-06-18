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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BoletimController extends Controller
{
    use NotaLookups;

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/Boletim/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id' => ['required', 'integer', 'exists:edu_turma,tur_id'],
            'aln_id' => ['nullable', 'integer', 'exists:edu_aluno,aln_id'],
            'uni_id' => ['nullable', 'integer', 'exists:cfg_unidade,uni_id'],
        ]);

        $this->autorizarTurma((int) $data['tur_id']);

        $ano    = AnoLetivo::findOrFail($data['anl_id']);
        $escola = Escola::findOrFail($data['esc_id']);
        $turma  = Turma::with('serie:ser_id,ser_nome')->findOrFail($data['tur_id']);

        $uniIdFiltro = ! empty($data['uni_id']) ? (int) $data['uni_id'] : null;
        $consolidado = $uniIdFiltro === null;

        // Unidades-coluna.
        $unidades = DB::table('cfg_unidade')
            ->where('uni_anl_id', $data['anl_id'])
            ->when($uniIdFiltro, fn ($q) => $q->where('uni_id', $uniIdFiltro))
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo'])
            ->map(fn ($u) => ['uni_id' => (int) $u->uni_id, 'label' => $this->unidadeLabel((int) $u->uni_numero, (string) $u->uni_tipo)])
            ->values();

        $disciplinas = DB::table('edu_grade_disciplinar as gd')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
            ->where('gd.grd_ser_id', $turma->tur_ser_id)
            ->where('gd.grd_fl_ativo', true)
            ->distinct()
            ->orderBy('d.dis_nome')
            ->get(['d.dis_id', 'd.dis_nome']);

        // Pré-calcula resultados [dis_id][uni_id] => [aln_id => result].
        $res = [];
        foreach ($disciplinas as $d) {
            foreach ($unidades as $u) {
                $res[$d->dis_id][$u['uni_id']] = CalculoNota::resultado((int) $turma->tur_id, (int) $d->dis_id, (int) $u['uni_id']);
            }
        }

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turma->tur_id)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->when(! empty($data['aln_id']), fn ($q) => $q->where('tma_aln_id', $data['aln_id']))
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => $m->aluno->aln_nome, SORT_FLAG_CASE | SORT_NATURAL)
            ->values()
            ->map(function (Matricula $m) use ($disciplinas, $unidades, $res, $consolidado) {
                $alnId = (int) $m->aluno->aln_id;

                $linhas = $disciplinas->map(function ($d) use ($alnId, $unidades, $res, $consolidado) {
                    $valores = [];
                    $porUnidade = [];
                    foreach ($unidades as $u) {
                        $r = $res[$d->dis_id][$u['uni_id']][$alnId] ?? ['tipo' => null, 'valor' => null, 'conceito' => null];
                        $valores[$u['uni_id']] = $this->celula($r);
                        $porUnidade[$u['uni_id']] = $r;
                    }
                    $final = $consolidado ? $this->celula(CalculoNota::consolidar($porUnidade)) : null;

                    return ['dis_nome' => $d->dis_nome, 'valores' => $valores, 'final' => $final];
                });

                return [
                    'aln_id'           => $alnId,
                    'aln_nome'         => $m->aluno->aln_nome,
                    'aln_nr_matricula' => $m->aluno->aln_nr_matricula,
                    'disciplinas'      => $linhas->values(),
                ];
            });

        $p = ParametroEntidade::first();

        return Inertia::render('relatorios/Boletim/Resultado', [
            'parametros'  => $p ? [
                'nome_entidade' => $p->par_nome_entidade,
                'logomarca_url' => $p->par_logomarca_url,
                'brasao_url'    => $p->par_brasao_url,
            ] : null,
            'filtros'     => [
                'anl_ano'  => $ano->anl_ano,
                'esc_nome' => $escola->esc_nome,
                'turma'    => trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' - ' : '') . $turma->tur_nome),
                'periodo'  => $uniIdFiltro ? ($unidades->firstWhere('uni_id', $uniIdFiltro)['label'] ?? null) : 'Consolidado',
            ],
            'unidades'    => $unidades,
            'consolidado' => $consolidado,
            'alunos'      => $alunos,
        ]);
    }
}
