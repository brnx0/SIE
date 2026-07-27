<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\EstoqueSaldo;
use App\Models\Alimentacao\Item;
use App\Models\Alimentacao\Movimento;
use App\Models\Escola\Escola;
use App\Models\Parametro\ParametroEntidade;
use App\Services\Alimentacao\MovimentaEstoque;
use App\Support\UserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;

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

        // Une todos os movimentos deste envio sob um mesmo id — é o que permite
        // reemitir a guia de remessa exatamente com os itens desta transferência.
        $transferenciaId = (string) Str::uuid();

        // Tudo numa transação: transferência que grava metade dos itens deixaria
        // o central sem a mercadoria e a escola sem receber.
        DB::transaction(function () use ($dados, $estoque, $request, $transferenciaId) {
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
                        'mov_usr_id'           => $request->user()?->id,
                        'mov_obs'              => $dados['obs'] ?? null,
                        'mov_origem'           => 'transferencia',
                        'mov_transferencia_id' => $transferenciaId,
                    ],
                );
            }
        });

        $escola = Escola::find($dados['esc_destino_id']);

        return to_route('alimentacao.estoque.movimentos')
            ->with('success', 'Transferência registrada para '.($escola?->esc_nome ?? 'a escola')
                .'. A guia de remessa (PDF) está disponível na listagem.');
    }

    /**
     * Guia de remessa em PDF: os itens que este envio entregou à escola.
     *
     * Lê pela perna de ENTRADA da transferência (destino = escola), que é quem
     * carrega a escola e a quantidade efetivamente recebida em cada lote.
     */
    public function pdf(Request $request, string $transferencia): HttpResponse
    {
        $movimentos = Movimento::query()
            ->where('mov_transferencia_id', $transferencia)
            ->where('mov_tipo', Movimento::TIPO_ENTRADA)
            ->whereNotNull('mov_esc_id')
            ->with([
                'escola:esc_id,esc_nome',
                'item:itm_id,itm_nome,itm_cd_item,itm_umd_id',
                'item.unidadeMedida:umd_id,umd_sigla',
                'lote:lot_id,lot_cd_lote,lot_dt_validade,lot_mrc_id',
                'lote.marca:mrc_id,mrc_nome',
                'usuario:id,name',
            ])
            ->orderBy('mov_id')
            ->get();

        abort_if($movimentos->isEmpty(), 404, 'Transferência não encontrada.');

        // Perfil com lotação só emite a guia da própria escola.
        $escolasIds = UserAccess::escolasIds($request->user());
        $escId      = (int) $movimentos->first()->mov_esc_id;
        abort_if($escolasIds !== null && ! in_array($escId, $escolasIds, true), 403);

        $parametros = ParametroEntidade::first();

        $html = view('exports.alimentacao_transferencia_pdf', [
            'nomeEntidade' => $parametros?->par_nome_entidade,
            'endereco'     => $parametros?->par_endereco,
            'escolaNome'   => $movimentos->first()->escola?->esc_nome ?? '—',
            'data'         => $movimentos->first()->mov_dt?->format('d/m/Y'),
            'usuario'      => $movimentos->first()->usuario?->name,
            'obs'          => $movimentos->first()->mov_obs,
            'protocolo'    => strtoupper(substr($transferencia, 0, 8)),
            'movimentos'   => $movimentos,
            'valorTotal'   => (float) $movimentos->sum(fn (Movimento $m) => (float) $m->mov_vl_total),
        ])->render();

        $mpdf = new Mpdf(['format' => 'A4', 'margin_top' => 12, 'margin_bottom' => 12, 'margin_left' => 12, 'margin_right' => 12, 'tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML($html);

        $filename = 'guia_remessa_'.substr($transferencia, 0, 8).'.pdf';

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
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
