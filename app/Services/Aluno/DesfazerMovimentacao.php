<?php

namespace App\Services\Aluno;

use App\Models\Aluno\AlunoMovimentacao;
use App\Models\Matricula\Matricula;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DesfazerMovimentacao
{
    public function executar(AlunoMovimentacao $mva, ?string $motivo = null): AlunoMovimentacao
    {
        if ($mva->mva_fl_cancelada) {
            throw ValidationException::withMessages([
                'mva_id' => 'Movimentação já foi desfeita.',
            ]);
        }

        return DB::transaction(function () use ($mva, $motivo) {
            // Reativa matrícula de origem
            if ($mva->mva_tma_id_origem) {
                Matricula::where('tma_id', $mva->mva_tma_id_origem)->update([
                    'tma_situacao'      => Matricula::SITUACAO_ATIVA,
                    'tma_tas_cod_saida' => null,
                    'tma_dt_saida'      => null,
                ]);
            }

            // Reativa matriculas complementares (AEE/ATIVIDADE) encerradas junto
            foreach ((array) ($mva->mva_tmas_extras ?? []) as $extra) {
                if (! empty($extra['tma_id'])) {
                    Matricula::where('tma_id', $extra['tma_id'])->update([
                        'tma_situacao'      => Matricula::SITUACAO_ATIVA,
                        'tma_tas_cod_saida' => null,
                        'tma_dt_saida'      => null,
                    ]);
                }
            }

            // Remove matrícula de destino criada (se houver)
            if ($mva->mva_tma_id_destino) {
                Matricula::where('tma_id', $mva->mva_tma_id_destino)->delete();
            }

            $mva->update([
                'mva_fl_cancelada'       => true,
                'mva_cancelada_motivo'   => $motivo,
                'mva_cancelada_at'       => now(),
                'mva_cancelada_user_id'  => Auth::id(),
            ]);

            return $mva->fresh();
        });
    }
}
