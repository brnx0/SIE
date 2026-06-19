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

        // Dias de aula lançados na frequência — por (disciplina + data).
        $dias = DB::table('edu_diario_aula as a')
            ->where('a.aul_tur_id', $turma->tur_id)
            ->whereNull('a.aul_deleted_at')
            ->whereNotNull('a.aul_dis_id')
            ->when($uniIdFiltro, fn ($q) => $q->where('a.aul_uni_id', $uniIdFiltro))
            ->distinct()
            ->get(['a.aul_dis_id as dis_id', 'a.aul_dt as dt', 'a.aul_uni_id as uni_id']);

        // Registros do dia (conteúdo / metodologia / planejamento executado).
        $conteudos = DB::table('edu_diario_conteudo as c')
            ->where('c.dco_tur_id', $turma->tur_id)
            ->whereNull('c.dco_deleted_at')
            ->whereNotNull('c.dco_dis_id')
            ->when($uniIdFiltro, fn ($q) => $q->where('c.dco_uni_id', $uniIdFiltro))
            ->get(['c.dco_dis_id as dis_id', 'c.dco_dt as dt', 'c.dco_uni_id as uni_id', 'c.dco_conteudo', 'c.dco_metodologia', 'c.dco_fl_plano_executado']);

        // União por (disciplina + data): o dia entra se tem frequência lançada OU registro
        // do dia. Conteúdo/planejamento vêm direto do registro (sem depender de casar com a aula).
        $linhasMap = [];
        $chave = fn (int $dis, string $dt) => $dis . '|' . $dt;
        foreach ($dias as $a) {
            $dt = Carbon::parse($a->dt)->toDateString();
            $linhasMap[$chave((int) $a->dis_id, $dt)] = [
                'dis_id' => (int) $a->dis_id, 'dt' => $dt, 'uni_id' => (int) $a->uni_id,
                'conteudo' => null, 'metodologia' => null, 'plano' => false,
            ];
        }
        foreach ($conteudos as $c) {
            $dt = Carbon::parse($c->dt)->toDateString();
            $k  = $chave((int) $c->dis_id, $dt);
            $linhasMap[$k] ??= ['dis_id' => (int) $c->dis_id, 'dt' => $dt, 'uni_id' => (int) $c->uni_id, 'conteudo' => null, 'metodologia' => null, 'plano' => false];
            $linhasMap[$k]['conteudo']    = $c->dco_conteudo;
            $linhasMap[$k]['metodologia'] = $c->dco_metodologia;
            $linhasMap[$k]['plano']       = (bool) $c->dco_fl_plano_executado;
        }
        $diasPorDisc = collect($linhasMap)->groupBy('dis_id');

        // Todas as disciplinas da grade da série devem aparecer, mesmo sem registro.
        $nomes = DB::table('edu_grade_disciplinar as gd')
            ->join('edu_disciplina as d', 'd.dis_id', '=', 'gd.grd_dis_id')
            ->where('gd.grd_ser_id', $turma->tur_ser_id)
            ->where('gd.grd_fl_ativo', true)
            ->distinct()
            ->pluck('d.dis_nome', 'd.dis_id');

        // Inclui também disciplinas com registro fora da grade atual (grade alterada).
        foreach ($diasPorDisc as $disId => $rows) {
            if (! $nomes->has((int) $disId)) {
                $nomes->put((int) $disId, DB::table('edu_disciplina')->where('dis_id', $disId)->value('dis_nome') ?? ('Disciplina ' . $disId));
            }
        }

        $disciplinas = $nomes
            ->map(fn ($nome, $disId) => (object) ['id' => (int) $disId, 'nome' => $nome])
            ->sortBy('nome', SORT_FLAG_CASE | SORT_NATURAL)
            ->values()
            ->map(function ($d) use ($diasPorDisc, $labelUni) {
                $rows = collect($diasPorDisc->get($d->id, []))->sortBy('dt')->values();

                return [
                    'dis_nome' => $d->nome,
                    'total'    => $rows->count(),
                    'dias'     => $rows->map(fn ($r) => [
                        'dt'              => Carbon::parse($r['dt'])->format('d/m/Y'),
                        'periodo'         => $labelUni[(int) $r['uni_id']] ?? '—',
                        'plano_executado' => (bool) $r['plano'],
                        'conteudo'        => $r['conteudo'],
                        'metodologia'     => $r['metodologia'],
                    ])->values(),
                ];
            });

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
