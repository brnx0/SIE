<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Relatorio\Concerns\NotaLookups;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\CalculoNota;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        ]);

        $this->autorizarTurma((int) $data['tur_id']);

        $ano   = AnoLetivo::findOrFail($data['anl_id']);
        $turma = Turma::with('serie:ser_id,ser_nome')->findOrFail($data['tur_id']);

        // Escola (cabeçalho): nome, endereço completo, telefone, email.
        $e = DB::table('edu_escola as esc')
            ->leftJoin('edu_bairro as b', 'b.bai_id', '=', 'esc.esc_bai_id')
            ->leftJoin('edu_municipio as m', 'm.mun_id', '=', 'esc.esc_mun_id')
            ->where('esc.esc_id', $data['esc_id'])
            ->first(['esc.esc_nome', 'esc.esc_logradouro', 'esc.esc_numero', 'esc.esc_ddd', 'esc.esc_telefone_fixo', 'esc.esc_email', 'b.bai_nome', 'm.mun_nome', 'm.mun_uf']);

        $logradouro = trim(($e->esc_logradouro ?? '') . ($e->esc_numero ? ', ' . $e->esc_numero : ''));
        $endereco = implode(' - ', array_filter([
            $logradouro ?: null,
            $e->bai_nome ?? null,
            $e->mun_nome ? $e->mun_nome . ($e->mun_uf ? ' - ' . $e->mun_uf : '') : null,
        ]));
        $telefone = $e->esc_telefone_fixo ? trim(($e->esc_ddd ? '(' . $e->esc_ddd . ') ' : '') . $e->esc_telefone_fixo) : null;

        $segNome = DB::table('edu_segmento')->where('seg_id', $turma->tur_seg_id)->value('seg_nome_completo');

        // Unidades (trimestres) — sempre todas do ano.
        $unidades = DB::table('cfg_unidade')
            ->where('uni_anl_id', $data['anl_id'])
            ->orderBy('uni_numero')
            ->get(['uni_id', 'uni_numero', 'uni_tipo'])
            ->map(fn ($u) => ['uni_id' => (int) $u->uni_id, 'label' => $this->unidadeLabel((int) $u->uni_numero, (string) $u->uni_tipo)])
            ->values();

        // Disciplinas pela grade disciplinar (ordem da grade), mesmo sem lançamento.
        $disciplinas = DB::table('edu_grade_disciplinar as gd')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
            ->where('gd.grd_ser_id', $turma->tur_ser_id)
            ->where('gd.grd_anl_id', $data['anl_id'])
            ->where('gd.grd_fl_ativo', true)
            ->orderBy('gd.grd_ordem')
            ->orderBy('d.dis_nome')
            ->get(['d.dis_id', 'd.dis_nome']);

        // Resultados [dis][uni] => [aln => result].
        $res = [];
        foreach ($disciplinas as $d) {
            foreach ($unidades as $u) {
                $res[$d->dis_id][$u['uni_id']] = CalculoNota::resultado((int) $turma->tur_id, (int) $d->dis_id, (int) $u['uni_id']);
            }
        }

        // Faltas [dis][uni][aln] => qtd.
        $faltas = [];
        $rowsFalta = DB::table('edu_diario_falta as f')
            ->join('edu_diario_aula as a', 'a.aul_id', '=', 'f.fal_aul_id')
            ->where('a.aul_tur_id', $turma->tur_id)
            ->where('f.fal_fl_presente', false)
            ->whereNull('a.aul_deleted_at')
            ->whereNull('f.fal_deleted_at')
            ->groupBy('a.aul_dis_id', 'a.aul_uni_id', 'f.fal_aln_id')
            ->get([
                'a.aul_dis_id',
                'a.aul_uni_id',
                'f.fal_aln_id',
                DB::raw('count(*) as q'),
            ]);
        foreach ($rowsFalta as $r) {
            $faltas[(int) $r->aul_dis_id][(int) $r->aul_uni_id][(int) $r->fal_aln_id] = (int) $r->q;
        }

        $alunos = Matricula::query()
            ->where('tma_tur_id', $turma->tur_id)
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma_deleted_at')
            ->when(! empty($data['aln_id']), fn ($q) => $q->where('tma_aln_id', $data['aln_id']))
            ->with('aluno:aln_id,aln_nome,aln_nr_matricula,aln_dt_nascimento')
            ->get()
            ->filter(fn ($m) => $m->aluno)
            ->sortBy(fn ($m) => Str::ascii(mb_strtolower((string) $m->aluno->aln_nome)))
            ->values()
            ->map(function (Matricula $m) use ($disciplinas, $unidades, $res, $faltas) {
                $alnId = (int) $m->aluno->aln_id;
                $totaisFaltas = [];
                $totalFaltasGeral = 0;

                $linhas = $disciplinas->map(function ($d) use ($alnId, $unidades, $res, $faltas, &$totaisFaltas, &$totalFaltasGeral) {
                    $valores = [];
                    $porUnidade = [];
                    $totalDis = 0;
                    foreach ($unidades as $u) {
                        $uid = $u['uni_id'];
                        $r = $res[$d->dis_id][$uid][$alnId] ?? ['tipo' => null, 'valor' => null, 'conceito' => null];
                        $f = $faltas[$d->dis_id][$uid][$alnId] ?? 0;
                        $valores[$uid] = ['media' => $this->celula($r), 'faltas' => $f];
                        $porUnidade[$uid] = $r;
                        $totalDis += $f;
                        $totaisFaltas[$uid] = ($totaisFaltas[$uid] ?? 0) + $f;
                    }
                    $totalFaltasGeral += $totalDis;
                    $anual = $this->celula(CalculoNota::consolidar($porUnidade));

                    return [
                        'dis_nome'     => $d->dis_nome,
                        'valores'      => $valores,
                        'media_anual'  => $anual,
                        'recuperacao'  => null,
                        'media_total'  => $anual,
                        'total_faltas' => $totalDis,
                    ];
                });

                return [
                    'aln_id'             => $alnId,
                    'aln_nome'           => $m->aluno->aln_nome,
                    'aln_nr_matricula'   => $m->aluno->aln_nr_matricula,
                    'aln_nascimento'     => optional($m->aluno->aln_dt_nascimento)->format('d/m/Y'),
                    'disciplinas'        => $linhas->values(),
                    'totais_faltas'      => $totaisFaltas,
                    'total_faltas_geral' => $totalFaltasGeral,
                ];
            });

        $p = ParametroEntidade::first();

        return Inertia::render('relatorios/Boletim/Resultado', [
            'parametros' => $p ? [
                'estado'        => $p->par_msg_cab_estado,
                'entidade'      => $p->par_nome_entidade,
                'secretaria'    => $p->par_msg_cab_secretaria,
                'logomarca_url' => $p->par_logomarca_url,
                'brasao_url'    => $p->par_brasao_url,
            ] : null,
            'escola' => [
                'nome'      => $e->esc_nome,
                'endereco'  => $endereco ?: null,
                'telefone'  => $telefone,
                'email'     => $e->esc_email,
            ],
            'cabecalho' => [
                'anl_ano'     => $ano->anl_ano,
                'tipo_ensino' => $segNome,
                'turma'       => $turma->tur_nome,
                'turno'       => $turma->tur_turno ? ucfirst(mb_strtolower((string) $turma->tur_turno)) : null,
                'serie'       => trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' ' : '') . $turma->tur_nome),
            ],
            'unidades' => $unidades,
            'alunos'   => $alunos,
        ]);
    }
}
