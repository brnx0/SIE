<?php

namespace App\Console\Commands;

use App\Models\Censo\CensoRegistro;
use App\Services\Censo\CensoImportManager;
use Illuminate\Console\Command;

/**
 * Fase A da importação do Censo (dev/CLI): parseia o arquivo de migração para
 * as tabelas de staging e imprime um relatório. Não toca produção (edu_*).
 *
 *   php artisan censo:stage caminho/arquivo.txt --ano=2025
 */
class CensoStageCommand extends Command
{
    protected $signature = 'censo:stage
        {arquivo : Caminho do arquivo .txt de migração do Censo}
        {--ano= : Ano letivo destino (anl_id) das matrículas/turmas}';

    protected $description = 'Parseia o arquivo de migração do Censo para staging (fase A).';

    public function handle(CensoImportManager $manager): int
    {
        $arquivo = $this->argument('arquivo');
        $ano = (int) $this->option('ano');

        if ($ano <= 0) {
            $this->error('Informe o ano letivo destino: --ano=<anl_id>');

            return self::FAILURE;
        }

        $this->info("Parseando {$arquivo} (ano letivo #{$ano})...");

        $lote = $manager->stage($arquivo, $ano);

        $this->newLine();
        $this->info("Lote #{$lote->icl_id} — layout {$lote->icl_versao_layout} — status {$lote->icl_status}");
        $this->line("Linhas: {$lote->icl_total_linhas}  |  Erros: {$lote->icl_total_erros}");

        $this->newLine();
        $this->line('Registros por tipo:');
        $rows = [];
        foreach (($lote->icl_contadores ?? []) as $tipo => $qtd) {
            $rows[] = [$tipo, $qtd];
        }
        $this->table(['Tipo', 'Qtd'], $rows);

        // Amostra de erros/avisos
        $problemas = CensoRegistro::query()
            ->where('icr_icl_id', $lote->icl_id)
            ->whereIn('icr_status', ['ERRO', 'AVISO'])
            ->limit(15)
            ->get(['icr_linha', 'icr_tipo', 'icr_status', 'icr_erro']);

        if ($problemas->isNotEmpty()) {
            $this->newLine();
            $this->warn('Amostra de problemas:');
            $this->table(
                ['Linha', 'Tipo', 'Status', 'Erro'],
                $problemas->map(fn ($r) => [
                    $r->icr_linha, $r->icr_tipo, $r->icr_status, $r->icr_erro,
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}
