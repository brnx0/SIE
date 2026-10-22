<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Matricula\Matricula;
use App\Models\Parametro\AnoLetivo;
use App\Models\Parametro\ParametroEntidade;
use App\Support\UserAccess;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alunos Aptos para EJA — alunos com IDADE_MINIMA anos completos na data de
 * referência, ordenados por escola, turma e nome.
 *
 * A regra de idade é aplicada em SQL como `aln_dt_nascimento <= referência - 15
 * anos`, que é a tradução exata de "15 anos completos na data de referência" e
 * não depende de aritmética de data em PHP (nem sofre com aniversário no dia).
 *
 * Alunos que JÁ estão em turma de EJA são identificados pelo nome do segmento e,
 * por padrão, ocultados: o relatório é de quem pode INGRESSAR. O resumo informa
 * quantos foram ocultados para o número nunca parecer arbitrário.
 */
class AlunosAptosEjaController extends Controller
{
    private const IDADE_MINIMA = 15;

    public function form(): Response
    {
        $user = auth()->user();
        $escolas = UserAccess::escolasVisiveis($user);

        return Inertia::render('relatorios/AlunosAptosEja/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => $escolas,
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
            'hoje'        => now()->toDateString(),
            'idadeMinima' => self::IDADE_MINIMA,
            // Turmas de todos os anos/escolas visíveis: o filtro de turma é
            // aplicado no cliente (por ano + escola), evitando cascata assíncrona.
            'turmas' => DB::table('edu_turma as t')
                ->leftJoin('edu_serie as s', 's.ser_id', '=', 't.tur_ser_id')
                ->whereNull('t.tur_deleted_at')
                ->whereIn('t.tur_esc_id', $escolas->pluck('esc_id')->all() ?: [-1])
                ->orderBy('t.tur_nome')
                ->get(['t.tur_id', 't.tur_nome', 't.tur_esc_id', 't.tur_anl_id', 's.ser_nome'])
                ->map(fn ($t) => [
                    'tur_id'   => (int) $t->tur_id,
                    'esc_id'   => (int) $t->tur_esc_id,
                    'anl_id'   => (int) $t->tur_anl_id,
                    'tur_nome' => trim(($t->ser_nome ? $t->ser_nome . ' - ' : '') . $t->tur_nome),
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

        return Inertia::render('relatorios/AlunosAptosEja/Resultado', [
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
            'Escola', 'Cód. Escola', 'Turma', 'Ano Escolar / Série', 'Etapa',
            'Matrícula', 'Nome do Aluno', 'Data de Nascimento', 'Idade',
            'Situação da Matrícula', 'Situação do Aluno', 'Já em EJA',
        ];

        $rows = $linhas->map(fn ($l) => [
            $l['esc_nome'], $l['esc_codigo'] ?? '', $l['turma'], $l['serie'] ?? '', $l['segmento'] ?? '',
            $l['matricula'] ?? '', $l['nome'], $l['dt_nascimento'] ?? '', $l['idade'],
            $l['situacao_matricula'], $l['situacao_aluno'], $l['ja_eja'] ? 'Sim' : 'Não',
        ])->all();

        return XlsxExport::download('alunos_aptos_eja_' . now()->format('Ymd_His') . '.xlsx', $headers, $rows);
    }

    /**
     * @return array{parametros: array|null, filtros: array, linhas: Collection, resumo: array}
     */
    private function montar(Request $request): array
    {
        $data = $request->validate([
            'anl_id'      => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id'      => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'tur_id'      => ['nullable', 'integer', 'exists:edu_turma,tur_id'],
            'data_ref'    => ['nullable', 'date'],
            'incluir_eja' => ['nullable', 'boolean'],
        ]);

        $ano        = AnoLetivo::findOrFail($data['anl_id']);
        $dataRef    = ! empty($data['data_ref']) ? Carbon::parse($data['data_ref']) : now();
        $incluirEja = $request->boolean('incluir_eja');

        // "15 anos completos em dataRef" ⇔ nasceu até dataRef menos 15 anos.
        $nascimentoLimite = $dataRef->copy()->subYears(self::IDADE_MINIMA)->toDateString();

        $escolas = UserAccess::escolasVisiveis(auth()->user());
        if (! empty($data['esc_id'])) {
            $escolas = $escolas->where('esc_id', (int) $data['esc_id'])->values();
        }
        $escolasIds = $escolas->pluck('esc_id')->map(fn ($id) => (int) $id)->all();

        $registros = $escolasIds ? $this->consultar($escolasIds, (int) $data['anl_id'], $nascimentoLimite, $data) : collect();

        $todos = $registros
            // Um aluno pode ter mais de um vínculo no ano: fica a matrícula ATIVA
            // mais recente, que é a que representa a situação atual dele.
            ->sortByDesc(fn ($r) => [$r->tma_situacao === Matricula::SITUACAO_ATIVA ? 1 : 0, (int) $r->tma_id])
            ->unique('aln_id')
            ->map(function ($r) use ($dataRef) {
                $nasc = $r->aln_dt_nascimento ? Carbon::parse($r->aln_dt_nascimento) : null;
                $seg  = (string) ($r->segmento ?? '');

                return [
                    'aln_id'     => (int) $r->aln_id,
                    'esc_id'     => (int) $r->esc_id,
                    'esc_nome'   => $r->esc_nome,
                    'esc_codigo' => $r->esc_cd_escola ?: $r->esc_cd_inep,
                    'tur_id'     => (int) $r->tur_id,
                    'turma'      => $r->tur_nome,
                    'serie'      => $r->serie,
                    'segmento'   => $r->segmento,
                    'matricula'  => $r->aln_nr_matricula,
                    'nome'       => $r->aln_nome,
                    'dt_nascimento' => $nasc?->format('d/m/Y'),
                    // diffInYears devolve float nesta versão do Carbon; o cast trunca
                    // para os anos COMPLETOS, que é a idade que o relatório pede.
                    'idade'      => $nasc ? (int) $nasc->diffInYears($dataRef) : null,
                    'situacao_matricula' => $r->tma_situacao,
                    'situacao_aluno'     => $r->aln_fl_ativo ? 'Ativo' : 'Inativo',
                    // Segmento com "EJA" no nome ⇒ já está na modalidade.
                    'ja_eja' => str_contains(mb_strtoupper(Str::ascii($seg)), 'EJA'),
                ];
            })
            ->values();

        $linhas = ($incluirEja ? $todos : $todos->filter(fn ($l) => ! $l['ja_eja']))
            ->sortBy([
                fn ($a, $b) => $this->cmp($a['esc_nome'], $b['esc_nome']),
                fn ($a, $b) => $this->cmp($a['turma'], $b['turma']),
                fn ($a, $b) => $this->cmp($a['nome'], $b['nome']),
            ])
            ->values();

        $parametros = ParametroEntidade::first();

        return [
            'parametros' => $parametros ? $parametros->cabecalhoRelatorio() : null,
            'filtros' => [
                'anl_id'       => (int) $ano->anl_id,
                'anl_ano'      => (int) $ano->anl_ano,
                'esc_id'       => ! empty($data['esc_id']) ? (int) $data['esc_id'] : null,
                'esc_nome'     => ! empty($data['esc_id']) ? ($escolas->first()->esc_nome ?? null) : null,
                'tur_id'       => ! empty($data['tur_id']) ? (int) $data['tur_id'] : null,
                'tur_nome'     => ! empty($data['tur_id'])
                    ? DB::table('edu_turma')->where('tur_id', $data['tur_id'])->value('tur_nome')
                    : null,
                'data_ref'     => $dataRef->format('d/m/Y'),
                'idade_minima' => self::IDADE_MINIMA,
                'incluir_eja'  => $incluirEja,
            ],
            'linhas' => $linhas,
            'resumo' => [
                'total'        => $linhas->count(),
                'escolas'      => $linhas->pluck('esc_id')->unique()->count(),
                'turmas'       => $linhas->pluck('tur_id')->unique()->count(),
                'ativos'       => $linhas->where('situacao_matricula', Matricula::SITUACAO_ATIVA)->count(),
                'ja_eja'       => $todos->where('ja_eja', true)->count(),
                'ocultados'    => $incluirEja ? 0 : $todos->where('ja_eja', true)->count(),
                'idade_maxima' => $linhas->max('idade'),
            ],
        ];
    }

    /** Vínculos do ano nas escolas dadas, já filtrados pela idade mínima. */
    private function consultar(array $escolasIds, int $anlId, string $nascimentoLimite, array $filtros): Collection
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
            ->where('t.tur_anl_id', $anlId)
            ->whereIn('t.tur_esc_id', $escolasIds)
            ->whereNotNull('a.aln_dt_nascimento')
            ->whereDate('a.aln_dt_nascimento', '<=', $nascimentoLimite);

        if (! empty($filtros['tur_id'])) {
            $q->where('t.tur_id', (int) $filtros['tur_id']);
        }

        return collect($q->get([
            'tma.tma_id', 'tma.tma_situacao',
            'a.aln_id', 'a.aln_nome', 'a.aln_nr_matricula', 'a.aln_dt_nascimento', 'a.aln_fl_ativo',
            't.tur_id', 't.tur_nome',
            'e.esc_id', 'e.esc_nome', 'e.esc_cd_escola', 'e.esc_cd_inep',
            's.ser_nome as serie', 'seg.seg_nome_reduzido as segmento',
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
