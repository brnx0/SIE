<?php

namespace App\Services\Aluno;

use App\Models\Aluno\AlunoMovimentacao;
use App\Models\Matricula\Matricula;
use App\Models\Turma\Turma;
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

        $this->validarVagas($mva);

        return DB::transaction(function () use ($mva, $motivo) {
            // Remove matrícula de destino ANTES de reativar origem,
            // senão índice parcial "1 ATIVA por ano" dispara unique violation.
            if ($mva->mva_tma_id_destino) {
                Matricula::where('tma_id', $mva->mva_tma_id_destino)->delete();
            }

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

            $mva->update([
                'mva_fl_cancelada'       => true,
                'mva_cancelada_motivo'   => $motivo,
                'mva_cancelada_at'       => now(),
                'mva_cancelada_user_id'  => Auth::id(),
            ]);

            return $mva->fresh();
        });
    }

    /**
     * Ao desfazer, as matrículas de origem (regular) e complementares voltam a
     * ATIVA — cada uma reocupa uma vaga na sua turma. Valida vaga líquida:
     * ativos atuais + retornando ≤ capacidade + expansão.
     *
     * Ativos = matrículas ATIVA (tma_tas_cod_saida nulo).
     */
    protected function validarVagas(AlunoMovimentacao $mva): void
    {
        $idsReativar = [];
        if ($mva->mva_tma_id_origem) {
            $idsReativar[] = $mva->mva_tma_id_origem;
        }
        foreach ((array) ($mva->mva_tmas_extras ?? []) as $extra) {
            if (! empty($extra['tma_id'])) {
                $idsReativar[] = $extra['tma_id'];
            }
        }
        if (! $idsReativar) {
            return;
        }

        // Quantos alunos retornam por turma.
        $retornando = [];
        foreach (Matricula::whereIn('tma_id', $idsReativar)->get(['tma_id', 'tma_tur_id']) as $m) {
            if ($m->tma_tur_id) {
                $retornando[$m->tma_tur_id] = ($retornando[$m->tma_tur_id] ?? 0) + 1;
            }
        }
        if (! $retornando) {
            return;
        }

        $turmas = Turma::whereIn('tur_id', array_keys($retornando))->get()->keyBy('tur_id');

        $erros = [];
        foreach ($retornando as $turId => $qtd) {
            $t = $turmas[$turId] ?? null;
            if (! $t || $t->tur_capacidade === null) {
                continue;
            }
            $limite = (int) $t->tur_capacidade + (int) ($t->tur_qt_expansao ?? 0);
            $atual  = Matricula::where('tma_tur_id', $turId)
                ->where('tma_situacao', Matricula::SITUACAO_ATIVA)
                ->count();
            $final = $atual + $qtd;
            if ($final > $limite) {
                $erros[] = "Turma {$t->tur_nome} ficaria com {$final}/{$limite} (sem vaga).";
            }
        }

        if ($erros) {
            throw ValidationException::withMessages([
                'vaga' => 'Não é possível desfazer: ' . implode(' ', array_unique($erros)),
            ]);
        }
    }
}
