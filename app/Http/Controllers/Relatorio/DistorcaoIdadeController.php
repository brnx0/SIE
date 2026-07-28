<?php

namespace App\Http\Controllers\Relatorio;

use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Support\UserAccess;
use App\Support\XlsxExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Distorção Idade-Série — alunos cuja idade está acima da faixa adequada para a
 * série/ano em que estão matriculados.
 *
 * Critério: há distorção quando a idade do aluno está acima da idade
 * recomendada para a série (edu_serie.ser_idade), ou seja, distorção >= 1 ano.
 * A idade é apurada na data de referência (padrão: hoje). Séries sem `ser_idade`
 * não são avaliadas. Ordenado por escola, turma e nome.
 */
class DistorcaoIdadeController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();
        $escolas = UserAccess::escolasVisiveis($user);
        $escolasIds = $escolas->pluck('esc_id')->all() ?: [-1];

        return Inertia::render('relatorios/DistorcaoIdade/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => $escolas,
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
            'hoje'        => now()->toDateString(),
            // Turmas de todas as escolas visíveis; filtro por ano/escola é local.
            'turmas' => DB::table('edu_turma as t')
                ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
                ->whereNull('t.tur_deleted_at')
                ->where('t.tur_modalidade', 'REGULAR')
                ->whereIn('t.tur_esc_id', $escolasIds)
                ->orderBy('t.tur_nome')
                ->get(['t.tur_id', 't.tur_nome', 't.tur_esc_id', 't.tur_anl_id', 't.tur_ser_id', 's.ser_nome'])
                ->map(fn ($t) => [
                    'tur_id'   => (int) $t->tur_id,
                    'esc_id'   => (int) $t->tur_esc_id,
                    'anl_id'   => (int) $t->tur_anl_id,
                    'ser_id'   => $t->tur_ser_id !== null ? (int) $t->tur_ser_id : null,
                    'tur_nome' => trim(($t->ser_nome ? $t->ser_nome . ' - ' : '') . $t->tur_nome),
                ]),
            // Séries com idade recomendada (para o filtro por série).
            'series' => DB::table('edu_serie')
                ->whereNull('ser_deleted_at')
                ->whereNotNull('ser_idade')
                ->orderBy('ser_nome')
                ->get(['ser_id', 'ser_nome', 'ser_idade'])
                ->map(fn ($s) => ['ser_id' => (int) $s->ser_id, 'ser_nome' => $s->ser_nome, 'ser_idade' => (int) $s->ser_idade]),
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

        return Inertia::render('relatorios/DistorcaoIdade/Resultado', [
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
            'Escola', 'Cód. Escola', 'Turma', 'Ano Escolar / Série', 'Segmento',
            'Matrícula', 'Nome do Aluno', 'Data de Nascimento', 'Idade Atual',
            'Idade Esperada', 'Distorção (anos)',
        ];

        $rows = $linhas->map(fn ($l) => [
            $l['esc_nome'], $l['esc_codigo'] ?? '', $l['turma'], $l['serie'] ?? '', $l['segmento'] ?? '',
            $l['matricula'] ?? '', $l['nome'], $l['dt_nascimento'] ?? '', $l['idade'],
            $l['idade_esperada'], $l['distorcao'],
        ])->all();

        return XlsxExport::download('distorcao_idade_serie_' . now()->format('Ymd_His') . '.xlsx', $headers, $rows);
    }

    /**
     * @return array{parametros: array|null, filtros: array, linhas: Collection, resumo: array}
     */
    private function montar(Request $request): array
    {
        $data = $request->validate([
            'anl_id'   => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id'   => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id'   => ['nullable', 'integer', 'exists:edu_turma,tur_id'],
            'ser_id'   => ['nullable', 'integer', 'exists:edu_serie,ser_id'],
            'data_ref' => ['nullable', 'date'],
        ]);

        $ano     = AnoLetivo::findOrFail($data['anl_id']);
        $dataRef = ! empty($data['data_ref']) ? Carbon::parse($data['data_ref']) : now();

        $escolas = UserAccess::escolasVisiveis(auth()->user());
        if (! empty($data['esc_id'])) {
            $escolas = $escolas->where('esc_id', (int) $data['esc_id'])->values();
        }
        $escolasIds = $escolas->pluck('esc_id')->map(fn ($id) => (int) $id)->all();

        $registros = $escolasIds ? $this->consultar($escolasIds, (int) $data['anl_id'], $data) : collect();

        $todos = $registros
            // Um aluno pode ter mais de um vínculo no ano: fica a matrícula ATIVA
            // mais recente, que representa a situação atual dele.
            ->sortByDesc(fn ($r) => [$r->tma_situacao === Matricula::SITUACAO_ATIVA ? 1 : 0, (int) $r->tma_id])
            ->unique('aln_id')
            ->map(function ($r) use ($dataRef) {
                $nasc = $r->aln_dt_nascimento ? Carbon::parse($r->aln_dt_nascimento) : null;
                $idade = $nasc ? (int) $nasc->diffInYears($dataRef) : null;
                $esperada = $r->ser_idade !== null ? (int) $r->ser_idade : null;
                $distorcao = ($idade !== null && $esperada !== null) ? $idade - $esperada : null;

                return [
                    'aln_id'         => (int) $r->aln_id,
                    'esc_id'         => (int) $r->esc_id,
                    'esc_nome'       => $r->esc_nome,
                    'esc_codigo'     => $r->esc_cd_escola ?: $r->esc_cd_inep,
                    'tur_id'         => (int) $r->tur_id,
                    'turma'          => $r->tur_nome,
                    'serie'          => $r->serie,
                    'segmento'       => $r->segmento,
                    'matricula'      => $r->aln_nr_matricula,
                    'nome'           => $r->aln_nome,
                    'dt_nascimento'  => $nasc?->format('d/m/Y'),
                    'idade'          => $idade,
                    'idade_esperada' => $esperada,
                    'distorcao'      => $distorcao,
                ];
            })
            ->values();

        // Só quem tem distorção (idade acima da recomendada) e série avaliável.
        $linhas = $todos
            ->filter(fn ($l) => $l['distorcao'] !== null && $l['distorcao'] >= 1)
            ->sortBy([
                fn ($a, $b) => $this->cmp($a['esc_nome'], $b['esc_nome']),
                fn ($a, $b) => $this->cmp($a['turma'], $b['turma']),
                fn ($a, $b) => $this->cmp($a['nome'], $b['nome']),
            ])
            ->values();

        $parametros = ParametroEntidade::first();

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
                'tur_id'   => ! empty($data['tur_id']) ? (int) $data['tur_id'] : null,
                'tur_nome' => ! empty($data['tur_id'])
                    ? DB::table('edu_turma')->where('tur_id', $data['tur_id'])->value('tur_nome')
                    : null,
                'ser_id'   => ! empty($data['ser_id']) ? (int) $data['ser_id'] : null,
                'ser_nome' => ! empty($data['ser_id'])
                    ? DB::table('edu_serie')->where('ser_id', $data['ser_id'])->value('ser_nome')
                    : null,
                'data_ref' => $dataRef->format('d/m/Y'),
            ],
            'linhas' => $linhas,
            'resumo' => [
                'total'          => $linhas->count(),
                'escolas'        => $linhas->pluck('esc_id')->unique()->count(),
                'turmas'         => $linhas->pluck('tur_id')->unique()->count(),
                'distorcao_max'  => $linhas->max('distorcao'),
                'avaliados'      => $todos->filter(fn ($l) => $l['idade_esperada'] !== null)->count(),
                'sem_referencia' => $todos->filter(fn ($l) => $l['idade_esperada'] === null)->count(),
            ],
        ];
    }

    /** Vínculos regulares do ano nas escolas dadas, com série e idade recomendada. */
    private function consultar(array $escolasIds, int $anlId, array $filtros): Collection
    {
        $q = DB::table('edu_turma_aluno as tma')
            ->join('edu_aluno as a', 'a.aln_id', '=', 'tma.tma_aln_id')
            ->join('edu_turma as t', 't.tur_id', '=', 'tma.tma_tur_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            // Série do vínculo (multi-seriada) com fallback para a da turma.
            ->leftJoin('edu_serie as s', fn ($j) => $j->on(
                DB::raw('s.ser_id'), '=', DB::raw('coalesce(tma.tma_ser_id, t.tur_ser_id)')
            ))
            ->leftJoin('edu_segmento as seg', fn ($j) => $j->on(
                DB::raw('seg.seg_id'), '=', DB::raw('coalesce(s.seg_id, t.tur_seg_id)')
            ))
            ->whereNull('tma.tma_deleted_at')
            ->whereNull('a.aln_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_modalidade', 'REGULAR')
            ->where('t.tur_anl_id', $anlId)
            ->whereIn('t.tur_esc_id', $escolasIds)
            ->whereNotNull('a.aln_dt_nascimento');

        if (! empty($filtros['tur_id'])) {
            $q->where('t.tur_id', (int) $filtros['tur_id']);
        }
        if (! empty($filtros['ser_id'])) {
            $q->where(DB::raw('coalesce(tma.tma_ser_id, t.tur_ser_id)'), (int) $filtros['ser_id']);
        }

        return collect($q->get([
            'tma.tma_id', 'tma.tma_situacao',
            'a.aln_id', 'a.aln_nome', 'a.aln_nr_matricula', 'a.aln_dt_nascimento',
            't.tur_id', 't.tur_nome',
            'e.esc_id', 'e.esc_nome', 'e.esc_cd_escola', 'e.esc_cd_inep',
            's.ser_nome as serie', 's.ser_idade', 'seg.seg_nome_reduzido as segmento',
        ]));
    }

    /** Comparação alfabética insensível a acento/caixa. */
    private function cmp(?string $a, ?string $b): int
    {
        return strcmp(
            Str::ascii(mb_strtolower((string) $a)),
            Str::ascii(mb_strtolower((string) $b)),
        );
    }
}
