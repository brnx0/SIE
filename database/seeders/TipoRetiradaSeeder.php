<?php

namespace Database\Seeders;

use App\Models\Alimentacao\TipoRetirada;
use Illuminate\Database\Seeder;

/**
 * Motivos de retirada (= saída) do estoque da unidade.
 *
 * Ponto de partida editável pela tela Cadastros → Tipos de Retirada: o cliente
 * inativa o que não usa e cadastra o que faltar. Consumo de cardápio NÃO entra
 * aqui — a confirmação da preparação já baixa como CONSUMO.
 */
class TipoRetiradaSeeder extends Seeder
{
    private const TIPOS = [
        'VENCIMENTO',
        'AVARIA',
        'DESCARTE',
        'DEVOLUCAO AO ESTOQUE CENTRAL',
        'OUTROS',
    ];

    public function run(): void
    {
        foreach (self::TIPOS as $nome) {
            // firstOrCreate: rodar de novo não duplica nem revive o que o
            // cliente inativou.
            TipoRetirada::firstOrCreate(
                ['tpr_nome' => $nome],
                ['tpr_fl_ativo' => true],
            );
        }
    }
}
