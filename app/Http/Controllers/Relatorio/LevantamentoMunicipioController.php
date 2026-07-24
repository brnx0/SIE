<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Models\Turma\Turma;
use App\Support\UserAccess;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Levantamento de Dados do Município — panorama consolidado da rede, com uma
 * linha por turma: escola, identificação da turma, segmento/série, turno,
 * capacidade, matrícula, professores e indicadores de diário.
 *
 * SOBRE A COLUNA DE FREQUÊNCIA — leia antes de mexer:
 *
 * Este relatório NÃO usa App\Support\Diario\FrequenciaMensal. Aquela classe é a
 * fórmula oficial por ALUNO (modo 'dias' vs 'aulas' conforme o segmento da série
 * do aluno) e exige várias queries por turma — inviável para a rede inteira.
 *
 * Aqui a coluna é um indicador OPERACIONAL de turma, calculado em uma query:
 *
 *     frequência lançada = presenças ÷ registros de chamada
 *
 * sobre as aulas executadas (aul_fl_nao_executada = false) e não migradas do ano
 * letivo. Serve para dimensionar o preenchimento do diário, NÃO para decidir
 * aprovação/reprovação por falta — para isso valem FrequenciaMensal (mensal) e
 * CalculoEncerramento (anual). A tela rotula a coluna como "Freq. lançada" e
 * explica a diferença, para ninguém confundir os números.
 */
class LevantamentoMunicipioController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/LevantamentoMunicipio/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
            'turnos'      => Turma::TURNOS,
            'segmentos'   => DB::table('edu_segmento')
                ->whereNull('seg_deleted_at')->where('seg_fl_ativo', true)
                ->orderBy('seg_ordem')
                ->get(['seg_id', 'seg_nome_reduzido', 'seg_nome_completo'])
                ->map(fn ($s) => [
                    'seg_id'   => (int) $s->seg_id,
                    'seg_nome' => $s->seg_nome_reduzido ?: $s->seg_nome_completo,
                ]),
            // Séries com o segmento junto: o filtro de série é filtrado no cliente
            // pelo segmento escolhido, sem ida ao servidor (evita cascata assíncrona).
            'series' => DB::table('edu_serie')
                ->whereNull('ser_deleted_at')->where('ser_fl_ativo', true)
                ->orderBy('ser_nome')
                ->get(['ser_id', 'ser_nome', 'seg_id'])
                ->map(fn ($s) => [
                    'ser_id'   => (int) $s->ser_id,
                    'ser_nome' => $s->ser_nome,
                    'seg_id'   => $s->seg_id !== null ? (int) $s->seg_id : null,
                ]),
        ]);
    }

    public function gerar(Request $request): Response
    {
        [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'resumo'     => $resumo,
        ] = $this->montar($request);

        return Inertia::render('relatorios/LevantamentoMunicipio/Resultado', [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'resumo'     => $resumo,
        ]);
    }

    public function exportarXlsx(Request $request): \Illuminate\Http\Response
    {
        ['linhas' => $linhas] = $this->montar($request);

        $headers = [
            'Escola', 'Cód. Escola', 'Cód. Turma', 'Turma', 'Modalidade', 'Segmento', 'Ano Escolar / Série',
            'Turno', 'Situação da Turma', 'Capacidade', 'Expansão', 'Matriculados', 'Vagas', '% Ocupação',
            'Transferidos', 'Evadidos', 'Cancelados', 'Total de Vínculos',
            'Nº Professores', 'Professor(es) Responsável(is)', 'Professores de Apoio',
            'Aulas Registradas', 'Registros de Chamada', 'Faltas', 'Freq. Lançada (%)',
        ];

        $rows = $linhas->map(fn ($l) => [
            $l['esc_nome'], $l['esc_codigo'] ?? '', $l['tur_codigo'] ?? '', $l['turma'],
            $l['modalidade'], $l['segmento'] ?? '', $l['serie'] ?? '', $l['turno'] ?? '', $l['situacao'] ?? '',
            $l['capacidade'], $l['expansao'], $l['matriculados'], $l['vagas'], $l['ocupacao'],
            $l['transferidos'], $l['evadidos'], $l['cancelados'], $l['total_vinculos'],
            $l['qt_professores'], $l['professores_texto'], $l['apoio_texto'],
            $l['aulas'], $l['registros_chamada'], $l['faltas'], $l['freq_lancada'],
        ])->all();

        return XlsxExport::download('levantamento_municipio_' . now()->format('Ymd_His') . '.xlsx', $headers, $rows);
    }

    /**
     * Monta uma linha por turma (compartilhado entre tela e export).
     *
     * Todas as agregações são feitas em queries em lote (matrícula, professores,
     * apoio, aulas, chamada) e cruzadas em memória — nada de consulta por turma.
     *
     * @return array{parametros: array|null, filtros: array, linhas: Collection, resumo: array}
     */
    private function montar(Request $request): array
    {
        $data = $request->validate([
            'anl_id' => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id' => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'seg_id' => ['nullable', 'integer', 'exists:edu_segmento,seg_id'],
            'ser_id' => ['nullable', 'integer', 'exists:edu_serie,ser_id'],
            'turno'  => ['nullable', Rule::in(Turma::TURNOS)],
        ]);

        $ano = AnoLetivo::findOrFail($data['anl_id']);

        $escolas = UserAccess::escolasVisiveis(auth()->user());
        if (! empty($data['esc_id'])) {
            $escolas = $escolas->where('esc_id', (int) $data['esc_id'])->values();
        }
        $escolasIds = $escolas->pluck('esc_id')->map(fn ($id) => (int) $id)->all();

        $turmas = $this->turmas($escolasIds, (int) $data['anl_id'], $data);
        $turIds = $turmas->pluck('tur_id')->map(fn ($id) => (int) $id)->all();

        $matricula   = $this->matriculaPorTurma($turIds);
        $professores = $this->professoresPorTurma($turIds);
        $apoio       = $this->apoioPorTurma($turIds);
        $diario      = $this->diarioPorTurma($turIds, (int) $data['anl_id']);

        $linhas = $turmas->map(function ($t) use ($matricula, $professores, $apoio, $diario) {
            $tid = (int) $t->tur_id;
            $m   = $matricula[$tid] ?? [];
            $d   = $diario[$tid] ?? ['aulas' => 0, 'registros' => 0, 'faltas' => 0];

            $ativos     = (int) ($m[Matricula::SITUACAO_ATIVA] ?? 0);
            $capacidade = (int) ($t->tur_capacidade ?? 0);
            $expansao   = (int) ($t->tur_qt_expansao ?? 0);
            $lotacao    = $capacidade + $expansao;

            $profs = $professores[$tid] ?? [];
            $aps   = $apoio[$tid] ?? [];

            $registros = (int) $d['registros'];
            $faltas    = (int) $d['faltas'];

            return [
                'tur_id'     => $tid,
                'esc_id'     => (int) $t->tur_esc_id,
                'esc_nome'   => $t->esc_nome,
                'esc_codigo' => $t->esc_cd_escola ?: $t->esc_cd_inep,
                'tur_codigo' => $t->tur_cd_inep,
                'turma'      => $t->tur_nome,
                'modalidade' => $t->tur_modalidade,
                'segmento'   => $t->segmento,
                'serie'      => $t->serie,
                'turno'      => $t->tur_turno,
                'situacao'   => $t->tur_situacao,

                'capacidade'   => $capacidade,
                'expansao'     => $expansao,
                'matriculados' => $ativos,
                // Vagas consideram a expansão autorizada; sem capacidade, não há vaga a calcular.
                'vagas'        => $lotacao > 0 ? max(0, $lotacao - $ativos) : null,
                'ocupacao'     => $lotacao > 0 ? round($ativos / $lotacao * 100, 1) : null,

                'transferidos'   => (int) ($m[Matricula::SITUACAO_TRANSFERIDA] ?? 0),
                'evadidos'       => (int) ($m[Matricula::SITUACAO_EVADIDO] ?? 0),
                'cancelados'     => (int) ($m[Matricula::SITUACAO_CANCELADA] ?? 0),
                'total_vinculos' => array_sum($m),

                'qt_professores'    => count($profs),
                'professores'       => $profs,
                'professores_texto' => implode('; ', array_map(
                    fn ($p) => $p['disciplina'] ? "{$p['nome']} ({$p['disciplina']})" : $p['nome'],
                    $profs,
                )),
                'apoio'       => $aps,
                'apoio_texto' => implode('; ', $aps),

                'aulas'             => (int) $d['aulas'],
                'registros_chamada' => $registros,
                'faltas'            => $faltas,
                'freq_lancada'      => $registros > 0
                    ? round(($registros - $faltas) / $registros * 100, 1)
                    : null,
            ];
        });

        $parametros = ParametroEntidade::first();
        $comCap     = $linhas->filter(fn ($l) => $l['ocupacao'] !== null);
        $comFreq    = $linhas->filter(fn ($l) => $l['freq_lancada'] !== null);

        return [
            'parametros' => $parametros ? [
                'nome_entidade'      => $parametros->par_nome_entidade,
                'msg_cab_secretaria' => $parametros->par_msg_cab_secretaria,
                'msg_cab_estado'     => $parametros->par_msg_cab_estado,
                'endereco'           => $parametros->par_endereco,
                'logomarca_url'      => $parametros->par_logomarca_url,
                'brasao_url'         => $parametros->par_brasao_url,
            ] : null,
            'filtros' => [
                'anl_id'   => (int) $ano->anl_id,
                'anl_ano'  => (int) $ano->anl_ano,
                'esc_id'   => ! empty($data['esc_id']) ? (int) $data['esc_id'] : null,
                'esc_nome' => ! empty($data['esc_id']) ? ($escolas->first()->esc_nome ?? null) : null,
                'seg_id'   => ! empty($data['seg_id']) ? (int) $data['seg_id'] : null,
                'seg_nome' => ! empty($data['seg_id'])
                    ? DB::table('edu_segmento')->where('seg_id', $data['seg_id'])->value('seg_nome_reduzido')
                    : null,
                'ser_id'   => ! empty($data['ser_id']) ? (int) $data['ser_id'] : null,
                'ser_nome' => ! empty($data['ser_id'])
                    ? DB::table('edu_serie')->where('ser_id', $data['ser_id'])->value('ser_nome')
                    : null,
                'turno'    => $data['turno'] ?? null,
            ],
            'linhas' => $linhas,
            'resumo' => [
                'escolas'          => $linhas->pluck('esc_id')->unique()->count(),
                'turmas'           => $linhas->count(),
                'matriculados'     => $linhas->sum('matriculados'),
                'capacidade'       => $linhas->sum(fn ($l) => $l['capacidade'] + $l['expansao']),
                'vagas'            => $linhas->sum(fn ($l) => $l['vagas'] ?? 0),
                'ocupacao_media'   => $comCap->count() ? round($comCap->avg('ocupacao'), 1) : null,
                'professores'      => $linhas->sum('qt_professores'),
                'turmas_sem_prof'  => $linhas->filter(fn ($l) => $l['qt_professores'] === 0)->count(),
                'turmas_sem_aula'  => $linhas->filter(fn ($l) => $l['aulas'] === 0)->count(),
                'freq_media'       => $comFreq->count() ? round($comFreq->avg('freq_lancada'), 1) : null,
            ],
        ];
    }

    /** Turmas do ano/escolas com escola, série e segmento resolvidos. */
    private function turmas(array $escolasIds, int $anlId, array $filtros): Collection
    {
        if (! $escolasIds) {
            return collect();
        }

        $q = DB::table('edu_turma as t')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            // AEE/Atividade podem não ter série: cai no segmento da própria turma.
            ->leftJoin('edu_segmento as seg', fn ($j) => $j->on(
                DB::raw('seg.seg_id'), '=', DB::raw('coalesce(s.seg_id, t.tur_seg_id)')
            ))
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_anl_id', $anlId)
            ->whereIn('t.tur_esc_id', $escolasIds);

        if (! empty($filtros['seg_id'])) {
            $q->whereRaw('coalesce(s.seg_id, t.tur_seg_id) = ?', [(int) $filtros['seg_id']]);
        }
        if (! empty($filtros['ser_id'])) {
            $q->where('t.tur_ser_id', (int) $filtros['ser_id']);
        }
        if (! empty($filtros['turno'])) {
            $q->where('t.tur_turno', $filtros['turno']);
        }

        return collect($q
            ->orderBy('e.esc_nome')->orderBy('seg.seg_ordem')->orderBy('s.ser_nome')->orderBy('t.tur_nome')
            ->get([
                't.tur_id', 't.tur_esc_id', 't.tur_nome', 't.tur_cd_inep', 't.tur_turno',
                't.tur_capacidade', 't.tur_qt_expansao', 't.tur_situacao', 't.tur_modalidade',
                'e.esc_nome', 'e.esc_cd_escola', 'e.esc_cd_inep',
                's.ser_nome as serie', 'seg.seg_nome_reduzido as segmento',
            ]));
    }

    /**
     * Vínculos por turma e situação: [tur_id => ['ATIVA' => n, 'EVADIDO' => n, ...]].
     *
     * @return array<int, array<string,int>>
     */
    private function matriculaPorTurma(array $turIds): array
    {
        if (! $turIds) {
            return [];
        }

        $out = [];
        $rows = DB::table('edu_turma_aluno')
            ->whereIn('tma_tur_id', $turIds)
            ->whereNull('tma_deleted_at')
            ->groupBy('tma_tur_id', 'tma_situacao')
            ->selectRaw('tma_tur_id, tma_situacao, count(*) as total')
            ->get();

        foreach ($rows as $r) {
            $out[(int) $r->tma_tur_id][$r->tma_situacao] = (int) $r->total;
        }

        return $out;
    }

    /**
     * Regentes por turma (edu_turma_professor). tup_dis_id nulo = vínculo AEE.
     *
     * @return array<int, array<int, array{nome: string, disciplina: ?string}>>
     */
    private function professoresPorTurma(array $turIds): array
    {
        if (! $turIds) {
            return [];
        }

        $out = [];
        $rows = DB::table('edu_turma_professor as tp')
            ->join('edu_funcionario as f', 'f.fun_id', '=', 'tp.tup_fun_id')
            ->leftJoin('edu_disciplina as d', 'd.dis_id', '=', 'tp.tup_dis_id')
            ->whereIn('tp.tup_tur_id', $turIds)
            ->whereNull('tp.tup_deleted_at')
            ->orderBy('f.fun_nome')
            ->get(['tp.tup_tur_id', 'f.fun_nome', 'f.fun_nome_social', 'd.dis_nome']);

        foreach ($rows as $r) {
            $out[(int) $r->tup_tur_id][] = [
                'nome'       => $r->fun_nome_social ?: $r->fun_nome,
                'disciplina' => $r->dis_nome,
            ];
        }

        return $out;
    }

    /**
     * Professores de apoio por turma (tabela sem soft delete).
     *
     * @return array<int, array<int, string>>
     */
    private function apoioPorTurma(array $turIds): array
    {
        if (! $turIds) {
            return [];
        }

        $out = [];
        $rows = DB::table('edu_turma_professor_apoio as ap')
            ->join('edu_funcionario as f', 'f.fun_id', '=', 'ap.tpa_fun_id')
            ->whereIn('ap.tpa_tur_id', $turIds)
            ->orderBy('f.fun_nome')
            ->get(['ap.tpa_tur_id', 'f.fun_nome', 'f.fun_nome_social']);

        foreach ($rows as $r) {
            $out[(int) $r->tpa_tur_id][] = $r->fun_nome_social ?: $r->fun_nome;
        }

        return $out;
    }

    /**
     * Indicadores de diário por turma: aulas executadas, registros de chamada e faltas.
     *
     * Espelha os filtros de FrequenciaMensal (não migradas, executadas, não
     * excluídas), porém agregando a turma inteira no ano — ver nota da classe.
     *
     * @return array<int, array{aulas:int, registros:int, faltas:int}>
     */
    private function diarioPorTurma(array $turIds, int $anlId): array
    {
        if (! $turIds) {
            return [];
        }

        $out = [];

        $aulas = DB::table('edu_diario_aula')
            ->whereIn('aul_tur_id', $turIds)
            ->where('aul_anl_id', $anlId)
            ->where('aul_fl_migrada', false)
            ->where('aul_fl_nao_executada', false)
            ->whereNull('aul_deleted_at')
            ->groupBy('aul_tur_id')
            ->selectRaw('aul_tur_id, count(*) as total')
            ->pluck('total', 'aul_tur_id');

        foreach ($aulas as $turId => $total) {
            $out[(int) $turId] = ['aulas' => (int) $total, 'registros' => 0, 'faltas' => 0];
        }

        $chamada = DB::table('edu_diario_falta as fa')
            ->join('edu_diario_aula as au', 'au.aul_id', '=', 'fa.fal_aul_id')
            ->whereIn('au.aul_tur_id', $turIds)
            ->where('au.aul_anl_id', $anlId)
            ->where('au.aul_fl_migrada', false)
            ->where('au.aul_fl_nao_executada', false)
            ->whereNull('au.aul_deleted_at')
            ->whereNull('fa.fal_deleted_at')
            ->groupBy('au.aul_tur_id')
            ->selectRaw('au.aul_tur_id as tur_id, count(*) as registros, count(*) filter (where fa.fal_fl_presente = false) as faltas')
            ->get();

        foreach ($chamada as $r) {
            $tid = (int) $r->tur_id;
            $out[$tid] ??= ['aulas' => 0, 'registros' => 0, 'faltas' => 0];
            $out[$tid]['registros'] = (int) $r->registros;
            $out[$tid]['faltas']    = (int) $r->faltas;
        }

        return $out;
    }
}
