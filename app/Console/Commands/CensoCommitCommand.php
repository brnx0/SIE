<?php

namespace App\Console\Commands;

use App\Models\Censo\CensoLote;
use App\Services\Censo\CensoCommitManager;
use Illuminate\Console\Command;

/**
 * Fase B da importação do Censo (M2): comita escolas e turmas de um lote já
 * parseado (staging). Insert-only. Ver docs/regras/importacao_censo.md.
 *
 *   php artisan censo:commit {loteId}
 */
class CensoCommitCommand extends Command
{
    protected $signature = 'censo:commit {lote : ID do lote (icl_id) já parseado}';

    protected $description = 'Comita escolas e turmas de um lote de Censo para produção (fase B / M2).';

    public function handle(CensoCommitManager $manager): int
    {
        $lote = CensoLote::find($this->argument('lote'));
        if (! $lote) {
            $this->error('Lote não encontrado.');

            return self::FAILURE;
        }

        if ($lote->icl_status === 'PENDENTE') {
            $this->error('Lote ainda não foi parseado (rode censo:stage antes).');

            return self::FAILURE;
        }

        $this->info("Comitando lote #{$lote->icl_id} (ano letivo #{$lote->icl_anl_id})...");

        $resumo = $manager->commit($lote);

        $this->newLine();
        $this->table(
            ['Métrica', 'Qtd'],
            collect($resumo)->map(fn ($v, $k) => [$k, $v])->values()->all()
        );

        return self::SUCCESS;
    }
}
