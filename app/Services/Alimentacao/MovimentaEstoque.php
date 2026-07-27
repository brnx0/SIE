<?php

namespace App\Services\Alimentacao;

use App\Models\Alimentacao\EstoqueSaldo;
use App\Models\Alimentacao\Item;
use App\Models\Alimentacao\Lote;
use App\Models\Alimentacao\Movimento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único ponto que escreve no estoque.
 *
 * Toda operação grava o movimento no ledger e atualiza o saldo materializado na
 * MESMA transação, com lock na linha do saldo. Nada fora daqui deve tocar em
 * `ali_movimento` ou `ali_estoque_saldo`: saldo e histórico precisam andar juntos.
 *
 * Convenção de estoque: `escId` nulo = central; preenchido = aquela escola.
 */
class MovimentaEstoque
{
    public function __construct(private ConversorUnidade $conversor)
    {
    }

    /**
     * Entrada no estoque (recebimento, ajuste positivo, agricultura familiar).
     *
     * @param  float|null  $vlUnitario  custo na unidade DIGITADA (convertido para a base aqui)
     */
    public function entrada(
        ?int $escId,
        Lote $lote,
        float $qtd,
        int $umdId,
        string $dt,
        array $extra = [],
        ?float $vlUnitario = null,
    ): Movimento {
        return DB::transaction(function () use ($escId, $lote, $qtd, $umdId, $dt, $extra, $vlUnitario) {
            $item     = Item::findOrFail($lote->lot_itm_id);
            $qtdBase  = $this->paraBase($item, $qtd, $umdId);
            $custoBase = $this->custoNaBase($vlUnitario, $qtd, $qtdBase);

            $movimento = $this->gravar(array_merge([
                'mov_esc_id'      => $escId,
                'mov_itm_id'      => $item->itm_id,
                'mov_lot_id'      => $lote->lot_id,
                'mov_umd_id'      => $umdId,
                'mov_tipo'        => Movimento::TIPO_ENTRADA,
                'mov_qtd'         => $qtd,
                'mov_qtd_base'    => $qtdBase,
                'mov_vl_unitario' => $custoBase,
                'mov_vl_total'    => $custoBase !== null ? $custoBase * $qtdBase : null,
                'mov_dt'          => $dt,
            ], $extra));

            $this->aplicarNoSaldo($escId, $item->itm_id, $lote->lot_id, $qtdBase, $custoBase);

            return $movimento;
        });
    }

    /**
     * Baixa de um lote específico (saída, retirada, consumo, ajuste negativo).
     * Use `baixarFefo()` quando o lote não for escolhido pelo usuário.
     */
    public function baixa(
        ?int $escId,
        Lote $lote,
        float $qtd,
        int $umdId,
        string $tipo,
        string $dt,
        array $extra = [],
    ): Movimento {
        return DB::transaction(function () use ($escId, $lote, $qtd, $umdId, $tipo, $dt, $extra) {
            $item    = Item::findOrFail($lote->lot_itm_id);
            $qtdBase = $this->paraBase($item, $qtd, $umdId);

            $saldo = $this->saldoBloqueado($escId, $item->itm_id, $lote->lot_id);
            $this->assertSaldoSuficiente($saldo, $qtdBase, $item, $lote);

            // Baixa sai pelo custo médio do saldo — é o que valoriza a saída.
            $custo = $saldo ? (float) $saldo->sld_vl_custo_medio : null;

            $movimento = $this->gravar(array_merge([
                'mov_esc_id'      => $escId,
                'mov_itm_id'      => $item->itm_id,
                'mov_lot_id'      => $lote->lot_id,
                'mov_umd_id'      => $umdId,
                'mov_tipo'        => $tipo,
                'mov_qtd'         => $qtd,
                'mov_qtd_base'    => $qtdBase,
                'mov_vl_unitario' => $custo,
                'mov_vl_total'    => $custo !== null ? $custo * $qtdBase : null,
                'mov_dt'          => $dt,
            ], $extra));

            $saldo->update(['sld_qtd_base' => (float) $saldo->sld_qtd_base - $qtdBase]);

            return $movimento;
        });
    }

    /**
     * Baixa por FEFO (first expire, first out): consome os lotes que vencem antes.
     * Pode gerar mais de um movimento — a quantidade pedida costuma cruzar lotes.
     *
     * @return Collection<int, Movimento>
     */
    public function baixarFefo(
        ?int $escId,
        Item $item,
        float $qtd,
        int $umdId,
        string $tipo,
        string $dt,
        array $extra = [],
    ): Collection {
        return DB::transaction(function () use ($escId, $item, $qtd, $umdId, $tipo, $dt, $extra) {
            $restante = $this->paraBase($item, $qtd, $umdId);

            $this->assertSaldoTotalSuficiente($escId, $item, $restante);

            $movimentos = collect();

            foreach ($this->lotesFefo($escId, $item) as $saldo) {
                if ($restante <= 0) {
                    break;
                }

                $disponivel = (float) $saldo->sld_qtd_base;
                $baixar     = min($disponivel, $restante);
                $custo      = (float) $saldo->sld_vl_custo_medio;

                // A quantidade do movimento vai na unidade base: o pedido do
                // usuário foi fatiado entre lotes e não corresponde mais à
                // quantidade digitada.
                $movimentos->push($this->gravar(array_merge([
                    'mov_esc_id'      => $escId,
                    'mov_itm_id'      => $item->itm_id,
                    'mov_lot_id'      => $saldo->sld_lot_id,
                    'mov_umd_id'      => $item->itm_umd_id,
                    'mov_tipo'        => $tipo,
                    'mov_qtd'         => $baixar,
                    'mov_qtd_base'    => $baixar,
                    'mov_vl_unitario' => $custo,
                    'mov_vl_total'    => $custo * $baixar,
                    'mov_dt'          => $dt,
                ], $extra)));

                $saldo->update(['sld_qtd_base' => $disponivel - $baixar]);
                $restante -= $baixar;
            }

            return $movimentos;
        });
    }

    /**
     * Transferência entre estoques: SAIDA na origem + ENTRADA no destino, ligadas
     * por `mov_trf_par_id`. É o que alimenta o protocolo da central de distribuição.
     *
     * @return Collection<int, Movimento> pares [saida, entrada]
     */
    public function transferir(
        ?int $escOrigemId,
        ?int $escDestinoId,
        Item $item,
        float $qtd,
        int $umdId,
        string $dt,
        array $extra = [],
    ): Collection {
        if ($escOrigemId === $escDestinoId) {
            throw ValidationException::withMessages([
                'esc_destino_id' => 'O estoque de destino deve ser diferente do de origem.',
            ]);
        }

        return DB::transaction(function () use ($escOrigemId, $escDestinoId, $item, $qtd, $umdId, $dt, $extra) {
            $saidas = $this->baixarFefo($escOrigemId, $item, $qtd, $umdId, Movimento::TIPO_TRANSFERENCIA, $dt, $extra);

            $pares = collect();

            // Uma entrada por lote baixado: o destino recebe os mesmos lotes, com
            // a mesma validade — senão o FEFO e o relatório de vencimento na escola
            // perderiam a informação.
            foreach ($saidas as $saida) {
                $lote = Lote::findOrFail($saida->mov_lot_id);

                $entrada = $this->entrada(
                    $escDestinoId,
                    $lote,
                    (float) $saida->mov_qtd_base,
                    $item->itm_umd_id,
                    $dt,
                    array_merge($extra, ['mov_trf_par_id' => $saida->mov_id]),
                    $saida->mov_vl_unitario !== null ? (float) $saida->mov_vl_unitario : null,
                );

                // Ligação nos dois sentidos: de qualquer lado se acha o par.
                $saida->update(['mov_trf_par_id' => $entrada->mov_id]);

                $pares->push([$saida, $entrada]);
            }

            return $pares;
        });
    }

    /**
     * Estorna um movimento: grava o inverso apontando para o original.
     *
     * Não apaga nem edita a linha — o ledger é append-only, então desfazer é
     * lançar o contrário, e os dois ficam visíveis no histórico.
     */
    public function estornar(Movimento $movimento, string $motivo, ?int $usuarioId): Movimento
    {
        return DB::transaction(function () use ($movimento, $motivo, $usuarioId) {
            $jaEstornado = Movimento::where('mov_estorno_de_id', $movimento->mov_id)->exists();

            if ($jaEstornado) {
                throw ValidationException::withMessages([
                    'motivo' => 'Este movimento já foi estornado.',
                ]);
            }

            if ($movimento->mov_tipo === Movimento::TIPO_ESTORNO) {
                throw ValidationException::withMessages([
                    'motivo' => 'Um estorno não pode ser estornado. Lance um novo movimento se precisar corrigir.',
                ]);
            }

            $qtdBase = (float) $movimento->mov_qtd_base;
            $item    = Item::findOrFail($movimento->mov_itm_id);

            // Estorno de entrada tira do saldo; estorno de baixa devolve.
            $devolve = $movimento->sinal() === -1;

            if (! $devolve) {
                $saldo = $this->saldoBloqueado($movimento->mov_esc_id, $item->itm_id, $movimento->mov_lot_id);
                $this->assertSaldoSuficiente($saldo, $qtdBase, $item, Lote::find($movimento->mov_lot_id), 'estornar esta entrada');
                $saldo->update(['sld_qtd_base' => (float) $saldo->sld_qtd_base - $qtdBase]);
            } else {
                $this->aplicarNoSaldo(
                    $movimento->mov_esc_id,
                    $item->itm_id,
                    $movimento->mov_lot_id,
                    $qtdBase,
                    $movimento->mov_vl_unitario !== null ? (float) $movimento->mov_vl_unitario : null,
                );
            }

            return $this->gravar([
                'mov_esc_id'        => $movimento->mov_esc_id,
                'mov_itm_id'        => $movimento->mov_itm_id,
                'mov_lot_id'        => $movimento->mov_lot_id,
                'mov_umd_id'        => $movimento->mov_umd_id,
                'mov_tipo'          => Movimento::TIPO_ESTORNO,
                'mov_qtd'           => $movimento->mov_qtd,
                'mov_qtd_base'      => $qtdBase,
                'mov_vl_unitario'   => $movimento->mov_vl_unitario,
                'mov_vl_total'      => $movimento->mov_vl_total,
                'mov_dt'            => now()->toDateString(),
                'mov_estorno_de_id' => $movimento->mov_id,
                'mov_usr_id'        => $usuarioId,
                'mov_obs'           => $motivo,
            ]);
        });
    }

    /** Saldo atual de um item num estoque, somando os lotes. */
    public function saldoDoItem(?int $escId, int $itmId): float
    {
        return (float) EstoqueSaldo::query()
            ->doEstoque($escId)
            ->where('sld_itm_id', $itmId)
            ->sum('sld_qtd_base');
    }

    // ------------------------------------------------------------------

    private function gravar(array $dados): Movimento
    {
        // O estorno inverte o sinal na leitura (pelo tipo), então o ledger nunca
        // guarda quantidade negativa — o CHECK do banco também exige isso.
        return Movimento::create($dados);
    }

    private function paraBase(Item $item, float $qtd, int $umdId): float
    {
        return $this->conversor->paraUnidadeBase($item, $qtd, $umdId);
    }

    /**
     * O custo é digitado na unidade do usuário (R$/CX); o saldo trabalha na
     * unidade base (R$/KG). Sem converter, uma caixa de R$ 90 viraria R$ 90/kg.
     */
    private function custoNaBase(?float $vlUnitario, float $qtd, float $qtdBase): ?float
    {
        if ($vlUnitario === null || $qtdBase <= 0) {
            return null;
        }

        return ($vlUnitario * $qtd) / $qtdBase;
    }

    private function saldoBloqueado(?int $escId, int $itmId, int $lotId): ?EstoqueSaldo
    {
        return EstoqueSaldo::query()
            ->doEstoque($escId)
            ->where('sld_itm_id', $itmId)
            ->where('sld_lot_id', $lotId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Cria ou atualiza o saldo, recalculando o custo médio ponderado.
     *
     * Média ponderada: (saldo × custo_atual + entrada × custo_entrada) ÷ total.
     * Sem isso, o valor do estoque passaria a refletir só a última compra.
     */
    private function aplicarNoSaldo(?int $escId, int $itmId, int $lotId, float $qtdBase, ?float $custoEntrada): void
    {
        $saldo = $this->saldoBloqueado($escId, $itmId, $lotId);

        if (! $saldo) {
            EstoqueSaldo::create([
                'sld_esc_id'         => $escId,
                'sld_itm_id'         => $itmId,
                'sld_lot_id'         => $lotId,
                'sld_qtd_base'       => $qtdBase,
                'sld_vl_custo_medio' => $custoEntrada ?? 0,
            ]);

            return;
        }

        $qtdAtual   = (float) $saldo->sld_qtd_base;
        $custoAtual = (float) $saldo->sld_vl_custo_medio;
        $novaQtd    = $qtdAtual + $qtdBase;

        $novoCusto = $custoEntrada === null || $novaQtd <= 0
            ? $custoAtual
            : (($qtdAtual * $custoAtual) + ($qtdBase * $custoEntrada)) / $novaQtd;

        $saldo->update([
            'sld_qtd_base'       => $novaQtd,
            'sld_vl_custo_medio' => $novoCusto,
        ]);
    }

    private function assertSaldoSuficiente(?EstoqueSaldo $saldo, float $qtdBase, Item $item, ?Lote $lote, string $acao = 'dar esta baixa'): void
    {
        $disponivel = $saldo ? (float) $saldo->sld_qtd_base : 0.0;

        if ($disponivel >= $qtdBase) {
            return;
        }

        $sigla = $item->unidadeMedida?->umd_sigla;

        throw ValidationException::withMessages([
            'qtd' => "Saldo insuficiente para {$acao}. {$item->itm_nome}"
                .($lote?->lot_cd_lote ? " (lote {$lote->lot_cd_lote})" : '')
                .' tem '.$this->fmt($disponivel)." {$sigla} em estoque; a operação pede "
                .$this->fmt($qtdBase)." {$sigla}.",
        ]);
    }

    private function assertSaldoTotalSuficiente(?int $escId, Item $item, float $qtdBase): void
    {
        $disponivel = $this->saldoDoItem($escId, $item->itm_id);

        if ($disponivel >= $qtdBase) {
            return;
        }

        $sigla = $item->unidadeMedida?->umd_sigla;

        throw ValidationException::withMessages([
            'qtd' => "Saldo insuficiente de {$item->itm_nome}: há ".$this->fmt($disponivel)." {$sigla}"
                .' em estoque e a operação pede '.$this->fmt($qtdBase)." {$sigla}.",
        ]);
    }

    /** @return Collection<int, EstoqueSaldo> lotes com saldo, na ordem FEFO */
    private function lotesFefo(?int $escId, Item $item): Collection
    {
        return EstoqueSaldo::query()
            ->doEstoque($escId)
            ->where('sld_itm_id', $item->itm_id)
            ->comSaldo()
            ->join('ali_lote', 'ali_lote.lot_id', '=', 'ali_estoque_saldo.sld_lot_id')
            ->orderByRaw('ali_lote.lot_dt_validade IS NULL')
            ->orderBy('ali_lote.lot_dt_validade')
            ->orderBy('ali_estoque_saldo.sld_id')
            ->lockForUpdate()
            ->select('ali_estoque_saldo.*')
            ->get();
    }

    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',');
    }
}
