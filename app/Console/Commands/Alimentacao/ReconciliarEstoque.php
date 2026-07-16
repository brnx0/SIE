<?php

namespace App\Console\Commands\Alimentacao;

use App\Models\Alimentacao\EstoqueSaldo;
use App\Models\Alimentacao\Movimento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Confere o saldo materializado (`ali_estoque_saldo`) contra o razão
 * (`ali_movimento`), que é a fonte da verdade.
 *
 * O saldo é cache: escrito na mesma transação do movimento, mas cache. Se algum
 * dia divergir (bug, escrita fora do MovimentaEstoque, restore parcial), é este
 * comando que mostra — e, com --corrigir, reescreve o saldo a partir do ledger.
 */
class ReconciliarEstoque extends Command
{
    protected $signature = 'alimentacao:reconciliar-estoque
                            {--corrigir : Reescreve o saldo divergente a partir do ledger}';

    protected $description = 'Confere o saldo de estoque contra o razão de movimentos';

    public function handle(): int
    {
        $doLedger = DB::table('ali_movimento')
            ->selectRaw("
                mov_esc_id,
                mov_itm_id,
                mov_lot_id,
                SUM(CASE WHEN mov_tipo = 'ENTRADA' THEN mov_qtd_base ELSE -mov_qtd_base END) AS qtd
            ")
            ->groupBy('mov_esc_id', 'mov_itm_id', 'mov_lot_id')
            ->get()
            ->keyBy(fn ($l) => $this->chave($l->mov_esc_id, $l->mov_itm_id, $l->mov_lot_id));

        $doSaldo = EstoqueSaldo::all()
            ->keyBy(fn ($s) => $this->chave($s->sld_esc_id, $s->sld_itm_id, $s->sld_lot_id));

        $divergencias = [];

        // Saldo que não bate com o ledger, ou linha de saldo sem movimento nenhum.
        foreach ($doSaldo as $chave => $saldo) {
            $esperado = (float) ($doLedger[$chave]->qtd ?? 0);
            $atual    = (float) $saldo->sld_qtd_base;

            if (abs($esperado - $atual) > 0.00005) {
                $divergencias[] = [$chave, $atual, $esperado, $saldo];
            }
        }

        // Movimento sem linha de saldo correspondente.
        foreach ($doLedger as $chave => $linha) {
            if (! isset($doSaldo[$chave]) && abs((float) $linha->qtd) > 0.00005) {
                $divergencias[] = [$chave, 0.0, (float) $linha->qtd, null];
            }
        }

        if (empty($divergencias)) {
            $this->info('Estoque conferido: saldo e razão batem em todas as combinações (estoque × item × lote).');

            return self::SUCCESS;
        }

        $this->warn(count($divergencias).' divergência(s) entre o saldo e o razão:');
        $this->table(
            ['Estoque/Item/Lote', 'Saldo atual', 'Razão diz', 'Diferença'],
            array_map(fn ($d) => [$d[0], $this->fmt($d[1]), $this->fmt($d[2]), $this->fmt($d[2] - $d[1])], $divergencias),
        );

        if (! $this->option('corrigir')) {
            $this->line('');
            $this->line('Rode com --corrigir para reescrever o saldo a partir do razão.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($divergencias) {
            foreach ($divergencias as [$chave, , $esperado, $saldo]) {
                if ($saldo) {
                    $saldo->update(['sld_qtd_base' => max(0, $esperado)]);
                    continue;
                }

                [$escId, $itmId, $lotId] = $this->desmontar($chave);
                EstoqueSaldo::create([
                    'sld_esc_id'   => $escId,
                    'sld_itm_id'   => $itmId,
                    'sld_lot_id'   => $lotId,
                    'sld_qtd_base' => max(0, $esperado),
                    // O custo médio não é reconstituível a partir do razão sozinho
                    // (depende da ordem das entradas): fica zerado e precisa de
                    // revisão manual.
                    'sld_vl_custo_medio' => 0,
                ]);
            }
        });

        $this->info('Saldo reescrito a partir do razão.');
        $this->warn('Atenção: linhas de saldo recriadas ficam com custo médio zerado — revise a valorização desses itens.');

        return self::SUCCESS;
    }

    private function chave(?int $escId, int $itmId, int $lotId): string
    {
        return ($escId ?? 'central')."/{$itmId}/{$lotId}";
    }

    private function desmontar(string $chave): array
    {
        [$esc, $itm, $lot] = explode('/', $chave);

        return [$esc === 'central' ? null : (int) $esc, (int) $itm, (int) $lot];
    }

    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',');
    }
}
