<?php

namespace App\Http\Controllers\Relatorio;

use App\Http\Controllers\Controller;
use App\Models\Escola\Escola;
use App\Models\Funcionario\Cargo;
use App\Models\Funcionario\Funcionario;
use App\Models\Parametro\ParametroEntidade;
use App\Support\UserAccess;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Relação de Funcionários — uma linha por funcionário (cargos/unidades de todas
 * as lotações ativas agregados). Filtra por unidade (escola), situação e cargo.
 */
class RelacaoFuncionariosController extends Controller
{
    /** fes_nivel → rótulo. */
    private const NIVEL_LABEL = [
        1 => 'Ensino Fundamental Incompleto',
        2 => 'Ensino Fundamental Completo',
        3 => 'Ensino Médio Incompleto',
        4 => 'Ensino Médio Completo',
        5 => 'Ensino Superior Incompleto',
        6 => 'Ensino Superior Completo',
        7 => 'Pós-graduação',
        8 => 'Mestrado / Doutorado',
        9 => 'Magistério',
    ];

    /**
     * Ordem acadêmica para escolher a MAIOR escolaridade — o código não é
     * ordenado (9 = Magistério é nível médio, não fica acima de Pós/Mestrado).
     */
    private const NIVEL_RANK = [
        1 => 1, 2 => 2, 3 => 3, 9 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 8, 8 => 9,
    ];

    public function form(): Response
    {
        $user = auth()->user();

        return Inertia::render('relatorios/RelacaoFuncionarios/Form', [
            'escolas'    => UserAccess::escolasVisiveis($user),
            'userEscola' => UserAccess::escolaDefault($user),
            'isAdmin'    => $user->isAdmin(),
            'cargos'     => Cargo::orderBy('crg_nome')->get(['crg_id', 'crg_nome']),
        ]);
    }

    public function gerar(Request $request): Response
    {
        ['parametros' => $parametros, 'filtros' => $filtros, 'linhas' => $linhas] = $this->montar($request);

        return Inertia::render('relatorios/RelacaoFuncionarios/Resultado', [
            'parametros' => $parametros,
            'filtros'    => $filtros,
            'linhas'     => $linhas,
            'total'      => $linhas->count(),
        ]);
    }

    public function exportarXlsx(Request $request): \Illuminate\Http\Response
    {
        ['linhas' => $linhas] = $this->montar($request);

        $headers = ['Nome', 'Matrícula', 'Cargo/Função', 'Escolaridade', 'Unidade de Lotação', 'Situação'];

        $rows = $linhas->map(fn ($l) => [
            $l['nome'],
            $l['matricula'],
            $l['cargo'],
            $l['escolaridade'],
            $l['unidade'],
            $l['situacao'],
        ])->all();

        return XlsxExport::download('relacao_funcionarios_' . now()->format('Ymd_His') . '.xlsx', $headers, $rows);
    }

    /**
     * @return array{parametros: array|null, filtros: array, linhas: \Illuminate\Support\Collection}
     */
    private function montar(Request $request): array
    {
        $data = $request->validate([
            'esc_id'   => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
            'crg_id'   => ['nullable', 'integer', 'exists:edu_cargo,crg_id'],
            'situacao' => ['nullable', 'in:ativo,inativo'],
        ]);

        $escId    = $data['esc_id'] ?? null;
        $crgId    = $data['crg_id'] ?? null;
        $situacao = $data['situacao'] ?? null;

        $query = Funcionario::query()
            ->with([
                'escolaridades:fes_id,fes_fun_id,fes_nivel',
                'admissoes:adm_id,adm_fun_id,adm_matricula',
                // Só lotações ATIVAS compõem cargo/unidade.
                'admissoes.lotacoes' => fn ($q) => $q->where('lot_fl_ativo', true),
                'admissoes.lotacoes.escola:esc_id,esc_nome',
                'admissoes.lotacoes.cargo:crg_id,crg_nome',
            ])
            ->when($situacao === 'ativo', fn ($q) => $q->where('fun_fl_ativo', true))
            ->when($situacao === 'inativo', fn ($q) => $q->where('fun_fl_ativo', false))
            ->when($escId, fn ($q) => $q->whereHas('admissoes.lotacoes', fn ($l) => $l
                ->where('lot_fl_ativo', true)->where('lot_esc_id', $escId)))
            ->when($crgId, fn ($q) => $q->whereHas('admissoes.lotacoes', fn ($l) => $l
                ->where('lot_fl_ativo', true)->where('lot_crg_id', $crgId)))
            ->orderBy('fun_nome');

        // Visibilidade: perfis com lotação só veem funcionários da sua escola.
        $query = UserAccess::scopeFuncionariosVisiveis($query, $request->user());

        $funcionarios = $query->get(['fun_id', 'fun_nome', 'fun_fl_ativo']);

        $linhas = $funcionarios->map(function (Funcionario $f) {
            $matriculas = $f->admissoes->pluck('adm_matricula')->filter()->unique()->values();
            $lotacoes   = $f->admissoes->flatMap(fn ($a) => $a->lotacoes);
            $cargos     = $lotacoes->map(fn ($l) => $l->cargo?->crg_nome)->filter()->unique()->values();
            $unidades   = $lotacoes->map(fn ($l) => $l->escola?->esc_nome)->filter()->unique()->values();

            $maior = $f->escolaridades
                ->sortByDesc(fn ($e) => self::NIVEL_RANK[$e->fes_nivel] ?? 0)
                ->first();

            return [
                'nome'         => $f->fun_nome,
                'matricula'    => $matriculas->implode(', ') ?: null,
                'cargo'        => $cargos->implode(', ') ?: null,
                'escolaridade' => $maior ? (self::NIVEL_LABEL[$maior->fes_nivel] ?? null) : null,
                'unidade'      => $unidades->implode(', ') ?: null,
                'situacao'     => $f->fun_fl_ativo ? 'Ativo' : 'Inativo',
                'ativo'        => (bool) $f->fun_fl_ativo,
            ];
        });

        $parametros = ParametroEntidade::first();

        return [
            'parametros' => $parametros?->cabecalhoRelatorio(),
            'filtros'    => [
                'esc_id'   => $escId,
                'esc_nome' => $escId ? optional(Escola::find($escId))->esc_nome : null,
                'crg_id'   => $crgId,
                'crg_nome' => $crgId ? optional(Cargo::find($crgId))->crg_nome : null,
                'situacao' => $situacao,
            ],
            'linhas' => $linhas,
        ];
    }
}
