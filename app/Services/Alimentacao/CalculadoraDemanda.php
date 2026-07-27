<?php

namespace App\Services\Alimentacao;

use App\Models\Alimentacao\Cardapio;
use App\Models\Alimentacao\Demanda;
use App\Models\Alimentacao\DemandaItem;
use App\Models\Alimentacao\Item;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Calcula a demanda mensal de merenda de uma unidade.
 *
 * Para cada dia letivo do mês, olha o que o cardápio manda servir naquele dia da
 * semana e turno, pega a ficha técnica do prato e multiplica o per capita pelo
 * número de alunos daquele turno. Somando o mês inteiro, sai quanto de cada item
 * a escola precisa.
 *
 * O calendário é o real: feriados e recessos (`cfg_dia_nao_letivo`) saem da
 * conta, e sábado letivo (`cfg_sabado_letivo`) entra contando como o dia da
 * semana que ele repõe — que é a mesma convenção usada pelo diário.
 */
class CalculadoraDemanda
{
    public function __construct(private ConversorUnidade $conversor)
    {
    }

    /**
     * Recalcula a demanda a partir do cardápio vigente, substituindo os itens.
     * Ajuste manual do nutricionista é perdido no recálculo — por isso a tela
     * avisa antes.
     */
    public function calcular(Demanda $demanda): Demanda
    {
        return DB::transaction(function () use ($demanda) {
            if (! $demanda->emRascunho()) {
                throw ValidationException::withMessages([
                    'situacao' => 'Esta demanda está fechada e não pode ser recalculada. Reabra-a para alterar.',
                ]);
            }

            $inicio = CarbonImmutable::create($demanda->dmd_ano, $demanda->dmd_mes, 1);
            $fim    = $inicio->endOfMonth();

            $cardapio = $this->cardapioVigente($demanda, $inicio, $fim);
            $alunos   = $this->alunosPorTurno($demanda->dmd_esc_id, $demanda->dmd_anl_id);

            if ($alunos->isEmpty()) {
                throw ValidationException::withMessages([
                    'situacao' => 'Esta escola não tem alunos matriculados no ano letivo escolhido — não há demanda a calcular.',
                ]);
            }

            $diasLetivos = $this->diasLetivosDoMes($demanda->dmd_esc_id, $demanda->dmd_anl_id, $inicio, $fim);

            if (empty($diasLetivos)) {
                throw ValidationException::withMessages([
                    'situacao' => 'Não há dias letivos neste mês pelo calendário do ano letivo.',
                ]);
            }

            $totais = $this->somarPorItem($cardapio, $diasLetivos, $alunos);

            // Substitui os itens: o recálculo é a nova verdade do rascunho.
            $demanda->itens()->delete();

            foreach ($totais as $itmId => $qtd) {
                $item = Item::find($itmId);

                DemandaItem::create([
                    'dmi_dmd_id'        => $demanda->dmd_id,
                    'dmi_itm_id'        => $itmId,
                    'dmi_umd_id'        => $item?->itm_umd_id,
                    'dmi_qtd_calculada' => round($qtd, 4),
                ]);
            }

            $demanda->update([
                'dmd_crd_id'       => $cardapio->crd_id,
                'dmd_dias_letivos' => count($diasLetivos),
            ]);

            return $demanda->fresh(['itens']);
        });
    }

    /**
     * Cardápio que a escola segue no período: o próprio dela tem precedência
     * sobre o padrão da rede.
     */
    private function cardapioVigente(Demanda $demanda, CarbonImmutable $inicio, CarbonImmutable $fim): Cardapio
    {
        $cardapio = Cardapio::query()
            ->vigenteParaEscola($demanda->dmd_esc_id, $inicio->toDateString())
            ->where('crd_anl_id', $demanda->dmd_anl_id)
            ->with('dias.prato.itens')
            ->first();

        if (! $cardapio) {
            throw ValidationException::withMessages([
                'situacao' => 'Não há cardápio ativo para esta escola em '
                    .$inicio->format('m/Y').'. Cadastre o cardápio (da escola ou o padrão da rede) antes de calcular a demanda.',
            ]);
        }

        if ($cardapio->dias->isEmpty()) {
            throw ValidationException::withMessages([
                'situacao' => "O cardápio \"{$cardapio->crd_nome}\" não tem a grade montada — sem pratos por dia não há o que calcular.",
            ]);
        }

        return $cardapio;
    }

    /**
     * Dias letivos do mês, por data.
     *
     * @return array<string, int> data => dia da semana (1=seg .. 5=sex)
     */
    private function diasLetivosDoMes(int $escId, int $anlId, CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $naoLetivos = $this->datasNaoLetivas($anlId, $inicio, $fim);
        $sabados    = $this->sabadosLetivos($escId, $anlId, $inicio, $fim);

        $dias = [];

        for ($dia = $inicio; $dia->lte($fim); $dia = $dia->addDay()) {
            $data = $dia->toDateString();

            if (isset($naoLetivos[$data])) {
                continue;
            }

            // Sábado letivo conta como o dia da semana que ele repõe.
            if (isset($sabados[$data])) {
                $dias[$data] = $sabados[$data];
                continue;
            }

            $semana = $dia->dayOfWeekIso; // 1 = segunda ... 7 = domingo

            if ($semana >= 1 && $semana <= 5) {
                $dias[$data] = $semana;
            }
        }

        return $dias;
    }

    /** @return array<string, true> */
    private function datasNaoLetivas(int $anlId, CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $registros = DB::table('cfg_dia_nao_letivo')
            ->where('dnl_anl_id', $anlId)
            // O registro pode ser um intervalo (recesso): pega tudo que cruza o mês.
            ->where('dnl_dt_dia', '<=', $fim->toDateString())
            ->where(function ($q) use ($inicio) {
                $q->whereNull('dnl_dt_fim')->where('dnl_dt_dia', '>=', $inicio->toDateString())
                  ->orWhere('dnl_dt_fim', '>=', $inicio->toDateString());
            })
            ->get(['dnl_dt_dia', 'dnl_dt_fim']);

        $datas = [];

        foreach ($registros as $r) {
            $de   = CarbonImmutable::parse($r->dnl_dt_dia);
            $ate  = $r->dnl_dt_fim ? CarbonImmutable::parse($r->dnl_dt_fim) : $de;

            for ($d = $de; $d->lte($ate); $d = $d->addDay()) {
                $datas[$d->toDateString()] = true;
            }
        }

        return $datas;
    }

    /**
     * Sábados letivos da escola. A exceção (`cfg_sabado_letivo_excecao`) tira o
     * sábado de uma escola específica — sem respeitar isso, a demanda contaria
     * um dia que aquela unidade não tem.
     *
     * @return array<string, int> data => dia da semana que o sábado repõe
     */
    private function sabadosLetivos(int $escId, int $anlId, CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $temExcecao = \Illuminate\Support\Facades\Schema::hasTable('cfg_sabado_letivo_excecao');

        return DB::table('cfg_sabado_letivo as s')
            ->where('s.sbl_anl_id', $anlId)
            ->whereBetween('s.sbl_dt_sabado', [$inicio->toDateString(), $fim->toDateString()])
            ->when($temExcecao, fn ($q) => $q->whereNotExists(function ($sub) use ($escId) {
                $sub->select(DB::raw(1))
                    ->from('cfg_sabado_letivo_excecao as e')
                    ->whereColumn('e.sle_sbl_id', 's.sbl_id')
                    ->where('e.sle_esc_id', $escId);
            }))
            ->get(['s.sbl_dt_sabado', 's.sbl_dia_semana'])
            ->mapWithKeys(fn ($s) => [
                CarbonImmutable::parse($s->sbl_dt_sabado)->toDateString() => (int) $s->sbl_dia_semana,
            ])
            ->all();
    }

    /**
     * Alunos ativos por turno na escola. Turma INTEGRAL conta no próprio turno —
     * o cardápio tem slots de INTEGRAL.
     *
     * @return Collection<string, int> turno => alunos
     */
    private function alunosPorTurno(int $escId, int $anlId): Collection
    {
        return DB::table('edu_turma_aluno as tma')
            ->join('edu_turma as t', 't.tur_id', '=', 'tma.tma_tur_id')
            ->where('t.tur_esc_id', $escId)
            ->where('t.tur_anl_id', $anlId)
            ->whereNull('t.tur_deleted_at')
            ->whereNull('tma.tma_deleted_at')
            // Aluno que saiu não come mais na escola.
            ->whereNull('tma.tma_tas_cod_saida')
            ->groupBy('t.tur_turno')
            ->select('t.tur_turno', DB::raw('COUNT(DISTINCT tma.tma_aln_id) as alunos'))
            ->get()
            ->mapWithKeys(fn ($r) => [$r->tur_turno => (int) $r->alunos]);
    }

    /**
     * Percorre os dias letivos e soma, por item, o que o cardápio consome.
     *
     * @param  array<string, int>  $diasLetivos
     * @return array<int, float> itm_id => quantidade na unidade base
     */
    private function somarPorItem(Cardapio $cardapio, array $diasLetivos, Collection $alunos): array
    {
        $semanas = $cardapio->dias->pluck('cdi_semana')->unique()->sort()->values();
        $totais  = [];

        foreach ($diasLetivos as $data => $diaSemana) {
            $semana = $this->semanaDoRodizio($cardapio, $data, $semanas);

            $slots = $cardapio->dias->filter(
                fn ($d) => $d->cdi_dia_semana === $diaSemana && $d->cdi_semana === $semana,
            );

            foreach ($slots as $slot) {
                $qtdAlunos = $alunos->get($slot->cdi_turno, 0);

                if ($qtdAlunos <= 0 || ! $slot->prato) {
                    continue;
                }

                foreach ($slot->prato->itens as $fichaItem) {
                    $item = Item::find($fichaItem->pri_itm_id);

                    if (! $item) {
                        continue;
                    }

                    // O per capita pode estar em unidade diferente da base do item
                    // (200 G de um arroz controlado em KG): converte para somar.
                    $porAluno = $this->conversor->converter(
                        (float) $fichaItem->pri_qtd_per_capita,
                        $fichaItem->pri_umd_id,
                        $item->itm_umd_id,
                        $item->itm_id,
                    );

                    $totais[$item->itm_id] = ($totais[$item->itm_id] ?? 0) + ($porAluno * $qtdAlunos);
                }
            }
        }

        return $totais;
    }

    /**
     * Qual semana do rodízio cai numa data.
     *
     * Cardápio de semana fixa tem só a semana 1 e sempre cai nela. Com rodízio,
     * a semana avança a cada 7 dias contados do início da vigência e volta ao
     * começo do ciclo — é isso que faz "semana 1, 2, 1, 2..." ao longo do mês.
     */
    private function semanaDoRodizio(Cardapio $cardapio, string $data, Collection $semanas): int
    {
        if ($semanas->count() <= 1) {
            return $semanas->first() ?? 1;
        }

        $inicio   = CarbonImmutable::parse($cardapio->crd_dt_inicio)->startOfWeek();
        $dia      = CarbonImmutable::parse($data)->startOfWeek();
        $decorrid = (int) $inicio->diffInWeeks($dia);

        return $semanas[$decorrid % $semanas->count()];
    }
}
