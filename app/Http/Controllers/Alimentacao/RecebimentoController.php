<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\Lote;
use App\Models\Alimentacao\Marca;
use App\Models\Alimentacao\OrdemFornecimento;
use App\Models\Alimentacao\Recebimento;
use App\Models\Alimentacao\RecebimentoItem;
use App\Services\Alimentacao\AceitaRecebimento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recebimento: confere a entrega do fornecedor contra a ordem e, ao aceitar,
 * dá entrada no estoque central. Cobre também o recebimento de agricultura
 * familiar (PNAE), que é o mesmo fluxo com `rcb_tipo` diferente.
 */
class RecebimentoController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50])
            ? (int) $request->input('per_page')
            : 10;

        $recebimentos = Recebimento::query()
            ->when($request->input('search'), fn ($q, $s) => $q
                ->whereHas('ordem', fn ($o) => $o->whereRaw('ofn_nr_ordem ilike ?', ["%{$s}%"]))
                ->orWhereRaw('rcb_nr_nota ilike ?', ["%{$s}%"]))
            ->when($request->input('situacao'), fn ($q, $s) => $q->where('rcb_situacao', $s))
            ->when($request->input('tipo'), fn ($q, $t) => $q->where('rcb_tipo', $t))
            ->with([
                'ordem:ofn_id,ofn_nr_ordem,ofn_ctr_id',
                'ordem.contrato:ctr_id,ctr_nr_contrato,ctr_ano,ctr_frn_id',
                'ordem.contrato.fornecedor:frn_id,frn_razao_social',
                'itens',
            ])
            ->orderByDesc('rcb_dt')
            ->orderByDesc('rcb_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($r) => [
                'rcb_id'          => $r->rcb_id,
                'rcb_dt'          => $r->rcb_dt?->toDateString(),
                'rcb_tipo'        => $r->rcb_tipo,
                'rcb_situacao'    => $r->rcb_situacao,
                'rcb_nr_nota'     => $r->rcb_nr_nota,
                'ordem_numero'    => $r->ordem?->ofn_nr_ordem,
                'fornecedor_nome' => $r->ordem?->contrato?->fornecedor?->frn_razao_social,
                'itens_count'     => $r->itens->count(),
                'conferidos'      => $r->itens->filter(fn ($i) => $i->conferido())->count(),
            ]);

        return Inertia::render('alimentacao/recebimentos/Index', [
            'recebimentos' => $recebimentos,
            'situacoes'    => Recebimento::SITUACOES,
            'tipos'        => Recebimento::TIPOS,
            'filters'      => [
                'search'   => $request->input('search', ''),
                'situacao' => $request->input('situacao', ''),
                'tipo'     => $request->input('tipo', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('alimentacao/recebimentos/Create', [
            'ordens' => $this->ordensRecebiveis(),
            'tipos'  => Recebimento::TIPOS,
        ]);
    }

    /** Cria o recebimento já com os itens da ordem, para conferir um a um. */
    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate(
            [
                'rcb_ofn_id'  => ['required', 'integer', 'exists:ali_ordem_fornecimento,ofn_id'],
                'rcb_dt'      => ['required', 'date'],
                'rcb_tipo'    => ['required', 'string', Rule::in(Recebimento::TIPOS)],
                'rcb_nr_nota' => ['nullable', 'string', 'max:30'],
                'rcb_fun_id'  => ['nullable', 'integer', 'exists:edu_funcionario,fun_id'],
                'rcb_obs'     => ['nullable', 'string'],
            ],
            [],
            [
                'rcb_ofn_id'  => 'ordem de fornecimento',
                'rcb_dt'      => 'data do recebimento',
                'rcb_tipo'    => 'tipo',
                'rcb_nr_nota' => 'número da nota',
                'rcb_fun_id'  => 'recebido por',
            ],
        );

        $ordem = OrdemFornecimento::with('itens')->findOrFail($dados['rcb_ofn_id']);

        $this->assertOrdemRecebivel($ordem);

        $recebimento = DB::transaction(function () use ($dados, $ordem, $request) {
            $recebimento = Recebimento::create($dados + [
                'rcb_situacao' => Recebimento::SITUACAO_PENDENTE,
                'rcb_usr_id'   => $request->user()?->id,
            ]);

            // Itens vêm da ordem com o saldo a entregar como previsto: conferir
            // contra o total do pedido ignoraria o que já chegou antes.
            foreach ($ordem->itens as $ordemItem) {
                $aEntregar = (float) $ordemItem->ofi_qtd - (float) $ordemItem->ofi_qtd_entregue;

                if ($aEntregar <= 0) {
                    continue;
                }

                RecebimentoItem::create([
                    'rci_rcb_id'       => $recebimento->rcb_id,
                    'rci_ofi_id'       => $ordemItem->ofi_id,
                    'rci_itm_id'       => $ordemItem->ofi_itm_id,
                    'rci_umd_id'       => $ordemItem->ofi_umd_id,
                    'rci_qtd_prevista' => $aEntregar,
                    'rci_qtd_recebida' => 0,
                    'rci_qtd_rejeitada' => 0,
                ]);
            }

            return $recebimento;
        });

        if ($recebimento->itens()->count() === 0) {
            $recebimento->forceDelete();

            return back()->with('error', 'Esta ordem já teve todos os itens entregues — não há o que receber.');
        }

        return to_route('alimentacao.recebimentos.edit', $recebimento->rcb_id)
            ->with('success', 'Recebimento aberto. Confira os itens e aceite para dar entrada no estoque.');
    }

    public function edit(int $id): Response
    {
        $recebimento = Recebimento::with([
            'ordem:ofn_id,ofn_nr_ordem,ofn_ctr_id,ofn_dt_emissao',
            'ordem.contrato:ctr_id,ctr_nr_contrato,ctr_ano,ctr_frn_id',
            'ordem.contrato.fornecedor:frn_id,frn_razao_social,frn_fl_agricultura_familiar',
            'itens.item:itm_id,itm_nome,itm_fl_perecivel,itm_validade_dias,itm_umd_id',
            'itens.item.unidadeMedida:umd_id,umd_sigla',
            'itens.unidadeMedida:umd_id,umd_sigla',
            'itens.lote:lot_id,lot_cd_lote,lot_dt_validade,lot_mrc_id',
            'itens.ordemItem:ofi_id,ofi_vl_unitario',
        ])->findOrFail($id);

        return Inertia::render('alimentacao/recebimentos/Edit', [
            'recebimento'   => [
                'rcb_id'        => $recebimento->rcb_id,
                'rcb_dt'        => $recebimento->rcb_dt?->toDateString(),
                'rcb_tipo'      => $recebimento->rcb_tipo,
                'rcb_situacao'  => $recebimento->rcb_situacao,
                'rcb_nr_nota'   => $recebimento->rcb_nr_nota,
                'rcb_obs'       => $recebimento->rcb_obs,
                'ordem_numero'  => $recebimento->ordem?->ofn_nr_ordem,
                'contrato'      => $recebimento->ordem?->contrato
                    ? $recebimento->ordem->contrato->ctr_nr_contrato.'/'.$recebimento->ordem->contrato->ctr_ano
                    : null,
                'fornecedor_nome' => $recebimento->ordem?->contrato?->fornecedor?->frn_razao_social,
            ],
            'itens' => $recebimento->itens->map(fn ($i) => [
                'rci_id'            => $i->rci_id,
                'item_nome'         => $i->item?->itm_nome,
                'item_perecivel'    => (bool) $i->item?->itm_fl_perecivel,
                'validade_dias'     => $i->item?->itm_validade_dias,
                'umd_sigla'         => $i->unidadeMedida?->umd_sigla,
                'qtd_prevista'      => (float) $i->rci_qtd_prevista,
                'qtd_recebida'      => (float) $i->rci_qtd_recebida,
                'qtd_rejeitada'     => (float) $i->rci_qtd_rejeitada,
                'motivo_rejeicao'   => $i->rci_motivo_rejeicao,
                'lot_id'            => $i->rci_lot_id,
                'lote_codigo'       => $i->lote?->lot_cd_lote,
                'lote_validade'     => $i->lote?->lot_dt_validade?->toDateString(),
                'lote_mrc_id'       => $i->lote?->lot_mrc_id,
                'vl_unitario'       => $i->ordemItem ? (float) $i->ordemItem->ofi_vl_unitario : null,
            ]),
            'marcas'        => Marca::where('mrc_fl_ativo', true)->orderBy('mrc_nome')->get(['mrc_id', 'mrc_nome']),
            'emConferencia' => $recebimento->emConferencia(),
        ]);
    }

    /** Conferência de um item: quanto chegou, quanto foi rejeitado, qual o lote. */
    public function conferir(Request $request, int $id, int $itemId): RedirectResponse
    {
        $recebimento = Recebimento::findOrFail($id);
        $linha       = RecebimentoItem::where('rci_rcb_id', $recebimento->rcb_id)->findOrFail($itemId);

        $this->assertEmConferencia($recebimento);

        $dados = $request->validate(
            [
                'rci_qtd_recebida'    => ['required', 'numeric', 'min:0'],
                'rci_qtd_rejeitada'   => ['required', 'numeric', 'min:0'],
                'rci_motivo_rejeicao' => ['nullable', 'string', 'max:120', 'required_unless:rci_qtd_rejeitada,0'],
                'lote_codigo'         => ['nullable', 'string', 'max:40'],
                'lote_validade'       => ['nullable', 'date'],
                'lote_mrc_id'         => ['nullable', 'integer', 'exists:ali_marca,mrc_id'],
            ],
            [
                'rci_motivo_rejeicao.required_unless' => 'Informe o motivo da rejeição.',
            ],
            [
                'rci_qtd_recebida'    => 'quantidade recebida',
                'rci_qtd_rejeitada'   => 'quantidade rejeitada',
                'rci_motivo_rejeicao' => 'motivo da rejeição',
                'lote_validade'       => 'validade do lote',
            ],
        );

        $recebida  = (float) $dados['rci_qtd_recebida'];
        $rejeitada = (float) $dados['rci_qtd_rejeitada'];

        // Conferir mais do que a ordem pede significa que o fornecedor entregou
        // além do contratado — o sistema não pode absorver isso em silêncio.
        if ($recebida + $rejeitada > (float) $linha->rci_qtd_prevista + 0.00005) {
            throw ValidationException::withMessages([
                'rci_qtd_recebida' => 'A soma de recebido e rejeitado ('.$this->num($recebida + $rejeitada)
                    .') passa do previsto nesta ordem ('.$this->num($linha->rci_qtd_prevista).').',
            ]);
        }

        DB::transaction(function () use ($linha, $dados, $recebida) {
            $lotId = $linha->rci_lot_id;

            // O lote nasce na conferência: é aqui que se sabe a validade do que chegou.
            if ($recebida > 0) {
                $lote = $lotId ? Lote::find($lotId) : null;

                $atributos = [
                    'lot_itm_id'      => $linha->rci_itm_id,
                    'lot_cd_lote'     => $dados['lote_codigo'] ?? null,
                    'lot_dt_validade' => $dados['lote_validade'] ?? null,
                    'lot_mrc_id'      => $dados['lote_mrc_id'] ?? null,
                ];

                $lote ? $lote->update($atributos) : $lote = Lote::create($atributos);
                $lotId = $lote->lot_id;
            }

            $linha->update([
                'rci_qtd_recebida'    => $dados['rci_qtd_recebida'],
                'rci_qtd_rejeitada'   => $dados['rci_qtd_rejeitada'],
                'rci_motivo_rejeicao' => $dados['rci_motivo_rejeicao'] ?? null,
                'rci_lot_id'          => $lotId,
            ]);
        });

        return back()->with('success', 'Item conferido.');
    }

    /** Aceite: dá entrada no estoque central e fecha a conferência. */
    public function aceitar(Request $request, int $id): RedirectResponse
    {
        $recebimento = Recebimento::findOrFail($id);

        app(AceitaRecebimento::class)->aceitar($recebimento, $request->user()?->id);

        return to_route('alimentacao.recebimentos.edit', $id)
            ->with('success', 'Recebimento aceito. Os itens conferidos deram entrada no estoque central.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $recebimento = Recebimento::findOrFail($id);

        // Aceito já virou estoque: apagar deixaria o saldo sem a origem.
        if (! $recebimento->emConferencia()) {
            return back()->with('error', 'Este recebimento já foi aceito e não pode ser excluído. Estorne os movimentos de estoque se precisar corrigir.');
        }

        $recebimento->itens()->delete();

        return $this->safeDelete($recebimento)
            ?? to_route('alimentacao.recebimentos.index')->with('success', 'Recebimento removido.');
    }

    // ------------------------------------------------------------------

    private function assertOrdemRecebivel(OrdemFornecimento $ordem): void
    {
        if (in_array($ordem->ofn_situacao, [OrdemFornecimento::SITUACAO_EMITIDA, OrdemFornecimento::SITUACAO_PARCIAL], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'rcb_ofn_id' => "A ordem {$ordem->ofn_nr_ordem} está {$ordem->ofn_situacao} e não aceita recebimento.",
        ]);
    }

    private function assertEmConferencia(Recebimento $recebimento): void
    {
        if ($recebimento->emConferencia()) {
            return;
        }

        throw ValidationException::withMessages([
            'rci_qtd_recebida' => "Este recebimento já está {$recebimento->rcb_situacao}: os itens não podem mais ser alterados.",
        ]);
    }

    /** Ordens que ainda têm o que entregar. */
    private function ordensRecebiveis()
    {
        return OrdemFornecimento::query()
            ->whereIn('ofn_situacao', [OrdemFornecimento::SITUACAO_EMITIDA, OrdemFornecimento::SITUACAO_PARCIAL])
            ->whereHas('itens', fn ($q) => $q->whereRaw('ofi_qtd > ofi_qtd_entregue'))
            ->with(['contrato:ctr_id,ctr_nr_contrato,ctr_ano,ctr_frn_id', 'contrato.fornecedor:frn_id,frn_razao_social'])
            ->orderByDesc('ofn_dt_emissao')
            ->get()
            ->map(fn ($o) => [
                'ofn_id'   => $o->ofn_id,
                'rotulo'   => $o->ofn_nr_ordem.' — '.($o->contrato?->fornecedor?->frn_razao_social ?? 'sem fornecedor'),
                'situacao' => $o->ofn_situacao,
            ]);
    }

    private function num($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    }
}
