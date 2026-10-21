<?php

namespace App\Support\Diario;

use App\Models\Diario\InstrumentoAvaliativo;
use App\Models\Parametro\ParametrizacaoAvaliacao;
use Illuminate\Support\Collection;

/**
 * Regras da parametrização de avaliações (Diário › Cadastro › Parametrização de
 * avaliações), num só lugar para o diário web e a API v1.
 *
 * Unidade SEM parametrização → nada muda: todos os instrumentos ativos, valor livre.
 * Unidade COM parametrização →
 *   · só os instrumentos parametrizados podem ser usados em avaliação NOVA (ou ao
 *     trocar o instrumento) — avaliação antiga com outro instrumento segue editável;
 *   · o valor vem preenchido com o parametrizado;
 *   · bloqueada: o valor é sempre o parametrizado (admin pode alterar).
 */
class RegrasParametrizacaoAvaliacao
{
    /** @return array{bloqueado: bool, itens: array<int, float>}|null */
    public static function daUnidade(int $uniId): ?array
    {
        $pav = ParametrizacaoAvaliacao::query()
            ->where('pav_uni_id', $uniId)
            ->with('itens:pai_id,pai_pav_id,pai_iav_id,pai_valor')
            ->first();

        if (! $pav || $pav->itens->isEmpty()) {
            return null;
        }

        return [
            'bloqueado' => (bool) $pav->pav_fl_bloqueado,
            'itens'     => $pav->itens->mapWithKeys(fn ($i) => [(int) $i->pai_iav_id => (float) $i->pai_valor])->all(),
        ];
    }

    /**
     * Instrumentos oferecidos no diário para a unidade (com `valor_param`) e o
     * resumo da parametrização para a tela.
     *
     * @return array{instrumentos: Collection, parametrizacao: array{ativa: bool, bloqueado: bool}}
     */
    public static function contexto(int $uniId, bool $isAdmin): array
    {
        $p = self::daUnidade($uniId);

        $instrumentos = InstrumentoAvaliativo::query()
            ->where('iav_fl_ativo', true)
            ->when($p, fn ($q) => $q->whereIn('iav_id', array_keys($p['itens'])))
            ->orderBy('iav_nome')
            ->get(['iav_id', 'iav_nome', 'iav_fl_recuperacao'])
            ->map(fn ($i) => [
                'iav_id'             => (int) $i->iav_id,
                'iav_nome'           => $i->iav_nome,
                'iav_fl_recuperacao' => (bool) $i->iav_fl_recuperacao,
                'valor_param'        => $p['itens'][(int) $i->iav_id] ?? null,
            ]);

        return [
            'instrumentos'   => $instrumentos,
            'parametrizacao' => [
                'ativa'     => $p !== null,
                // Já considera a exceção do admin: true = o valor fica travado PARA ESTE usuário.
                'bloqueado' => $p !== null && $p['bloqueado'] && ! $isAdmin,
            ],
        ];
    }

    /**
     * Valida o instrumento e devolve o valor a gravar, conforme a parametrização.
     * $iavAnterior: instrumento atual da avaliação (na edição), para não travar
     * avaliações criadas antes da parametrização.
     */
    public static function aplicar(int $uniId, int $iavId, $valorInput, bool $isAdmin, ?int $iavAnterior = null)
    {
        $p = self::daUnidade($uniId);
        if (! $p) {
            return $valorInput;
        }

        $parametrizado = array_key_exists($iavId, $p['itens']);
        if (! $parametrizado) {
            abort_unless(
                $iavAnterior !== null && $iavAnterior === $iavId,
                422,
                'Instrumento não parametrizado para esta unidade. Escolha um dos instrumentos definidos pela coordenação.'
            );

            return $valorInput;
        }

        $valorParam = $p['itens'][$iavId];

        if ($p['bloqueado'] && ! $isAdmin) {
            return $valorParam;
        }

        return ($valorInput === null || $valorInput === '') ? $valorParam : $valorInput;
    }
}
