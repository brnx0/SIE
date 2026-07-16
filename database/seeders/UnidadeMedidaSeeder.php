<?php

namespace Database\Seeders;

use App\Models\Alimentacao\ConversaoUnidade;
use App\Models\Alimentacao\UnidadeMedida;
use Illuminate\Database\Seeder;

class UnidadeMedidaSeeder extends Seeder
{
    /** [sigla, nome, fracionável] */
    private const UNIDADES = [
        ['KG', 'Quilograma', true],
        ['G', 'Grama', true],
        ['L', 'Litro', true],
        ['ML', 'Mililitro', true],
        ['UN', 'Unidade', false],
        ['DZ', 'Dúzia', false],
        ['CX', 'Caixa', false],
        ['FD', 'Fardo', false],
        ['PCT', 'Pacote', false],
        ['SC', 'Saco', false],
        ['LT', 'Lata', false],
        ['GF', 'Garrafa', false],
        ['BDJ', 'Bandeja', false],
        ['MC', 'Maço', false],
    ];

    /**
     * Conversões globais — só as que valem para qualquer item.
     * Embalagem (CX, FD, PCT...) varia por item e é cadastrada em Item → Conversões.
     *
     * [sigla origem, sigla destino, fator]
     */
    private const CONVERSOES = [
        ['KG', 'G', 1000],
        ['L', 'ML', 1000],
        ['DZ', 'UN', 12],
    ];

    public function run(): void
    {
        foreach (self::UNIDADES as [$sigla, $nome, $fracionavel]) {
            UnidadeMedida::query()->updateOrCreate(
                ['umd_sigla' => $sigla],
                ['umd_nome' => $nome, 'umd_fl_fracionavel' => $fracionavel, 'umd_fl_ativo' => true],
            );
        }

        $ids = UnidadeMedida::query()->pluck('umd_id', 'umd_sigla');

        foreach (self::CONVERSOES as [$origem, $destino, $fator]) {
            ConversaoUnidade::query()->updateOrCreate(
                [
                    'cvu_itm_id'         => null,
                    'cvu_umd_origem_id'  => $ids[$origem],
                    'cvu_umd_destino_id' => $ids[$destino],
                ],
                ['cvu_fator' => $fator, 'cvu_fl_ativo' => true],
            );
        }
    }
}
