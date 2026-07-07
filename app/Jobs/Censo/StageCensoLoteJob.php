<?php

namespace App\Jobs\Censo;

use App\Models\Censo\CensoLote;
use App\Services\Censo\CensoImportManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Fase A assíncrona: parseia o arquivo do lote para staging.
 * Disparado pela tela de upload. Requer worker (queue:work).
 */
class StageCensoLoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $loteId)
    {
    }

    public function handle(CensoImportManager $manager): void
    {
        $lote = CensoLote::find($this->loteId);
        if (! $lote) {
            return;
        }

        $lote->update(['icl_status' => 'PROCESSANDO']);
        $manager->runStage($lote);
    }

    public function failed(Throwable $e): void
    {
        CensoLote::whereKey($this->loteId)->update([
            'icl_status'   => 'ERRO',
            'icl_mensagem' => 'Falha ao parsear: '.$e->getMessage(),
        ]);
    }
}
