<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Support\IndicesAprovacao;
use App\Support\UserAccess;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Índices de Aprovação e Reprovação — por trimestre (unidade) e anual, por
 * escola e turma, com consolidado da rede. Regras de apuração em
 * App\Support\IndicesAprovacao. Percentuais sobre aprovados + reprovados
 * (pendentes ficam fora do denominador).
 */
class IndicesAprovacaoController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();
        $escolas = UserAccess::escolasVisiveis($user);
        $escolasIds = $escolas->pluck('esc_id')->all() ?: [-1];

        return Inertia::render('relatorios/IndicesAprovacao/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => $escolas,
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
            // Unidades de todos os anos — o recorte pelo ano escolhido é local.
            'unidades'    => DB::table('cfg_unidade')->orderBy('uni_anl_id')->orderBy('uni_numero')
                ->get(['uni_id', 'uni_anl_id', 'uni_numero', 'uni_tipo'])
                ->map(fn ($u) => [
                    'uni_id' => (int) $u->uni_id,
                    'anl_id' => (int) $u->uni_anl_id,
                    'label'  => $u->uni_numero . 'º ' . ucfirst(mb_strtolower((string) $u->uni_tipo)),
                ]),
            'turmas' => $this->turmasQuery($escolasIds)
                ->orderBy('t.tur_nome')
                ->get(['t.tur_id', 't.tur_nome', 't.tur_esc_id', 't.tur_anl_id', 't.tur_ser_id', 's.ser_nome'])
                ->map(fn ($t) => [
                    'tur_id'   => (int) $t->tur_id,
                    'esc_id'   => (int) $t->tur_esc_id,
                    'anl_id'   => (int) $t->tur_anl_id,
                    'ser_id'   => $t->tur_ser_id !== null ? (int) $t->tur_ser_id : null,
                    'tur_nome' => trim(($t->ser_nome ? $t->ser_nome . ' - ' : '') . $t->tur_nome),
                ]),
            'series' => DB::table('edu_serie')->whereNull('ser_deleted_at')->orderBy('ser_nome')
                ->get(['ser_id', 'ser_nome'])
                ->map(fn ($s) => ['ser_id' => (int) $s->ser_id, 'ser_nome' => $s->ser_nome]),
        ]);
    }

    public function gerar(Request $request): Response
    {
        return Inertia::render('relatorios/IndicesAprovacao/Resultado', $this->montar($request));
    }

    public function exportarXlsx(Request $request): \Illuminate\Http\Response
    {
        $d = $this->montar($request);

        $headers = ['Período', 'Escola', 'Turma', 'Ano Escolar / Série', 'Alunos', 'Avaliados', 'Aprovados', 'Reprovados', 'Pendentes', '% Aprovação', '% Reprovação', 'Observação'];
        $pct = fn ($v) => $v === null ? '' : $v;

        $rows = [];
        foreach ($d['periodos'] as $per) {
            $c = $per['chave'];
            foreach ($d['escolas'] as $esc) {
                foreach ($esc['turmas'] as $t) {
                    if ($t['sem_avaliacao']) {
                        $rows[] = [$per['label'], $esc['esc_nome'], $t['turma'], $t['serie'] ?? '', $t['alunos'], '', '', '', '', '', '', 'Sem avaliação por nota'];
                        continue;
                    }
                    $i = $t['indices'][$c];
                    $rows[] = [$per['label'], $esc['esc_nome'], $t['turma'], $t['serie'] ?? '', $t['alunos'], $i['avaliados'], $i['aprovados'], $i['reprovados'], $i['pendentes'], $pct($i['pct_aprovacao']), $pct($i['pct_reprovacao']), $t['aviso'] ?? ''];
                }
                $i = $esc['indices'][$c];
                $rows[] = [$per['label'], $esc['esc_nome'] . ' — TOTAL', '', '', $esc['alunos'], $i['avaliados'], $i['aprovados'], $i['reprovados'], $i['pendentes'], $pct($i['pct_aprovacao']), $pct($i['pct_reprovacao']), ''];
            }
            $i = $d['rede']['indices'][$c];
            $rows[] = [$per['label'], 'TOTAL DA REDE', '', '', $d['rede']['alunos'], $i['avaliados'], $i['aprovados'], $i['reprovados'], $i['pendentes'], $pct($i['pct_aprovacao']), $pct($i['pct_reprovacao']), ''];
        }

        return XlsxExport::download('indices_aprovacao_' . now()->format('Ymd_His') . '.xlsx', $headers, $rows);
    }

    /**
     * Filtros → índices por turma, subtotais por escola e total da rede, para os
     * períodos escolhidos (todas as unidades + anual, ou um só).
     */
    private function montar(Request $request): array
    {
        $data = $request->validate([
            'anl_id'  => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id'  => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'ser_id'  => ['nullable', 'integer', 'exists:edu_serie,ser_id'],
            'tur_id'  => ['nullable', 'integer', 'exists:edu_turma,tur_id'],
            // 'anual' | uni_id | vazio (todos)
            'periodo' => ['nullable', 'string', 'max:20'],
        ]);

        $anlId = (int) $data['anl_id'];
        $ano   = AnoLetivo::findOrFail($anlId);

        $escolas = UserAccess::escolasVisiveis(auth()->user());
        if (! empty($data['esc_id'])) {
            $escolas = $escolas->where('esc_id', (int) $data['esc_id'])->values();
        }
        $escolasIds = $escolas->pluck('esc_id')->map(fn ($id) => (int) $id)->all() ?: [-1];

        $unidades = IndicesAprovacao::unidades($anlId);
        $todosPeriodos = array_merge(
            array_map(fn ($u) => ['chave' => 'u' . $u['uni_id'], 'label' => $u['label']], $unidades),
            [['chave' => 'anual', 'label' => 'Anual']],
        );
        $periodoSel = $data['periodo'] ?? null;
        $periodos = match (true) {
            ! $periodoSel           => $todosPeriodos,
            $periodoSel === 'anual' => [['chave' => 'anual', 'label' => 'Anual']],
            default                 => array_values(array_filter($todosPeriodos, fn ($p) => $p['chave'] === 'u' . (int) $periodoSel)),
        };
        abort_if(! $periodos, 422, 'Período inválido para o ano letivo.');

        $turmas = $this->turmasQuery($escolasIds)
            ->where('t.tur_anl_id', $anlId)
            ->when(! empty($data['ser_id']), fn ($q) => $q->where('t.tur_ser_id', (int) $data['ser_id']))
            ->when(! empty($data['tur_id']), fn ($q) => $q->where('t.tur_id', (int) $data['tur_id']))
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->orderBy('e.esc_nome')->orderBy('s.ser_nome')->orderBy('t.tur_nome')
            ->get(['t.tur_id', 't.tur_nome', 't.tur_esc_id', 'e.esc_nome', 's.ser_nome']);

        $chaves = array_column($periodos, 'chave');
        $comIndices = function (array $periodosTurma) use ($chaves): array {
            $out = [];
            foreach ($chaves as $c) {
                $p = $periodosTurma[$c] ?? ['aprovados' => 0, 'reprovados' => 0, 'pendentes' => 0];
                $out[$c] = $p + IndicesAprovacao::percentuais($p);
            }

            return $out;
        };

        IndicesAprovacao::preparar($turmas->pluck('tur_id')->all());

        $porEscola = [];
        $rede = ['alunos' => 0, 'sem_avaliacao' => 0, 'periodos' => []];
        foreach ($turmas as $t) {
            $r = IndicesAprovacao::turma((int) $t->tur_id, $unidades);
            $esc = (int) $t->tur_esc_id;
            $porEscola[$esc] ??= ['esc_id' => $esc, 'esc_nome' => $t->esc_nome, 'alunos' => 0, 'sem_avaliacao' => 0, 'periodos' => [], 'turmas' => []];

            $porEscola[$esc]['turmas'][] = [
                'tur_id'        => (int) $t->tur_id,
                'turma'         => $t->tur_nome,
                'serie'         => $t->ser_nome,
                'alunos'        => $r['alunos'],
                'sem_avaliacao' => $r['sem_avaliacao'],
                'aviso'         => $r['aviso'],
                'indices'       => $comIndices($r['periodos']),
            ];

            if ($r['sem_avaliacao']) {
                $porEscola[$esc]['sem_avaliacao'] += $r['alunos'];
                $rede['sem_avaliacao'] += $r['alunos'];
                continue;
            }
            $porEscola[$esc]['alunos'] += $r['alunos'];
            $rede['alunos'] += $r['alunos'];
            $porEscola[$esc]['periodos'] = IndicesAprovacao::somar($porEscola[$esc]['periodos'], $r['periodos']);
            $rede['periodos'] = IndicesAprovacao::somar($rede['periodos'], $r['periodos']);
        }

        $escolasOut = array_map(function ($e) use ($comIndices) {
            $e['indices'] = $comIndices($e['periodos']);
            unset($e['periodos']);

            return $e;
        }, array_values($porEscola));

        $parametros = ParametroEntidade::first();
        $nome = fn ($tabela, $col, $id, $campo) => $id ? DB::table($tabela)->where($col, $id)->value($campo) : null;

        return [
            'parametros' => $parametros ? $parametros->cabecalhoRelatorio() : null,
            'filtros'    => [
                'anl_id'  => $anlId,
                'anl_ano' => $ano->anl_ano,
                'esc_id'  => $data['esc_id'] ?? null,
                'esc_nome' => $nome('edu_escola', 'esc_id', $data['esc_id'] ?? null, 'esc_nome'),
                'ser_id'  => $data['ser_id'] ?? null,
                'ser_nome' => $nome('edu_serie', 'ser_id', $data['ser_id'] ?? null, 'ser_nome'),
                'tur_id'  => $data['tur_id'] ?? null,
                'tur_nome' => $nome('edu_turma', 'tur_id', $data['tur_id'] ?? null, 'tur_nome'),
                'periodo' => $periodoSel,
            ],
            'periodos' => $periodos,
            'escolas'  => $escolasOut,
            'rede'     => [
                'alunos'        => $rede['alunos'],
                'sem_avaliacao' => $rede['sem_avaliacao'],
                'indices'       => $comIndices($rede['periodos']),
            ],
        ];
    }

    /** Turmas regulares das escolas visíveis. */
    private function turmasQuery(array $escolasIds)
    {
        return DB::table('edu_turma as t')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->whereIn('t.tur_esc_id', $escolasIds);
    }
}
