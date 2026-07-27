<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
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
use Inertia\Inertia;
use Inertia\Response;

/**
 * Formação de Classes — quadro de turmas regulares com capacidade, expansão,
 * alunos da rede × novos, vagas, PCD, faixa etária e professores alocados.
 *
 * Escopo: a escola é OPCIONAL — em branco varre a rede inteira. O filtro de
 * situação aceita todas as turmas (padrão), só abertas ou só encerradas.
 *
 * Continua restrito à modalidade REGULAR: AEE e Atividade têm relatórios
 * próprios (formacao-classes-aee, relacao-turmas-aee/atividade).
 */
class FormacaoClassesController extends Controller
{
    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/FormacaoClasses/Form', [
            'anosLetivos' => AnoLetivo::orderByDesc('anl_ano')->get(['anl_id', 'anl_ano', 'anl_fl_em_exercicio']),
            'escolas'     => UserAccess::escolasVisiveis($user),
            'userEscola'  => UserAccess::escolaDefault($user),
            'isAdmin'     => $user->isAdmin(),
            'situacoes'   => Turma::SITUACOES,
        ]);
    }

    public function gerar(Request $request): Response
    {
        [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
        ] = $this->montar($request);

        return Inertia::render('relatorios/FormacaoClasses/Resultado', [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'total'      => $linhas->count(),
        ]);
    }

    public function exportarXlsx(Request $request): \Illuminate\Http\Response
    {
        ['linhas' => $linhas] = $this->montar($request);

        $headers = [
            'Escola', 'Código da Turma', 'Nome da Turma', 'Segmento', 'Série / Ano Escolar', 'Turno',
            'Status da Turma', 'Capacidade', 'Expansão', 'Matriculados',
            'Alunos da Rede', 'Alunos Novos', 'Vagas', 'PCD', 'Faixa Etária',
            'Professor(es) Alocado(s)', 'Professor(es) de Apoio',
        ];

        $rows = $linhas->map(fn ($l) => [
            $l['esc_nome'], $l['tur_codigo'] ?? '', $l['turma'], $l['segmento'] ?? '', $l['serie'] ?? '', $l['turno'] ?? '',
            $l['situacao'] ?? '', $l['capacidade'], $l['expansao'], $l['matriculados'],
            $l['rede'], $l['novos'], $l['vagas'], $l['pcd'], $l['idade'],
            $l['professores_texto'], $l['apoio_texto'],
        ])->all();

        return XlsxExport::download('formacao_classes_' . now()->format('Ymd_His') . '.xlsx', $headers, $rows);
    }

    /**
     * @return array{parametros: array|null, filtros: array, linhas: Collection}
     */
    private function montar(Request $request): array
    {
        $data = $request->validate([
            'anl_id'   => ['required', 'integer', 'exists:cfg_ano_letivo,anl_id'],
            'esc_id'   => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'situacao' => ['nullable', 'in:todas,ABERTA,ENCERRADA'],
        ]);

        $situacao  = $data['situacao'] ?? 'todas';
        $anoLetivo = AnoLetivo::findOrFail($data['anl_id']);
        $escola    = ! empty($data['esc_id']) ? Escola::findOrFail($data['esc_id']) : null;

        $escolas = UserAccess::escolasVisiveis(auth()->user());
        if ($escola) {
            $escolas = $escolas->where('esc_id', $escola->esc_id)->values();
        }
        $escolasIds = $escolas->pluck('esc_id')->map(fn ($id) => (int) $id)->all();

        // Sem eager-load de matriculas: em modo rede seriam ~25 mil matrículas
        // × aluno × saúde, e o custo de hidratar isso em model estoura a memória.
        // Os agregados por turma saem de uma query com linhas cruas (ver agregados()).
        $query = Turma::query()
            ->with([
                'escola:esc_id,esc_nome',
                'serie:ser_id,ser_nome,ser_idade',
                'segmento:seg_id,seg_nome_reduzido,seg_ordem',
            ])
            ->whereIn('tur_esc_id', $escolasIds ?: [-1])
            ->where('tur_anl_id', $data['anl_id'])
            ->where('tur_modalidade', Turma::MODALIDADE_REGULAR);

        if ($situacao !== 'todas') {
            $query->where('tur_situacao', $situacao);
        }

        $turmas = $query->get();

        $dtCorte = $anoLetivo->anl_dt_corte;

        // Total histórico de matrículas por aluno, em uma varredura só. Sem
        // whereIn: em modo rede a lista de IDs teria dezenas de milhares de itens.
        $totalPorAluno = DB::table('edu_turma_aluno')
            ->whereNull('tma_deleted_at')
            ->groupBy('tma_aln_id')
            ->selectRaw('tma_aln_id, count(*) as total')
            ->pluck('total', 'tma_aln_id');

        $turIds      = $turmas->pluck('tur_id')->map(fn ($id) => (int) $id)->all();
        $professores = $this->professoresPorTurma($turIds);
        $apoio       = $this->apoioPorTurma($turIds);
        $agregados   = $this->agregados($turIds, $totalPorAluno, $dtCorte);

        // Em modo rede o agrupamento é por escola + segmento; com escola fixa,
        // só por segmento — mantém o layout original de quem já usava o relatório.
        $porEscola = $escola === null;

        $turmas = $turmas
            ->sortBy(fn ($t) => [
                $porEscola ? ($t->escola?->esc_nome ?? '') : '',
                $t->segmento?->seg_ordem ?? 9999,
                $t->segmento?->seg_nome_reduzido ?? '',
                $t->tur_turno,
                $t->serie?->ser_nome ?? '',
                $t->tur_nome,
            ])
            ->values();

        $linhas = $turmas->map(function (Turma $t, $i) use ($agregados, $professores, $apoio, $porEscola) {
            $cap = (int) ($t->tur_capacidade ?? 0);
            $exp = (int) ($t->tur_qt_expansao ?? 0);

            $tid = (int) $t->tur_id;
            $ag  = $agregados[$tid] ?? ['novos' => 0, 'rede' => 0, 'pcd' => 0, 'idade_min' => null, 'idade_max' => null];

            $novos = $ag['novos']; $rede = $ag['rede']; $pcd = $ag['pcd'];
            $idadeMin = $ag['idade_min']; $idadeMax = $ag['idade_max'];

            $idadeTexto = '—';
            if ($idadeMin !== null && $idadeMax !== null) {
                $idadeTexto = $idadeMin === $idadeMax ? (string) $idadeMin : "{$idadeMin} a {$idadeMax}";
            }

            $profs    = $professores[$tid] ?? [];
            $aps      = $apoio[$tid] ?? [];
            $segNome  = $t->segmento?->seg_nome_reduzido ?? '—';
            $escNome  = $t->escola?->esc_nome ?? '—';

            return [
                'num'         => $i + 1,
                // Chave de agrupamento montada aqui: a tela apenas agrupa por ela.
                'grupo'       => $porEscola ? "{$escNome} — {$segNome}" : $segNome,
                'esc_id'      => (int) $t->tur_esc_id,
                'esc_nome'    => $escNome,
                'tur_id'      => $tid,
                'tur_codigo'  => $t->tur_cd_inep,
                'segmento'    => $t->segmento?->seg_nome_reduzido,
                'turno'       => $t->tur_turno,
                'serie'       => $t->serie?->ser_nome,
                'turma'       => $t->tur_nome,
                'situacao'    => $t->tur_situacao,
                'fluxo'       => '-',
                'pcd'         => $pcd,
                'idade'       => $idadeTexto,
                'sala'        => '-',
                'capacidade'  => $cap,
                'expansao'    => $exp,
                'rede'        => $rede,
                'novos'       => $novos,
                'matriculados' => $rede + $novos,
                'vagas'       => max(0, $cap + $exp - $rede - $novos),
                'professores' => $profs,
                'professores_texto' => implode('; ', array_map(
                    fn ($p) => $p['disciplina'] ? "{$p['nome']} ({$p['disciplina']})" : $p['nome'],
                    $profs,
                )),
                'apoio_texto' => implode('; ', $aps),
            ];
        });

        $parametros = ParametroEntidade::with('municipio:mun_id,mun_nome,mun_uf')->first();

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
                'anl_id'    => (int) $anoLetivo->anl_id,
                'anl_ano'   => $anoLetivo->anl_ano,
                'esc_id'    => $escola?->esc_id,
                'esc_nome'  => $escola?->esc_nome ?? 'Todas as escolas da rede',
                'situacao'  => $situacao,
                'rede'      => $porEscola,
                'escolas'   => $linhas->pluck('esc_id')->unique()->count(),
            ],
            'linhas' => $linhas,
        ];
    }

    /**
     * Agregados de matrícula por turma: alunos novos × da rede, PCD e faixa etária.
     *
     * Uma query só, devolvendo linhas cruas (stdClass) em vez de models — é o que
     * permite rodar em modo rede sem estourar memória.
     *
     * @param  array<int>  $turIds
     * @return array<int, array{novos:int, rede:int, pcd:int, idade_min:?int, idade_max:?int}>
     */
    private function agregados(array $turIds, $totalPorAluno, $dtCorte): array
    {
        if (! $turIds) {
            return [];
        }

        $rows = DB::table('edu_turma_aluno as tma')
            ->join('edu_aluno as a', 'a.aln_id', '=', 'tma.tma_aln_id')
            // edu_aluno_saude é 1-1 e não tem soft delete.
            ->leftJoin('edu_aluno_saude as s', 's.als_aln_id', '=', 'a.aln_id')
            ->whereIn('tma.tma_tur_id', $turIds)
            ->where('tma.tma_situacao', Matricula::SITUACAO_ATIVA)
            ->whereNull('tma.tma_deleted_at')
            ->whereNull('a.aln_deleted_at')
            ->get(['tma.tma_tur_id', 'tma.tma_aln_id', 'a.aln_dt_nascimento', 's.als_fl_pcd']);

        $out = [];
        foreach ($rows as $r) {
            $tid = (int) $r->tma_tur_id;
            $out[$tid] ??= ['novos' => 0, 'rede' => 0, 'pcd' => 0, 'idade_min' => null, 'idade_max' => null];

            // "Novo" = aluno sem histórico anterior de matrícula (total <= 1).
            if ((int) ($totalPorAluno[$r->tma_aln_id] ?? 1) <= 1) {
                $out[$tid]['novos']++;
            } else {
                $out[$tid]['rede']++;
            }

            if ($r->als_fl_pcd) {
                $out[$tid]['pcd']++;
            }

            if ($r->aln_dt_nascimento && $dtCorte) {
                $idade = $this->calcularIdade($r->aln_dt_nascimento, $dtCorte);
                if ($out[$tid]['idade_min'] === null || $idade < $out[$tid]['idade_min']) {
                    $out[$tid]['idade_min'] = $idade;
                }
                if ($out[$tid]['idade_max'] === null || $idade > $out[$tid]['idade_max']) {
                    $out[$tid]['idade_max'] = $idade;
                }
            }
        }

        return $out;
    }

    /**
     * Regentes por turma. tup_dis_id nulo = vínculo sem disciplina (AEE).
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

    protected function calcularIdade($nascimento, $referencia): int
    {
        $nasc = $nascimento instanceof Carbon ? $nascimento : Carbon::parse($nascimento);
        $ref  = $referencia instanceof Carbon ? $referencia : Carbon::parse($referencia);
        $idade = $ref->year - $nasc->year;
        if ($ref->format('m-d') < $nasc->format('m-d')) $idade--;
        return $idade;
    }
}
