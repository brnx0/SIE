<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoMovimentacaoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['tmv_cod' => 1,  'tmv_descricao' => 'Evasão',                                   'tmv_tas_cod_entrada' => null, 'tmv_tas_cod_saida' => 4,    'tmv_fl_ativo' => false],
            ['tmv_cod' => 2,  'tmv_descricao' => 'Desistência',                              'tmv_tas_cod_entrada' => null, 'tmv_tas_cod_saida' => 2,    'tmv_fl_ativo' => false],
            ['tmv_cod' => 3,  'tmv_descricao' => 'Transferência',                            'tmv_tas_cod_entrada' => null, 'tmv_tas_cod_saida' => 3,    'tmv_fl_ativo' => true],
            ['tmv_cod' => 4,  'tmv_descricao' => 'Transferência - Dentro da Rede Municipal', 'tmv_tas_cod_entrada' => 18,   'tmv_tas_cod_saida' => 19,   'tmv_fl_ativo' => false],
            ['tmv_cod' => 5,  'tmv_descricao' => 'Remanejamento',                            'tmv_tas_cod_entrada' => 16,   'tmv_tas_cod_saida' => 15,   'tmv_fl_ativo' => true],
            ['tmv_cod' => 6,  'tmv_descricao' => 'Óbito',                                    'tmv_tas_cod_entrada' => null, 'tmv_tas_cod_saida' => 5,    'tmv_fl_ativo' => true],
            ['tmv_cod' => 7,  'tmv_descricao' => 'Reclassificação',                          'tmv_tas_cod_entrada' => 14,   'tmv_tas_cod_saida' => 13,   'tmv_fl_ativo' => true],
            ['tmv_cod' => 8,  'tmv_descricao' => 'Matrícula Indevida',                       'tmv_tas_cod_entrada' => null, 'tmv_tas_cod_saida' => 20,   'tmv_fl_ativo' => false],
            ['tmv_cod' => 10, 'tmv_descricao' => 'Matrícula Indeferida',                     'tmv_tas_cod_entrada' => null, 'tmv_tas_cod_saida' => 30,   'tmv_fl_ativo' => false],
            ['tmv_cod' => 11, 'tmv_descricao' => 'Matrícula Cancelada',                      'tmv_tas_cod_entrada' => null, 'tmv_tas_cod_saida' => 34,   'tmv_fl_ativo' => true],
            ['tmv_cod' => 12, 'tmv_descricao' => 'Trancamento de Matrícula',                 'tmv_tas_cod_entrada' => null, 'tmv_tas_cod_saida' => 36,   'tmv_fl_ativo' => false],
            ['tmv_cod' => 13, 'tmv_descricao' => 'Transferência - Pandemia',                 'tmv_tas_cod_entrada' => null, 'tmv_tas_cod_saida' => null, 'tmv_fl_ativo' => false],
            ['tmv_cod' => 14, 'tmv_descricao' => 'Classificação',                            'tmv_tas_cod_entrada' => null, 'tmv_tas_cod_saida' => null, 'tmv_fl_ativo' => false],
            ['tmv_cod' => 15, 'tmv_descricao' => 'Deixou de Frequentar',                     'tmv_tas_cod_entrada' => null, 'tmv_tas_cod_saida' => 39,   'tmv_fl_ativo' => true],
        ];

        $now = now();

        foreach ($tipos as $t) {
            DB::table('edu_tipo_movimentacao')->updateOrInsert(
                ['tmv_cod' => $t['tmv_cod']],
                array_merge($t, [
                    'tmv_created_at' => $now,
                    'tmv_updated_at' => $now,
                ]),
            );
        }
    }
}
