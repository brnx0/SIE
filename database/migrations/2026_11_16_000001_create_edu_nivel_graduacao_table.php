<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro de Nível de Graduação Acadêmica (grau acadêmico INEP).
 * Alimenta a lista suspensa do Cadastro de Curso de Formação Superior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_nivel_graduacao', function (Blueprint $t) {
            $t->bigIncrements('ngr_id');
            $t->string('ngr_nome', 60);
            $t->boolean('ngr_fl_ativo')->default(true);

            $t->timestamp('ngr_created_at')->nullable();
            $t->timestamp('ngr_updated_at')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX edu_nivel_graduacao_nome_unique ON edu_nivel_graduacao (UPPER(ngr_nome))');

        // Graus acadêmicos da tabela auxiliar do Censo (INEP).
        $now = now();
        DB::table('edu_nivel_graduacao')->insert(array_map(fn ($nome) => [
            'ngr_nome'       => $nome,
            'ngr_fl_ativo'   => true,
            'ngr_created_at' => $now,
            'ngr_updated_at' => $now,
        ], ['Bacharelado', 'Licenciatura', 'Tecnológico', 'Sequencial']));
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_nivel_graduacao');
    }
};
