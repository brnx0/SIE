<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cfg_atendimento_aee')) {
            return;
        }

        Schema::create('cfg_atendimento_aee', function (Blueprint $t) {
            $t->smallIncrements('ate_id');
            $t->string('ate_descricao', 200);
            $t->boolean('ate_fl_ativo')->default(true);
            $t->timestamp('ate_created_at')->nullable();
            $t->timestamp('ate_updated_at')->nullable();
        });

        // IDs preservados do legado p/ não quebrar referências históricas.
        $now = now();
        DB::table('cfg_atendimento_aee')->insert([
            ['ate_id' => 1,  'ate_descricao' => 'Ensino do Sistema Braile',                                       'ate_fl_ativo' => true, 'ate_created_at' => $now, 'ate_updated_at' => $now],
            ['ate_id' => 3,  'ate_descricao' => 'Ensino do uso de recursos ópticos e não ópticos',                'ate_fl_ativo' => true, 'ate_created_at' => $now, 'ate_updated_at' => $now],
            ['ate_id' => 4,  'ate_descricao' => 'Estratégias para o desenvolvimento de processos mentais',       'ate_fl_ativo' => true, 'ate_created_at' => $now, 'ate_updated_at' => $now],
            ['ate_id' => 5,  'ate_descricao' => 'Técnicas de orientação e mobilidade',                            'ate_fl_ativo' => true, 'ate_created_at' => $now, 'ate_updated_at' => $now],
            ['ate_id' => 6,  'ate_descricao' => 'Ensino da Língua Brasileira de Sinais - Libras',                'ate_fl_ativo' => true, 'ate_created_at' => $now, 'ate_updated_at' => $now],
            ['ate_id' => 7,  'ate_descricao' => 'Ensino de uso da Comunicação Alternativa e Aumentativa CAA',    'ate_fl_ativo' => true, 'ate_created_at' => $now, 'ate_updated_at' => $now],
            ['ate_id' => 8,  'ate_descricao' => 'Estratégias para enriquecimento curricular',                     'ate_fl_ativo' => true, 'ate_created_at' => $now, 'ate_updated_at' => $now],
            ['ate_id' => 9,  'ate_descricao' => 'Ensino do uso do Soroban',                                       'ate_fl_ativo' => true, 'ate_created_at' => $now, 'ate_updated_at' => $now],
            ['ate_id' => 10, 'ate_descricao' => 'Ensino da usabilidade e das funcionalidades da informática acessível', 'ate_fl_ativo' => true, 'ate_created_at' => $now, 'ate_updated_at' => $now],
            ['ate_id' => 11, 'ate_descricao' => 'Ensino da Língua Portuguesa na modalidade escrita',             'ate_fl_ativo' => true, 'ate_created_at' => $now, 'ate_updated_at' => $now],
            ['ate_id' => 12, 'ate_descricao' => 'Estratégias para autonomia no ambiente escolar',                 'ate_fl_ativo' => true, 'ate_created_at' => $now, 'ate_updated_at' => $now],
        ]);

        // Reseta a sequência p/ próximo insert continuar após o maior ID inserido.
        DB::statement("SELECT setval(pg_get_serial_sequence('cfg_atendimento_aee', 'ate_id'), (SELECT MAX(ate_id) FROM cfg_atendimento_aee))");
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_atendimento_aee');
    }
};
