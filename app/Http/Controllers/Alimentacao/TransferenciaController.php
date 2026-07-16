<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\EstoqueSaldo;
use App\Models\Alimentacao\Item;
use App\Models\Escola\Escola;
use App\Services\Alimentacao\MovimentaEstoque;
use App\Support\UserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Saída do estoque central para a escola.
 *
 * Gera par de movimentos (SAIDA no central + ENTRADA na escola) por lote, via
 * FEFO — quem transfere escolhe item e quantidade, não lote.
 */
class TransferenciaController extends Controller
{
    public function create(Request $request): Response
    {
        // A escola vem do EscolaCombobox (busca em /api/escolas, já escopado por
        // lotação) — não há lista para mandar.
        return Inertia::render('alimentacao/transferencias/Create', [
            'itens' => $this->itensComSaldoNoCentral(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Só quem opera o central transfere. Perfil preso a uma escola não tem
        // como tirar do central — só recebe.
        $this->assertOperaCentral($request);

        $dados = $request->validate(
            [
                'esc_destino_id' => ['required', 'integer', 'exists:edu_escola,esc_id'],
                'dt'             => ['required', 'date'],
                'obs'            => ['nullable', 'string'],
                'itens'                 => ['required', 'array', 'min:1'],
                'itens.*.itm_id'        => ['required', 'integer', 'exists:ali_item,itm_id'],
                'itens.*.qtd'           => ['required', 'numeric', 'gt:0'],
                'itens.*.umd_id'        => ['required', 'integer', 'exists:ali_unidade_medida,umd_id'],
            ],
            [
                'itens.required'    => 'Informe ao menos um item para transferir.',
                'itens.*.qtd.gt'    => 'A quantidade deve ser maior que zero.',
            ],
            [
                'esc_destino_id' => 'escola de destino',
                'dt'             => 'data',
            ],
        );

        $estoque = app(MovimentaEstoque::class);

        // Tudo numa transação: transferência que grava metade dos itens deixaria
        // o central sem a mercadoria e a escola sem receber.
        DB::transaction(function () use ($dados, $estoque, $request) {
            foreach ($dados['itens'] as $linha) {
                $item = Item::findOrFail($linha['itm_id']);

                $estoque->transferir(
                    null, // origem: central
                    (int) $dados['esc_destino_id'],
                    $item,
                    (float) $linha['qtd'],
                    (int) $linha['umd_id'],
                    $dados['dt'],
                    [
                        'mov_usr_id' => $request->user()?->id,
                        'mov_obs'    => $dados['obs'] ?? null,
                        'mov_origem' => 'transferencia',
                    ],
                );
            }
        });

        $escola = Escola::find($dados['esc_destino_id']);

        return to_route('alimentacao.estoque.movimentos')
            ->with('success', 'Transferência registrada para '.($escola?->esc_nome ?? 'a escola').'.');
    }

    /** Itens com saldo no central, com o disponível — é o que dá para transferir. */
    private function itensComSaldoNoCentral()
    {
        return EstoqueSaldo::query()
            ->doEstoque(null) // null = central
            ->comSaldo()
            ->select('sld_itm_id', DB::raw('SUM(sld_qtd_base) as qtd'))
            ->groupBy('sld_itm_id')
            ->get()
            ->map(function ($linha) {
                $item = Item::with('unidadeMedida:umd_id,umd_sigla')->find($linha->sld_itm_id);

                return [
                    'itm_id'    => $item?->itm_id,
                    'itm_nome'  => $item?->itm_nome,
                    'itm_umd_id' => $item?->itm_umd_id,
                    'umd_sigla' => $item?->unidadeMedida?->umd_sigla,
                    'saldo'     => (float) $linha->qtd,
                ];
            })
            ->filter(fn ($i) => $i['itm_id'] !== null)
            ->sortBy('itm_nome')
            ->values();
    }

    private function assertOperaCentral(Request $request): void
    {
        if (UserAccess::escolasIds($request->user()) === null) {
            return;
        }

        throw ValidationException::withMessages([
            'esc_destino_id' => 'Seu perfil está vinculado a uma escola e não pode transferir do estoque central.',
        ]);
    }
}
