<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlunosDeficienciaController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/AlunosDeficiencia/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => $user->isAdmin()
                ? Escola::where('esc_fl_ativo', true)->orderBy('esc_nome')->get(['esc_id', 'esc_nome'])
                : Escola::where('esc_id', $user->esc_id)->get(['esc_id', 'esc_nome']),
            'userEscola'  => $user->isAdmin() ? null : ['esc_id' => $user->esc_id, 'esc_nome' => $user->escola?->esc_nome],
            'isAdmin'     => $user->isAdmin(),
        ]);
    }

    public function gerar(Request $request): Response
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
        ]);

        $anoLetivo  = AnoLetivo::findOrFail($data['anl_id']);
        $parametros = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->first();
        $escolaFiltro = ! empty($data['esc_id']) ? Escola::find($data['esc_id']) : null;

        $matriculas = Matricula::query()
            ->with([
                'aluno:aln_id,aln_nome,aln_nr_matricula,aln_dt_nascimento',
                'aluno.saude:als_id,als_aln_id,als_fl_pcd',
                'turma:tur_id,tur_nome,tur_esc_id,tur_anl_id,tur_modalidade,tur_turno,tur_ser_id',
                'turma.serie:ser_id,ser_nome',
                'turma.escola:esc_id,esc_nome',
            ])
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereHas('turma', fn ($q) => $q
                ->where('tur_anl_id', $data['anl_id'])
                ->where('tur_modalidade', Turma::MODALIDADE_REGULAR)
                ->when(! empty($data['esc_id']), fn ($q2) => $q2->where('tur_esc_id', $data['esc_id'])))
            ->whereHas('aluno.saude', fn ($s) => $s->where('als_fl_pcd', true))
            ->get();

        $linhas = $matriculas
            ->sortBy(fn ($m) => [
                $m->turma?->escola?->esc_nome ?? '',
                $m->turma?->serie?->ser_nome ?? '',
                $m->turma?->tur_nome ?? '',
                $m->aluno?->aln_nome ?? '',
            ])
            ->values()
            ->map(function (Matricula $m) {
                return [
                    'escola'         => $m->turma?->escola?->esc_nome,
                    'matricula'      => $m->aluno?->aln_nr_matricula,
                    'nome'           => $m->aluno?->aln_nome,
                    'dt_nascimento'  => optional($m->aluno?->aln_dt_nascimento)->format('d/m/Y'),
                    'ano_serie'      => $m->turma?->serie?->ser_nome,
                    'turma'          => $m->turma?->tur_nome,
                    'turno'          => $m->turma?->tur_turno,
                    'pcd'            => 'Sim',
                ];
            });

        return Inertia::render('relatorios/AlunosDeficiencia/Resultado', [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'endereco'           => $parametros->par_endereco,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'filtros' => [
                'anl_ano'  => $anoLetivo->anl_ano,
                'esc_nome' => $escolaFiltro?->esc_nome ?? 'Todas as escolas',
            ],
            'linhas' => $linhas,
            'total'  => $linhas->count(),
        ]);
    }
}
