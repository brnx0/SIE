<?php

namespace App\Http\Controllers\Alimentacao;

use App\Http\Controllers\Controller;
use App\Models\Alimentacao\ContratoItem;
use App\Models\Alimentacao\OrdemFornecimento;
use App\Models\Alimentacao\OrdemFornecimentoItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Itens da ordem de fornecimento — sub-lista da tela da ordem.
 *
 * É aqui que os saldos são cobrados: não se pede mais do que o contrato tem, nem
 * se gasta mais do que o empenho cobre.
 */
class OrdemFornecimentoItemController extends Controller
{
    public function store(Request $request, int $ordemId): RedirectResponse
    {
        $ordem = OrdemFornecimento::with('contrato')->findOrFail($ordemId);
        $dados = $this->validar($request, $ordem);

        DB::transaction(function () use ($ordem, $dados) {
            $this->assertDentroDoSaldo($ordem, (int) $dados['cti_id'], (float) $dados['ofi_qtd'], null);
            $this->assertDentroDoEmpenho($ordem, (float) $dados['ofi_qtd'] * (float) $dados['ofi_vl_unitario'], null);

            $contratoItem = ContratoItem::findOrFail($dados['cti_id']);

            OrdemFornecimentoItem::create([
                'ofi_ofn_id'      => $ordem->ofn_id,
                'ofi_itm_id'      => $contratoItem->cti_itm_id,
                'ofi_umd_id'      => $contratoItem->cti_umd_id,
                'ofi_qtd'         => $dados['ofi_qtd'],
                'ofi_vl_unitario' => $dados['ofi_vl_unitario'],
            ]);
        });

        return back()->with('success', 'Item adicionado à ordem de fornecimento.');
    }

    public function update(Request $request, int $ordemId, int $ordemItemId): RedirectResponse
    {
        $ordem     = OrdemFornecimento::with('contrato')->findOrFail($ordemId);
        $ordemItem = OrdemFornecimentoItem::where('ofi_ofn_id', $ordem->ofn_id)->findOrFail($ordemItemId);

        $this->assertOrdemEditavel($ordem);

        $dados = $this->validar($request, $ordem, $ordemItemId);

        DB::transaction(function () use ($ordem, $ordemItem, $dados) {
            $this->assertDentroDoSaldo($ordem, (int) $dados['cti_id'], (float) $dados['ofi_qtd'], $ordemItem->ofi_id);
            $this->assertDentroDoEmpenho($ordem, (float) $dados['ofi_qtd'] * (float) $dados['ofi_vl_unitario'], $ordemItem->ofi_id);

            $contratoItem = ContratoItem::findOrFail($dados['cti_id']);

            $ordemItem->update([
                'ofi_itm_id'      => $contratoItem->cti_itm_id,
                'ofi_umd_id'      => $contratoItem->cti_umd_id,
                'ofi_qtd'         => $dados['ofi_qtd'],
                'ofi_vl_unitario' => $dados['ofi_vl_unitario'],
            ]);
        });

        return back()->with('success', 'Item da ordem atualizado.');
    }

    public function destroy(int $ordemId, int $ordemItemId): RedirectResponse
    {
        $ordem     = OrdemFornecimento::findOrFail($ordemId);
        $ordemItem = OrdemFornecimentoItem::where('ofi_ofn_id', $ordem->ofn_id)->findOrFail($ordemItemId);

        $this->assertOrdemEditavel($ordem);

        if ((float) $ordemItem->ofi_qtd_entregue > 0) {
            return back()->with('error', 'Este item já teve entrega registrada e não pode ser removido da ordem.');
        }

        return $this->safeDelete($ordemItem)
            ?? back()->with('success', 'Item removido da ordem.');
    }

    private function validar(Request $request, OrdemFornecimento $ordem, ?int $ignorarId = null): array
    {
        return $request->validate(
            [
                // O item vem do contrato, não do catálogo geral: só se pede o que foi contratado.
                'cti_id' => [
                    'required', 'integer',
                    Rule::exists('ali_contrato_item', 'cti_id')->where(fn ($q) => $q->where('cti_ctr_id', $ordem->ofn_ctr_id)),
                ],
                'ofi_qtd'         => ['required', 'numeric', 'gt:0'],
                'ofi_vl_unitario' => ['required', 'numeric', 'gt:0'],
            ],
            [
                'cti_id.exists'      => 'O item escolhido não faz parte deste contrato.',
                'ofi_qtd.gt'         => 'A quantidade deve ser maior que zero.',
                'ofi_vl_unitario.gt' => 'O valor unitário deve ser maior que zero.',
            ],
            [
                'cti_id'          => 'item do contrato',
                'ofi_qtd'         => 'quantidade',
                'ofi_vl_unitario' => 'valor unitário',
            ],
        );
    }

    /** Ordem entregue ou cancelada é registro fechado: mexer reescreveria o passado. */
    private function assertOrdemEditavel(OrdemFornecimento $ordem): void
    {
        if (in_array($ordem->ofn_situacao, [OrdemFornecimento::SITUACAO_EMITIDA, OrdemFornecimento::SITUACAO_PARCIAL], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'ofi_qtd' => "Esta ordem está {$ordem->ofn_situacao} e seus itens não podem mais ser alterados.",
        ]);
    }

    /**
     * A soma pedida do item em todas as ordens não canceladas do contrato não
     * pode passar da quantidade contratada — senão a prefeitura compra mais do
     * que contratou, e o estouro só apareceria na prestação de contas.
     */
    private function assertDentroDoSaldo(OrdemFornecimento $ordem, int $ctiId, float $qtd, ?int $ignorarOfiId): void
    {
        $contratoItem = ContratoItem::with(['item:itm_id,itm_nome', 'unidadeMedida:umd_id,umd_sigla'])
            ->lockForUpdate()
            ->findOrFail($ctiId);

        $jaPedido = (float) DB::table('ali_ordem_fornecimento_item as ofi')
            ->join('ali_ordem_fornecimento as ofn', 'ofn.ofn_id', '=', 'ofi.ofi_ofn_id')
            ->where('ofn.ofn_ctr_id', $ordem->ofn_ctr_id)
            ->where('ofi.ofi_itm_id', $contratoItem->cti_itm_id)
            ->where('ofn.ofn_situacao', '!=', OrdemFornecimento::SITUACAO_CANCELADA)
            ->whereNull('ofn.ofn_deleted_at')
            ->when($ignorarOfiId, fn ($q, $id) => $q->where('ofi.ofi_id', '!=', $id))
            ->sum('ofi.ofi_qtd');

        $saldo = (float) $contratoItem->cti_qtd - $jaPedido;

        if ($qtd <= $saldo) {
            return;
        }

        $sigla = $contratoItem->unidadeMedida?->umd_sigla;

        throw ValidationException::withMessages([
            'ofi_qtd' => "Saldo insuficiente no contrato para {$contratoItem->item?->itm_nome}."
                .' Contratado: '.$this->fmt((float) $contratoItem->cti_qtd)." {$sigla}"
                .' · já pedido: '.$this->fmt($jaPedido)." {$sigla}"
                .' · disponível: '.$this->fmt($saldo)." {$sigla}.",
        ]);
    }

    /** O valor das ordens não pode passar do que o empenho reservou. */
    private function assertDentroDoEmpenho(OrdemFornecimento $ordem, float $valorItem, ?int $ignorarOfiId): void
    {
        if (! $ordem->ofn_emp_id) {
            return; // ordem sem empenho vinculado
        }

        $empenho = \App\Models\Alimentacao\Empenho::lockForUpdate()->findOrFail($ordem->ofn_emp_id);

        $consumido = (float) DB::table('ali_ordem_fornecimento_item as ofi')
            ->join('ali_ordem_fornecimento as ofn', 'ofn.ofn_id', '=', 'ofi.ofi_ofn_id')
            ->where('ofn.ofn_emp_id', $empenho->emp_id)
            ->where('ofn.ofn_situacao', '!=', OrdemFornecimento::SITUACAO_CANCELADA)
            ->whereNull('ofn.ofn_deleted_at')
            ->when($ignorarOfiId, fn ($q, $id) => $q->where('ofi.ofi_id', '!=', $id))
            ->sum(DB::raw('ofi.ofi_qtd * ofi.ofi_vl_unitario'));

        $saldo = (float) $empenho->emp_vl_empenhado - $consumido;

        if ($valorItem <= $saldo) {
            return;
        }

        throw ValidationException::withMessages([
            'ofi_qtd' => "Saldo insuficiente no empenho {$empenho->emp_nr_empenho}."
                .' Empenhado: R$ '.number_format((float) $empenho->emp_vl_empenhado, 2, ',', '.')
                .' · já consumido: R$ '.number_format($consumido, 2, ',', '.')
                .' · disponível: R$ '.number_format($saldo, 2, ',', '.')
                .' · este item: R$ '.number_format($valorItem, 2, ',', '.').'.',
        ]);
    }

    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',');
    }
}
