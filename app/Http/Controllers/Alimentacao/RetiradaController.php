<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\EstoqueSaldo;
use App\Models\Alimentacao\Item;
use App\Models\Alimentacao\Movimento;
use App\Models\Alimentacao\TipoRetirada;
use App\Models\Escola\Escola;
use App\Models\Funcionario\Funcionario;
use App\Services\Alimentacao\MovimentaEstoque;
use App\Support\UserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Retirada de item do estoque da unidade.
 *
 * Diferente da transferência: aqui o item sai e acabou — foi usado na cozinha,
 * descartado, venceu. O `tipo de retirada` diz qual foi o caso, e o responsável
 * fica registrado.
 */
class RetiradaController extends Controller
{
    public function create(Request $request): Response
    {
        // Perfil global pode trocar de estoque na tela; a lista de itens tem de
        // acompanhar, senão o select muda e os itens continuam sendo do central.
        $escId = $request->has('esc_id')
            ? $this->resolverEstoque($request, $request->input('esc_id') === '' ? null : (int) $request->input('esc_id'))
            : $this->estoqueDoUsuario($request);

        return Inertia::render('alimentacao/retiradas/Create', [
            // O EscolaCombobox busca em /api/escolas (já escopado por lotação);
            // aqui vai só a escola em uso, para ele mostrar o nome.
            'escolaAtual'    => $this->escolaResumo($escId),
            'podeVerCentral' => UserAccess::escolasIds($request->user()) === null,
            'tiposRetirada'  => TipoRetirada::where('tpr_fl_ativo', true)->orderBy('tpr_nome')->get(['tpr_id', 'tpr_nome']),
            'itens'          => $this->itensComSaldo($escId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate(
            [
                'esc_id'  => ['nullable', 'integer', 'exists:edu_escola,esc_id'],
                'tpr_id'  => ['required', 'integer', 'exists:ali_tipo_retirada,tpr_id'],
                'fun_id'  => ['nullable', 'integer', 'exists:edu_funcionario,fun_id'],
                'responsavel' => ['nullable', 'string', 'max:120'],
                'dt'      => ['required', 'date'],
                'obs'     => ['nullable', 'string'],
                'itens'          => ['required', 'array', 'min:1'],
                'itens.*.itm_id' => ['required', 'integer', 'exists:ali_item,itm_id'],
                'itens.*.qtd'    => ['required', 'numeric', 'gt:0'],
                'itens.*.umd_id' => ['required', 'integer', 'exists:ali_unidade_medida,umd_id'],
            ],
            [
                'itens.required' => 'Informe ao menos um item para retirar.',
                'itens.*.qtd.gt' => 'A quantidade deve ser maior que zero.',
            ],
            [
                'esc_id' => 'estoque',
                'tpr_id' => 'tipo de retirada',
                'fun_id' => 'responsável',
                'dt'     => 'data',
            ],
        );

        $escId = $this->resolverEstoque($request, $dados['esc_id'] ?? null);

        // Responsável cadastrado (fun_id) e nome digitado são exclusivos: escolhido
        // um funcionário, ignora-se o texto livre, para não guardar as duas versões.
        $responsavel = isset($dados['fun_id'])
            ? null
            : (trim((string) ($dados['responsavel'] ?? '')) ?: null);

        $estoque = app(MovimentaEstoque::class);

        // Transação única: retirada que grava parte dos itens deixaria o saldo
        // refletindo uma operação que não aconteceu inteira.
        DB::transaction(function () use ($dados, $escId, $estoque, $request, $responsavel) {
            foreach ($dados['itens'] as $linha) {
                $item = Item::findOrFail($linha['itm_id']);

                // FEFO: quem retira escolhe item e quantidade, não lote.
                $estoque->baixarFefo(
                    $escId,
                    $item,
                    (float) $linha['qtd'],
                    (int) $linha['umd_id'],
                    Movimento::TIPO_RETIRADA,
                    $dados['dt'],
                    [
                        'mov_tpr_id'      => (int) $dados['tpr_id'],
                        'mov_fun_id'      => $dados['fun_id'] ?? null,
                        'mov_responsavel' => $responsavel,
                        'mov_usr_id'      => $request->user()?->id,
                        'mov_obs'         => $dados['obs'] ?? null,
                        'mov_origem'      => 'retirada',
                    ],
                );
            }
        });

        return to_route('alimentacao.estoque.movimentos')->with('success', 'Retirada registrada com sucesso.');
    }

    // ------------------------------------------------------------------

    /** Estoque padrão da tela: a escola do usuário, ou o central para perfil global. */
    private function estoqueDoUsuario(Request $request): ?int
    {
        $escolasIds = UserAccess::escolasIds($request->user());

        return $escolasIds === null ? null : ($escolasIds[0] ?? -1);
    }

    /**
     * Perfil com lotação retira só do estoque da própria escola — mandar outro
     * `esc_id` no payload não pode furar isso.
     */
    private function resolverEstoque(Request $request, ?int $pedido): ?int
    {
        $escolasIds = UserAccess::escolasIds($request->user());

        if ($escolasIds === null) {
            return $pedido; // global: null = central
        }

        if ($pedido !== null && ! in_array($pedido, $escolasIds, true)) {
            throw ValidationException::withMessages([
                'esc_id' => 'Você só pode retirar itens do estoque da sua escola.',
            ]);
        }

        return $pedido ?? ($escolasIds[0] ?? -1);
    }

    /** Escola resumida para o EscolaCombobox exibir o nome do que já está escolhido. */
    private function escolaResumo(?int $escId): ?array
    {
        if (! $escId) {
            return null;
        }

        $escola = Escola::find($escId, ['esc_id', 'esc_nome']);

        return $escola ? ['esc_id' => $escola->esc_id, 'esc_nome' => $escola->esc_nome] : null;
    }

    private function itensComSaldo(?int $escId)
    {
        return EstoqueSaldo::query()
            ->doEstoque($escId)
            ->comSaldo()
            ->select('sld_itm_id', DB::raw('SUM(sld_qtd_base) as qtd'))
            ->groupBy('sld_itm_id')
            ->get()
            ->map(function ($linha) {
                $item = Item::with('unidadeMedida:umd_id,umd_sigla')->find($linha->sld_itm_id);

                return [
                    'itm_id'     => $item?->itm_id,
                    'itm_nome'   => $item?->itm_nome,
                    'itm_umd_id' => $item?->itm_umd_id,
                    'umd_sigla'  => $item?->unidadeMedida?->umd_sigla,
                    'saldo'      => (float) $linha->qtd,
                ];
            })
            ->filter(fn ($i) => $i['itm_id'] !== null)
            ->sortBy('itm_nome')
            ->values();
    }
}
