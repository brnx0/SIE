<?php

namespace Database\Seeders;

use App\Models\Parametro\ParametroEntidade;
use Illuminate\Database\Seeder;

class ParametroEntidadeSeeder extends Seeder
{
    public function run(): void
    {
        ParametroEntidade::updateOrCreate(
            ['par_id' => 1],
            [
                'par_nome_entidade' => 'Secretaria Municipal de Educação',
                'par_msg_cab_secretaria' => 'Prefeitura Municipal',
                'par_msg_cab_estado' => 'Estado',
                'par_endereco' => '',
                'par_mun_id' => null,
                'par_logomarca' => null,
                'par_brasao' => null,
                'par_fl_nome_pessoa_caixa_alta' => true,
                'par_fl_nome_escola_caixa_alta' => true,
                'par_fl_alertar_homonimos' => true,
                'par_fl_alertar_acentos_nomes' => true,
            ],
        );
    }
}
