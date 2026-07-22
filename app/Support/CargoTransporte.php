<?php

namespace App\Support;

use App\Models\Funcionario\Cargo;

/**
 * Fonte única para os cargos que atuam no transporte escolar.
 *
 * Motorista e monitor não têm cadastro próprio: são funcionários da rede
 * (edu_funcionario) com lotação ativa em um destes cargos. Os lookups de
 * Api\Transporte\FuncionarioTransporteController filtram por estes IDs.
 */
final class CargoTransporte
{
    /** Cargo do condutor do veículo escolar. */
    public const MOTORISTA = 'Motorista';

    /** Cargo do acompanhante da rota. */
    public const MONITOR = 'Monitor(a) de Transporte';

    /** Papéis aceitos pelos lookups (papel => nome do cargo). */
    public const PAPEIS = [
        'motorista' => self::MOTORISTA,
        'monitor'   => self::MONITOR,
    ];

    /**
     * IDs dos cargos (ativos e inativos) correspondentes ao papel.
     *
     * Inclui inativos de propósito: um cargo pode ser desativado sem que os
     * funcionários já lotados nele deixem de figurar nas rotas existentes.
     *
     * @return array<int>
     */
    public static function ids(string $papel): array
    {
        $nome = self::PAPEIS[$papel] ?? null;

        if (! $nome) {
            return [];
        }

        return Cargo::query()
            ->where('crg_nome', $nome)
            ->pluck('crg_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
