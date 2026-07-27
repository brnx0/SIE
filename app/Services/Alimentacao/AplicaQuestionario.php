<?php

namespace App\Services\Alimentacao;

use App\Models\Alimentacao\Pergunta;
use App\Models\Alimentacao\Visita;
use App\Models\Alimentacao\VisitaResposta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aplicação do questionário na visita e o ateste.
 */
class AplicaQuestionario
{
    /**
     * Monta as respostas em branco a partir das perguntas ATIVAS do questionário,
     * congelando texto e tipo.
     *
     * O congelamento é o que permite corrigir a redação de uma pergunta (ou
     * inativá-la) sem reescrever visitas antigas.
     */
    public function montarRespostas(Visita $visita): void
    {
        if (! $visita->vis_tpq_id) {
            throw ValidationException::withMessages([
                'vis_tpq_id' => 'Esta visita não tem questionário definido.',
            ]);
        }

        $perguntas = Pergunta::where('prg_tpq_id', $visita->vis_tpq_id)
            ->ativas()
            ->orderBy('prg_ordem')
            ->orderBy('prg_id')
            ->get();

        if ($perguntas->isEmpty()) {
            throw ValidationException::withMessages([
                'vis_tpq_id' => 'O questionário escolhido não tem perguntas ativas. Cadastre as perguntas antes de aplicar.',
            ]);
        }

        DB::transaction(function () use ($visita, $perguntas) {
            foreach ($perguntas as $pergunta) {
                // firstOrCreate: remontar não pode apagar o que já foi respondido.
                VisitaResposta::firstOrCreate(
                    ['vre_vis_id' => $visita->vis_id, 'vre_prg_id' => $pergunta->prg_id],
                    [
                        'vre_pergunta_texto' => $pergunta->prg_texto,
                        'vre_tipo_resposta'  => $pergunta->prg_tipo_resposta,
                    ],
                );
            }
        });
    }

    /**
     * Marca a visita como realizada. Exige que as perguntas obrigatórias tenham
     * resposta — questionário pela metade não é visita feita.
     */
    public function registrarRealizacao(Visita $visita, string $dtRealizada): Visita
    {
        return DB::transaction(function () use ($visita, $dtRealizada) {
            $visita = Visita::with('respostas.pergunta')->lockForUpdate()->findOrFail($visita->vis_id);

            if (! $visita->editavel()) {
                throw ValidationException::withMessages([
                    'situacao' => "Esta visita está {$visita->vis_situacao} e não pode ser alterada.",
                ]);
            }

            $pendentes = $visita->respostas->filter(
                fn ($r) => $r->pergunta?->prg_fl_obrigatoria && ! $r->respondida(),
            );

            if ($pendentes->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'situacao' => 'Há '.$pendentes->count().' pergunta(s) obrigatória(s) sem resposta.'
                        .' Responda todas antes de marcar a visita como realizada.',
                ]);
            }

            $visita->update([
                'vis_situacao'     => Visita::SITUACAO_REALIZADA,
                'vis_dt_realizada' => $dtRealizada,
            ]);

            return $visita;
        });
    }

    /**
     * Ateste da visita.
     *
     * Quem atesta não pode ser quem realizou: o ateste é conferência de terceiro,
     * e o mesmo servidor validando o próprio trabalho esvazia o controle.
     */
    public function atestar(Visita $visita, int $funAtesteId, string $dtAteste, ?string $parecer): Visita
    {
        return DB::transaction(function () use ($visita, $funAtesteId, $dtAteste, $parecer) {
            $visita = Visita::lockForUpdate()->findOrFail($visita->vis_id);

            if ($visita->atestada()) {
                throw ValidationException::withMessages([
                    'vis_fun_ateste_id' => 'Esta visita já foi atestada.',
                ]);
            }

            if (! $visita->podeAtestar()) {
                throw ValidationException::withMessages([
                    'vis_fun_ateste_id' => "Só visita REALIZADA pode ser atestada — esta está {$visita->vis_situacao}.",
                ]);
            }

            if ($visita->vis_fun_id && $visita->vis_fun_id === $funAtesteId) {
                throw ValidationException::withMessages([
                    'vis_fun_ateste_id' => 'Quem realizou a visita não pode atestá-la. O ateste é conferência de outra pessoa.',
                ]);
            }

            if ($dtAteste < $visita->vis_dt_realizada?->toDateString()) {
                throw ValidationException::withMessages([
                    'vis_dt_ateste' => 'O ateste não pode ser anterior à realização da visita ('
                        .$visita->vis_dt_realizada->format('d/m/Y').').',
                ]);
            }

            $visita->update([
                'vis_situacao'      => Visita::SITUACAO_ATESTADA,
                'vis_fun_ateste_id' => $funAtesteId,
                'vis_dt_ateste'     => $dtAteste,
                'vis_parecer'       => $parecer,
            ]);

            return $visita;
        });
    }

    /** Cancelamento exige motivo — visita que some sem explicação não se audita. */
    public function cancelar(Visita $visita, string $motivo): Visita
    {
        return DB::transaction(function () use ($visita, $motivo) {
            $visita = Visita::lockForUpdate()->findOrFail($visita->vis_id);

            if ($visita->atestada()) {
                throw ValidationException::withMessages([
                    'vis_motivo_cancelamento' => 'Visita atestada não pode ser cancelada.',
                ]);
            }

            $visita->update([
                'vis_situacao'            => Visita::SITUACAO_CANCELADA,
                'vis_motivo_cancelamento' => $motivo,
            ]);

            return $visita;
        });
    }
}
