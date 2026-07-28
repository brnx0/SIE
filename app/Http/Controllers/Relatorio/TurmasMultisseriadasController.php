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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Relação de Alunos em Turmas Multisseriadas — alunos matriculados em turmas
 * cuja série é multisseriada (edu_serie.ser_fl_multi = true).
 *
 * Um aluno por vínculo (dedup pelo mais recente/ATIVA). Idade apurada na data de
 * referência (padrão: hoje). Ordenado por escola, turma e nome. Excel + PDF.
 */
class TurmasMultisseriadasController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();
        $escolas = UserAccess::escolasVisiveis($user);
        $escolasIds = $escolas->pluck('esc_id')->all() ?: [-1];

        return Inertia::render('relatorios/TurmasMultisseriadas/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => $escolas,
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
            'hoje'        => now()->toDateString(),
            'turnos'      => Turma::TURNOS,
            // Séries multisseriadas presentes na rede visível (p/ o filtro).
            'series' => DB::table('edu_serie as s')
                ->whereNull('s.ser_deleted_at')
                ->where('s.ser_fl_multi', true)
                ->orderBy('s.ser_nome')
                ->get(['s.ser_id', 's.ser_nome'])
                ->map(fn ($s) => ['ser_id' => (int) $s->ser_id, 'ser_nome' => $s->ser_nome]),
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

        return Inertia::render('relatorios/TurmasMultisseriadas/Resultado', [
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
            'Escola', 'Cód. Turma', 'Turma', 'Série / Ano Escolar', 'Turno',
            'Matrícula', 'Nome do Aluno', 'Data de Nascimento', 'Idade', 'Status do Aluno',
        ];

        $rows = $linhas->map(fn ($l) => [
            $l['esc_nome'], $l['turma_codigo'] ?? '', $l['turma'], $l['serie'] ?? '', $l['turno'] ?? '',
            $l['matricula'] ?? '', $l['nome'], $l['dt_nascimento'] ?? '', $l['idade'], $l['status'],
        ])->all();

        return XlsxExport::download('alunos_turmas_multisseriadas_' . now()->format('Ymd_His') . '.xlsx', $headers, $rows);
    }

    /**
     * @return array{parametros: array|null, filtros: array, linhas: Collection, resumo: array}
     */
    private function montar(Request $request): array
    {
        $data = $request->validate([
            'anl_id'   => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id'   => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'ser_id'   => ['nullable', 'integer', 'exists:edu_serie,ser_id'],
            'turno'    => ['nullable', Rule::in(Turma::TURNOS)],
            'situacao' => ['nullable', 'in:ativo,inativo,todos'],
            'data_ref' => ['nullable', 'date'],
        ]);

        $ano      = AnoLetivo::findOrFail($data['anl_id']);
        $dataRef  = ! empty($data['data_ref']) ? Carbon::parse($data['data_ref']) : now();
        $situacao = $data['situacao'] ?? 'todos';

        $escolas = UserAccess::escolasVisiveis(auth()->user());
        if (! empty($data['esc_id'])) {
            $escolas = $escolas->where('esc_id', (int) $data['esc_id'])->values();
        }
        $escolasIds = $escolas->pluck('esc_id')->map(fn ($id) => (int) $id)->all();

        $registros = $escolasIds ? $this->consultar($escolasIds, (int) $data['anl_id'], $situacao, $data) : collect();

        $linhas = $registros
            // Um aluno pode ter mais de um vínculo no ano: fica a matrícula ATIVA
            // mais recente, que representa a situação atual dele.
            ->sortByDesc(fn ($r) => [$r->tma_situacao === Matricula::SITUACAO_ATIVA ? 1 : 0, (int) $r->tma_id])
            ->unique('aln_id')
            ->map(function ($r) use ($dataRef) {
                $nasc = $r->aln_dt_nascimento ? Carbon::parse($r->aln_dt_nascimento) : null;

                return [
                    'aln_id'        => (int) $r->aln_id,
                    'esc_id'        => (int) $r->esc_id,
                    'esc_nome'      => $r->esc_nome,
                    'tur_id'        => (int) $r->tur_id,
                    'turma'         => $r->tur_nome,
                    'turma_codigo'  => $r->tur_cd_inep,
                    'serie'         => $r->serie,
                    'turno'         => $r->tur_turno,
                    'matricula'     => $r->aln_nr_matricula,
                    'nome'          => $r->aln_nome,
                    'dt_nascimento' => $nasc?->format('d/m/Y'),
                    'idade'         => $nasc ? (int) $nasc->diffInYears($dataRef) : null,
                    'ativo'         => (bool) $r->aln_fl_ativo,
                    'status'        => $r->aln_fl_ativo ? 'Ativo' : 'Inativo',
                ];
            })
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
                'ser_id'   => ! empty($data['ser_id']) ? (int) $data['ser_id'] : null,
                'ser_nome' => ! empty($data['ser_id'])
                    ? DB::table('edu_serie')->where('ser_id', $data['ser_id'])->value('ser_nome')
                    : null,
                'turno'    => $data['turno'] ?? null,
                'situacao' => $situacao,
                'data_ref' => $dataRef->format('d/m/Y'),
            ],
            'linhas' => $linhas,
            'resumo' => [
                'total'    => $linhas->count(),
                'escolas'  => $linhas->pluck('esc_id')->unique()->count(),
                'turmas'   => $linhas->pluck('tur_id')->unique()->count(),
                'ativos'   => $linhas->where('ativo', true)->count(),
                'inativos' => $linhas->where('ativo', false)->count(),
            ],
        ];
    }

    /** Vínculos do ano em turmas multisseriadas nas escolas dadas. */
    private function consultar(array $escolasIds, int $anlId, string $situacao, array $filtros): Collection
    {
        $q = DB::table('edu_turma_aluno as tma')
            ->join('edu_aluno as a', 'a.aln_id', '=', 'tma.tma_aln_id')
            ->join('edu_turma as t', 't.tur_id', '=', 'tma.tma_tur_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 't.tur_esc_id')
            // Série do vínculo (multi-seriada) com fallback para a da turma.
            ->join('edu_serie as s', fn ($j) => $j->on(
                DB::raw('s.ser_id'), '=', DB::raw('coalesce(tma.tma_ser_id, t.tur_ser_id)')
            ))
            ->whereNull('tma.tma_deleted_at')
            ->whereNull('a.aln_deleted_at')
            ->whereNull('t.tur_deleted_at')
            ->where('t.tur_anl_id', $anlId)
            ->whereIn('t.tur_esc_id', $escolasIds)
            // Turma multisseriada: a série é marcada como multi.
            ->where('s.ser_fl_multi', true)
            ->when($situacao === 'ativo', fn ($qq) => $qq->where('a.aln_fl_ativo', true))
            ->when($situacao === 'inativo', fn ($qq) => $qq->where('a.aln_fl_ativo', false))
            ->when(! empty($filtros['ser_id']), fn ($qq) => $qq->where(
                DB::raw('coalesce(tma.tma_ser_id, t.tur_ser_id)'), (int) $filtros['ser_id']
            ))
            ->when(! empty($filtros['turno']), fn ($qq) => $qq->where('t.tur_turno', $filtros['turno']));

        return collect($q->get([
            'tma.tma_id', 'tma.tma_situacao',
            'a.aln_id', 'a.aln_nome', 'a.aln_nr_matricula', 'a.aln_dt_nascimento', 'a.aln_fl_ativo',
            't.tur_id', 't.tur_nome', 't.tur_cd_inep', 't.tur_turno',
            'e.esc_id', 'e.esc_nome',
            's.ser_nome as serie',
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
