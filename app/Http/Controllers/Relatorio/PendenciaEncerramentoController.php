<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Support\UserAccess;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Escolas com pendência de encerramento de turmas e/ou renovação de matrículas.
 *
 * Definições (as duas categorias são DISJUNTAS de propósito, porque renovar só
 * é possível depois de encerrar — ver ConfirmacaoRenovacaoController):
 *
 * - Turma SEM ENCERRAMENTO: tur_situacao <> 'ENCERRADA'.
 * - Turma SEM RENOVAÇÃO:    já encerrada, mas nenhuma das suas matrículas gerou
 *                           matrícula no ano seguinte (edu_turma_aluno.tma_origem_tma_id).
 *
 * Assim "sem encerramento + sem renovação" nunca conta a mesma turma duas vezes
 * e a soma bate com o total de turmas pendentes da escola.
 */
class PendenciaEncerramentoController extends Controller
{
    private const SITUACAO_ENCERRADA = 'ENCERRADA';

    private const MODALIDADE = 'REGULAR';

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/PendenciaEncerramento/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
            'segmentos'   => DB::table('edu_segmento')
                ->whereNull('seg_deleted_at')
                ->where('seg_fl_ativo', true)
                ->orderBy('seg_ordem')
                ->get(['seg_id', 'seg_nome_reduzido', 'seg_nome_completo'])
                ->map(fn ($s) => [
                    'seg_id'   => (int) $s->seg_id,
                    'seg_nome' => $s->seg_nome_reduzido ?: $s->seg_nome_completo,
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

        return Inertia::render('relatorios/PendenciaEncerramento/Resultado', [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'resumo'     => $resumo,
        ]);
    }

    public function exportarXlsx(Request $request): \Illuminate\Http\Response
    {
        ['filtros' => $filtros, 'linhas' => $linhas] = $this->montar($request);

        $nome = 'pendencias_encerramento_' . now()->format('Ymd_His') . '.xlsx';

        if ($filtros['formato'] === 'detalhado') {
            $headers = ['Escola', 'Código', 'Etapa', 'Ano Escolar / Série', 'Turma', 'Turno', 'Situação da Turma', 'Pendência', 'Alunos', 'Alunos Renovados'];

            $rows = [];
            foreach ($linhas as $l) {
                foreach ($l['turmas'] as $t) {
                    $rows[] = [
                        $l['esc_nome'], $l['esc_codigo'] ?? '',
                        $t['segmento'] ?? '', $t['serie'] ?? '', $t['turma'] ?? '', $t['turno'] ?? '',
                        $t['situacao'] ?? '', $t['pendencia'], $t['alunos'], $t['renovados'],
                    ];
                }
            }

            return XlsxExport::download($nome, $headers, $rows);
        }

        $headers = ['Escola', 'Código', 'Turmas sem Encerramento', 'Turmas sem Renovação', 'Total Pendente', 'Total de Turmas', 'Etapas / Anos Escolares Afetados'];

        $rows = $linhas->map(fn ($l) => [
            $l['esc_nome'],
            $l['esc_codigo'] ?? '',
            $l['sem_encerramento'],
            $l['sem_renovacao'],
            $l['total_pendente'],
            $l['total_turmas'],
            implode(' / ', $l['afetados']),
        ])->all();

        return XlsxExport::download($nome, $headers, $rows);
    }

    /**
     * Monta escolas pendentes (compartilhado entre tela e export).
     *
     * @return array{parametros: array|null, filtros: array, linhas: Collection, resumo: array}
     */
    private function montar(Request $request): array
    {
        $data = $request->validate([
            'anl_id'  => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id'  => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'seg_id'  => ['nullable', 'integer', 'exists:edu_segmento,seg_id'],
            'formato' => ['nullable', 'in:consolidado,detalhado'],
        ]);

        $formato = $data['formato'] ?? 'consolidado';
        $ano     = AnoLetivo::findOrFail($data['anl_id']);

        $escolas = UserAccess::escolasVisiveis(auth()->user());
        if (! empty($data['esc_id'])) {
            $escolas = $escolas->where('esc_id', (int) $data['esc_id'])->values();
        }
        $escolasIds = $escolas->pluck('esc_id')->map(fn ($id) => (int) $id)->all();

        $turmas = $this->turmas($escolasIds, (int) $data['anl_id'], $data['seg_id'] ?? null);

        // Turmas encerradas que JÁ produziram ao menos uma matrícula no ano seguinte.
        $renovadasPorTurma = $this->renovacoesPorTurma($turmas->pluck('tur_id')->all());
        $alunosPorTurma    = $this->alunosPorTurma($turmas->pluck('tur_id')->all());

        $porEscola = $turmas
            ->map(function ($t) use ($renovadasPorTurma, $alunosPorTurma) {
                $encerrada = $t->tur_situacao === self::SITUACAO_ENCERRADA;
                $renovados = (int) ($renovadasPorTurma[(int) $t->tur_id] ?? 0);

                $t->encerrada = $encerrada;
                $t->renovados = $renovados;
                $t->alunos    = (int) ($alunosPorTurma[(int) $t->tur_id] ?? 0);
                // Disjunto: turma não encerrada nunca entra em "sem renovação".
                $t->pendencia = ! $encerrada
                    ? 'Sem encerramento'
                    : ($renovados === 0 ? 'Sem renovação' : null);

                return $t;
            })
            ->groupBy('tur_esc_id');

        $detalhes = $escolasIds
            ? DB::table('edu_escola')->whereIn('esc_id', $escolasIds)
                ->get(['esc_id', 'esc_cd_escola', 'esc_cd_inep'])->keyBy('esc_id')
            : collect();

        $linhas = $escolas
            ->map(function ($e) use ($porEscola, $detalhes) {
                $daEscola  = $porEscola->get($e->esc_id, collect());
                $pendentes = $daEscola->filter(fn ($t) => $t->pendencia !== null)->values();
                $d         = $detalhes->get($e->esc_id);

                return [
                    'esc_id'           => (int) $e->esc_id,
                    'esc_nome'         => $e->esc_nome,
                    'esc_codigo'       => $d?->esc_cd_escola ?: $d?->esc_cd_inep,
                    'total_turmas'     => $daEscola->count(),
                    'sem_encerramento' => $pendentes->where('pendencia', 'Sem encerramento')->count(),
                    'sem_renovacao'    => $pendentes->where('pendencia', 'Sem renovação')->count(),
                    'total_pendente'   => $pendentes->count(),
                    'afetados'         => $pendentes
                        ->map(fn ($t) => trim(($t->segmento ?? '—') . ' · ' . ($t->serie ?? '—')))
                        ->unique()->sort()->values()->all(),
                    'turmas' => $pendentes->map(fn ($t) => [
                        'tur_id'    => (int) $t->tur_id,
                        'turma'     => $t->tur_nome,
                        'serie'     => $t->serie,
                        'segmento'  => $t->segmento,
                        'turno'     => $t->tur_turno,
                        'situacao'  => $t->tur_situacao,
                        'alunos'    => $t->alunos,
                        'renovados' => $t->renovados,
                        'pendencia' => $t->pendencia,
                    ])->all(),
                ];
            })
            // O relatório é de PENDÊNCIA: escola em dia não polui a lista.
            ->filter(fn ($l) => $l['total_pendente'] > 0)
            ->values();

        $parametros = ParametroEntidade::first();

        return [
            'parametros' => $parametros ? $parametros->cabecalhoRelatorio() : null,
            'filtros' => [
                'anl_id'   => (int) $ano->anl_id,
                'anl_ano'  => (int) $ano->anl_ano,
                'esc_id'   => ! empty($data['esc_id']) ? (int) $data['esc_id'] : null,
                'esc_nome' => ! empty($data['esc_id']) ? ($escolas->first()->esc_nome ?? null) : null,
                'seg_id'   => ! empty($data['seg_id']) ? (int) $data['seg_id'] : null,
                'seg_nome' => ! empty($data['seg_id'])
                    ? DB::table('edu_segmento')->where('seg_id', $data['seg_id'])->value('seg_nome_reduzido')
                    : null,
                'formato'  => $formato,
            ],
            'linhas' => $linhas,
            'resumo' => [
                'escolas_pendentes' => $linhas->count(),
                'escolas_avaliadas' => $escolas->count(),
                'sem_encerramento'  => $linhas->sum('sem_encerramento'),
                'sem_renovacao'     => $linhas->sum('sem_renovacao'),
                'total_pendente'    => $linhas->sum('total_pendente'),
                'total_turmas'      => $turmas->count(),
            ],
        ];
    }

    /** Turmas REGULAR do ano/escolas, com segmento resolvido (série → segmento, senão o da turma). */
    private function turmas(array $escolasIds, int $anlId, ?int $segId): Collection
    {
        if (! $escolasIds) {
            return collect();
        }

        $q = DB::table('edu_turma as t')
            ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
            // Segmento vem da série; turmas sem série caem no segmento da própria turma.
            ->leftJoin('edu_segmento as seg', fn ($j) => $j->on(
                DB::raw('seg.seg_id'), '=', DB::raw('coalesce(s.seg_id, t.tur_seg_id)')
            ))
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', self::MODALIDADE)
            ->where('t.tur_anl_id', $anlId)
            ->whereIn('t.tur_esc_id', $escolasIds);

        if ($segId) {
            $q->whereRaw('coalesce(s.seg_id, t.tur_seg_id) = ?', [$segId]);
        }

        return collect($q
            ->orderBy('seg.seg_ordem')->orderBy('s.ser_nome')->orderBy('t.tur_nome')
            ->get([
                't.tur_id', 't.tur_esc_id', 't.tur_nome', 't.tur_turno', 't.tur_situacao',
                's.ser_nome as serie', 'seg.seg_nome_reduzido as segmento',
            ]));
    }

    /**
     * Nº de matrículas renovadas (que geraram matrícula no ano seguinte) por turma.
     *
     * @return array<int,int>
     */
    private function renovacoesPorTurma(array $turIds): array
    {
        if (! $turIds) {
            return [];
        }

        return DB::table('edu_turma_aluno as origem')
            ->join('edu_turma_aluno as filha', function ($j) {
                $j->on('filha.tma_origem_tma_id', '=', 'origem.tma_id')
                    ->whereNull('filha.tma_deleted_at');
            })
            ->whereIn('origem.tma_tur_id', $turIds)
            ->whereNull('origem.tma_deleted_at')
            ->groupBy('origem.tma_tur_id')
            // Alias explícito: pluck(DB::raw('count(*)')) não resolve a coluna.
            ->selectRaw('origem.tma_tur_id as tur_id, count(*) as total')
            ->pluck('total', 'tur_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Nº de matrículas ativas por turma (dimensiona o esforço da pendência).
     *
     * @return array<int,int>
     */
    private function alunosPorTurma(array $turIds): array
    {
        if (! $turIds) {
            return [];
        }

        return DB::table('edu_turma_aluno')
            ->whereIn('tma_tur_id', $turIds)
            ->whereNull('tma_deleted_at')
            ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
            ->groupBy('tma_tur_id')
            ->selectRaw('tma_tur_id as tur_id, count(*) as total')
            ->pluck('total', 'tur_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
