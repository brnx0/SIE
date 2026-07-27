<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Funcionario\Cargo;
use App\Models\Funcionario\Funcionario;
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
 * Relação de Funcionários — cadastro nominal da rede, em ordem alfabética.
 *
 * Uma linha por VÍNCULO DE LOTAÇÃO vigente (funcionário pode ter mais de um):
 * mostra a unidade e o cargo daquele vínculo. Funcionários sem lotação vigente
 * aparecem uma vez, com unidade/cargo em branco — só quando não há filtro de
 * unidade nem de cargo (senão não fazem parte do recorte).
 *
 * Espelha a família de relatórios "listagem" (ver RelacaoEscolasController):
 * form/gerar/exportarXlsx + montar(); PDF é impressão do navegador na tela.
 */
class RelacaoFuncionariosController extends Controller
{
    /** Rótulos dos níveis de escolaridade — espelha resources/js/lib/funcionarioEnums.ts. */
    private const ESCOLARIDADES = [
        1  => 'Ensino Fundamental Incompleto',
        2  => 'Ensino Fundamental Completo',
        3  => 'Ensino Médio Incompleto',
        4  => 'Ensino Médio Completo',
        5  => 'Ensino Superior Incompleto',
        6  => 'Ensino Superior Completo',
        7  => 'Pós-graduação',
        8  => 'Mestrado / Doutorado',
        9  => 'Magistério',
        10 => 'Ensino Superior Completo',
    ];

    /** Níveis do mais alto ao mais baixo — escolhe a "maior" escolaridade do funcionário. */
    private const ESCOLARIDADE_RANK = [8, 7, 6, 10, 5, 9, 4, 3, 2, 1];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/RelacaoFuncionarios/Form', [
            'escolas'    => UserAccess::escolasVisiveis($user),
            'userEscola' => UserAccess::escolaDefault($user),
            'isAdmin'    => $user->isAdmin(),
            'cargos'     => Cargo::query()
                ->where('crg_fl_ativo', true)
                ->orderBy('crg_nome')
                ->get(['crg_id', 'crg_nome'])
                ->map(fn ($c) => ['crg_id' => (int) $c->crg_id, 'crg_nome' => $c->crg_nome]),
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

        return Inertia::render('relatorios/RelacaoFuncionarios/Resultado', [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'resumo'     => $resumo,
        ]);
    }

    public function exportarXlsx(Request $request): \Illuminate\Http\Response
    {
        ['linhas' => $linhas] = $this->montar($request);

        $headers = ['Nome', 'Matrícula', 'Cargo/Função', 'Escolaridade', 'Unidade de Lotação', 'Vínculo', 'Situação'];

        $rows = $linhas->map(fn ($l) => [
            $l['nome'],
            $l['matricula'] ?? '',
            $l['cargo'] ?? '',
            $l['escolaridade'] ?? '',
            $l['unidade'] ?? '',
            $l['vinculo'] ?? '',
            $l['situacao'],
        ])->all();

        return XlsxExport::download('relacao_funcionarios_' . now()->format('Ymd_His') . '.xlsx', $headers, $rows);
    }

    /**
     * Monta a relação de funcionários (compartilhado entre tela e export).
     *
     * @return array{parametros: array|null, filtros: array, linhas: Collection, resumo: array}
     */
    private function montar(Request $request): array
    {
        $data = $request->validate([
            'esc_id'   => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'crg_id'   => ['nullable', 'integer', 'exists:edu_cargo,crg_id'],
            'situacao' => ['nullable', 'in:ativo,inativo,todos'],
        ]);

        $user       = auth()->user();
        $situacao   = $data['situacao'] ?? 'todos';
        $escId      = ! empty($data['esc_id']) ? (int) $data['esc_id'] : null;
        $crgId      = ! empty($data['crg_id']) ? (int) $data['crg_id'] : null;
        $escolasIds = UserAccess::escolasIds($user); // null = admin (sem recorte)
        $hoje       = now()->toDateString();

        // ── Linhas por vínculo de lotação vigente ──────────────────────────
        $lotacoes = DB::table('edu_funcionario_lotacao as l')
            ->join('edu_funcionario_admissao as a', 'a.adm_id', '=', 'l.lot_adm_id')
            ->join('edu_funcionario as f', 'f.fun_id', '=', 'a.adm_fun_id')
            ->join('edu_cargo as cg', 'cg.crg_id', '=', 'l.lot_crg_id')
            ->join('edu_escola as e', 'e.esc_id', '=', 'l.lot_esc_id')
            ->where('l.lot_fl_ativo', true)
            ->whereNull('f.fun_deleted_at')
            ->whereNull('a.adm_deleted_at')
            ->where(fn ($w) => $w->whereNull('l.lot_dt_fim')->orWhere('l.lot_dt_fim', '>=', $hoje))
            ->when($escolasIds !== null, fn ($q) => $q->whereIn('l.lot_esc_id', $escolasIds ?: [-1]))
            ->when($situacao === 'ativo', fn ($q) => $q->where('f.fun_fl_ativo', true))
            ->when($situacao === 'inativo', fn ($q) => $q->where('f.fun_fl_ativo', false))
            ->when($escId, fn ($q) => $q->where('l.lot_esc_id', $escId))
            ->when($crgId, fn ($q) => $q->where('l.lot_crg_id', $crgId))
            ->get([
                'f.fun_id', 'f.fun_nome', 'f.fun_fl_ativo',
                'a.adm_matricula',
                'cg.crg_nome',
                'e.esc_nome',
                'l.lot_vinculo', 'l.lot_esc_id',
            ])
            // Mesmo funcionário pode ter 2 admissões/lotações no mesmo cargo e escola.
            ->unique(fn ($r) => $r->lot_esc_id . '|' . $r->fun_id . '|' . $r->crg_nome)
            ->values();

        $comLotacao = $lotacoes->pluck('fun_id')->unique()->all();

        // ── Funcionários sem lotação vigente ───────────────────────────────
        // Só entram quando não há recorte por unidade/cargo (o filtro os excluiria).
        $semLotacao = collect();
        if (! $escId && ! $crgId) {
            $q = Funcionario::query()
                ->when($situacao === 'ativo', fn ($qq) => $qq->where('fun_fl_ativo', true))
                ->when($situacao === 'inativo', fn ($qq) => $qq->where('fun_fl_ativo', false))
                ->when($comLotacao, fn ($qq) => $qq->whereNotIn('fun_id', $comLotacao));
            UserAccess::scopeFuncionariosVisiveis($q, $user);

            $semLotacao = $q->get(['fun_id', 'fun_nome', 'fun_fl_ativo']);
        }

        // Escolaridade (maior nível) por funcionário — 1 query para todos.
        $funIds = collect($comLotacao)
            ->merge($semLotacao->pluck('fun_id'))
            ->unique()
            ->all();
        $escolaridadePorFun = $this->escolaridadePorFuncionario($funIds);

        // Matrícula dos sem-lotação: admissão mais recente (se houver).
        $matriculaPorFun = $this->matriculaPorFuncionario($semLotacao->pluck('fun_id')->all());

        $linhasLotacao = $lotacoes->map(fn ($r) => [
            'fun_id'       => (int) $r->fun_id,
            'nome'         => $r->fun_nome,
            'matricula'    => $r->adm_matricula,
            'cargo'        => $r->crg_nome,
            'escolaridade' => $escolaridadePorFun[(int) $r->fun_id] ?? null,
            'unidade'      => $r->esc_nome,
            'vinculo'      => $r->lot_vinculo,
            'ativo'        => (bool) $r->fun_fl_ativo,
            'situacao'     => $r->fun_fl_ativo ? 'Ativo' : 'Inativo',
            'sem_lotacao'  => false,
        ]);

        $linhasSem = $semLotacao->map(fn ($f) => [
            'fun_id'       => (int) $f->fun_id,
            'nome'         => $f->fun_nome,
            'matricula'    => $matriculaPorFun[(int) $f->fun_id] ?? null,
            'cargo'        => null,
            'escolaridade' => $escolaridadePorFun[(int) $f->fun_id] ?? null,
            'unidade'      => null,
            'vinculo'      => null,
            'ativo'        => (bool) $f->fun_fl_ativo,
            'situacao'     => $f->fun_fl_ativo ? 'Ativo' : 'Inativo',
            'sem_lotacao'  => true,
        ]);

        $linhas = $linhasLotacao->concat($linhasSem)
            // Ordem alfabética insensível a acento/caixa (requisito), depois unidade.
            ->sortBy([
                fn ($l) => Str::ascii(mb_strtolower((string) $l['nome'])),
                fn ($l) => Str::ascii(mb_strtolower((string) ($l['unidade'] ?? ''))),
            ])
            ->values();

        $distintos = $linhas->pluck('fun_id')->unique();

        return [
            'parametros' => $this->parametros(),
            'filtros' => [
                'situacao'  => $situacao,
                'esc_id'    => $escId,
                'esc_nome'  => $escId ? DB::table('edu_escola')->where('esc_id', $escId)->value('esc_nome') : null,
                'crg_id'    => $crgId,
                'crg_nome'  => $crgId ? DB::table('edu_cargo')->where('crg_id', $crgId)->value('crg_nome') : null,
            ],
            'linhas' => $linhas,
            'resumo' => [
                'funcionarios' => $distintos->count(),
                'vinculos'     => $linhas->where('sem_lotacao', false)->count(),
                'ativos'       => $linhas->where('ativo', true)->pluck('fun_id')->unique()->count(),
                'inativos'     => $linhas->where('ativo', false)->pluck('fun_id')->unique()->count(),
                'sem_lotacao'  => $linhas->where('sem_lotacao', true)->count(),
            ],
        ];
    }

    /**
     * Maior escolaridade (rótulo) por funcionário.
     *
     * @param  array<int>  $funIds
     * @return array<int, string>
     */
    private function escolaridadePorFuncionario(array $funIds): array
    {
        if (! $funIds) {
            return [];
        }

        $rank = array_flip(self::ESCOLARIDADE_RANK); // nível => posição (0 = mais alto)

        $melhor = []; // fun_id => posição
        $out    = []; // fun_id => rótulo

        $rows = DB::table('edu_funcionario_escolaridade')
            ->whereIn('fes_fun_id', $funIds)
            ->get(['fes_fun_id', 'fes_nivel']);

        foreach ($rows as $r) {
            $fun = (int) $r->fes_fun_id;
            $pos = $rank[(int) $r->fes_nivel] ?? PHP_INT_MAX;
            if (! isset($melhor[$fun]) || $pos < $melhor[$fun]) {
                $melhor[$fun] = $pos;
                $out[$fun] = self::ESCOLARIDADES[(int) $r->fes_nivel] ?? null;
            }
        }

        return array_filter($out);
    }

    /**
     * Matrícula da admissão mais recente por funcionário.
     *
     * @param  array<int>  $funIds
     * @return array<int, string>
     */
    private function matriculaPorFuncionario(array $funIds): array
    {
        if (! $funIds) {
            return [];
        }

        return DB::table('edu_funcionario_admissao')
            ->whereIn('adm_fun_id', $funIds)
            ->whereNull('adm_deleted_at')
            ->orderBy('adm_dt_admissao')
            ->get(['adm_fun_id', 'adm_matricula'])
            // orderBy asc → o último gravado vence (admissão mais recente).
            ->reduce(function (array $acc, $r) {
                if ($r->adm_matricula) {
                    $acc[(int) $r->adm_fun_id] = $r->adm_matricula;
                }

                return $acc;
            }, []);
    }

    /** Cabeçalho institucional (mesmo bloco dos demais relatórios). */
    private function parametros(): ?array
    {
        $p = ParametroEntidade::first();

        return $p ? [
            'nome_entidade'      => $p->par_nome_entidade,
            'msg_cab_secretaria' => $p->par_msg_cab_secretaria,
            'msg_cab_estado'     => $p->par_msg_cab_estado,
            'endereco'           => $p->par_endereco,
            'logomarca_url'      => $p->par_logomarca_url,
            'brasao_url'         => $p->par_brasao_url,
        ] : null;
    }
}
