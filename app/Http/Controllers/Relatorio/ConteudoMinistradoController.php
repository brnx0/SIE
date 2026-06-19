<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Relatorio\Concerns\NotaLookups;
use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Relatório de Conteúdo Ministrado.
 * Por disciplina da turma, lista os dias com aula registrada na frequência,
 * indicando se o planejamento foi executado e o conteúdo/metodologia do dia.
 * Sem período (unidade) selecionado, lista todos os períodos do ano.
 */
class ConteudoMinistradoController extends Controller
{
    use NotaLookups;

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/ConteudoMinistrado/Form', [
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
            'uni_id' => ['nullable', 'integer', 'exists:cfg_unidade,uni_id'],
        ]);

        $this->autorizarTurma((int) $data['tur_id']);

        $ano    = AnoLetivo::findOrFail($data['anl_id']);
        $escola = Escola::findOrFail($data['esc_id']);
        $turma  = Turma::with('serie:ser_id,ser_nome')->findOrFail($data['tur_id']);

        $uniIdFiltro = ! empty($data['uni_id']) ? (int) $data['uni_id'] : null;

        // Rótulos das unidades do ano (para a coluna de período no modo "todos").
        $labelUni = [];
        foreach (DB::table('cfg_unidade')->where('uni_anl_id', $data['anl_id'])->orderBy('uni_numero')->get(['uni_id', 'uni_numero', 'uni_tipo']) as $u) {
            $labelUni[(int) $u->uni_id] = $this->unidadeLabel((int) $u->uni_numero, (string) $u->uni_tipo);
        }

        // Dias com aula lançada (frequência) por disciplina; conteúdo/metodologia/plano
        // vêm do registro do dia (pode estar vazio se só a presença foi marcada).
        $linhas = DB::table('edu_diario_aula as a')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'a.aul_dis_id')
            ->leftJoin('edu_diario_conteudo as c', function ($j) {
                $j->on('c.dco_tur_id', '=', 'a.aul_tur_id')
                    ->on('c.dco_dis_id', '=', 'a.aul_dis_id')
                    ->on('c.dco_dt', '=', 'a.aul_dt')
                    ->whereNull('c.dco_deleted_at');
            })
            ->where('a.aul_tur_id', $turma->tur_id)
            ->whereNull('a.aul_deleted_at')
            ->when($uniIdFiltro, fn ($q) => $q->where('a.aul_uni_id', $uniIdFiltro))
            ->groupBy('d.dis_id', 'd.dis_nome', 'a.aul_dt', 'a.aul_uni_id', 'c.dco_conteudo', 'c.dco_metodologia', 'c.dco_fl_plano_executado')
            ->orderBy('d.dis_nome')
            ->orderBy('a.aul_dt')
            ->get(['d.dis_id', 'd.dis_nome', 'a.aul_dt', 'a.aul_uni_id', 'c.dco_conteudo', 'c.dco_metodologia', 'c.dco_fl_plano_executado']);

        $disciplinas = $linhas
            ->groupBy('dis_id')
            ->map(fn ($rows) => [
                'dis_nome' => $rows->first()->dis_nome,
                'total'    => $rows->count(),
                'dias'     => $rows->map(fn ($r) => [
                    'dt'              => Carbon::parse($r->aul_dt)->format('d/m/Y'),
                    'periodo'         => $labelUni[(int) $r->aul_uni_id] ?? '—',
                    'plano_executado' => (bool) $r->dco_fl_plano_executado,
                    'conteudo'        => $r->dco_conteudo,
                    'metodologia'     => $r->dco_metodologia,
                ])->values(),
            ])
            ->values();

        $p = ParametroEntidade::first();

        return Inertia::render('relatorios/ConteudoMinistrado/Resultado', [
            'parametros'  => $p ? [
                'nome_entidade' => $p->par_nome_entidade,
                'logomarca_url' => $p->par_logomarca_url,
                'brasao_url'    => $p->par_brasao_url,
            ] : null,
            'filtros'     => [
                'anl_ano'  => $ano->anl_ano,
                'esc_nome' => $escola->esc_nome,
                'turma'    => trim(($turma->serie?->ser_nome ? $turma->serie->ser_nome . ' - ' : '') . $turma->tur_nome),
                'periodo'  => $uniIdFiltro ? ($labelUni[$uniIdFiltro] ?? '—') : 'Todos os períodos',
            ],
            'consolidado' => $uniIdFiltro === null,
            'disciplinas' => $disciplinas,
        ]);
    }
}
