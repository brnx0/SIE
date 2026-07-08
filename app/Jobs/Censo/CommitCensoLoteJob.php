<?php

namespace App\Jobs\Censo;

use App\Models\Censo\CensoLote;
use App\Services\Censo\CensoCommitManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Fase B assíncrona: comita o lote para produção (insert-only). Requer worker.
 */
class CommitCensoLoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public int $loteId)
    {
    }

    public function handle(CensoCommitManager $manager): void
    {
        $lote = CensoLote::find($this->loteId);
        if (! $lote || $lote->icl_status === 'PENDENTE') {
            return;
        }

        $lote->update(['icl_status' => 'COMMITANDO']);
        $resumo = $manager->commit($lote);

        $lote->update([
            'icl_status'   => 'COMMITADO',
            'icl_mensagem' => json_encode($resumo, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function failed(Throwable $e): void
    {
        CensoLote::whereKey($this->loteId)->update([
            'icl_status'   => 'ERRO',
            'icl_mensagem' => 'Falha ao comitar: '.$e->getMessage(),
        ]);
    }
}
