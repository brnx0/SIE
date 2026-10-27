<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Termos (nomenclaturas) dos resultados da Avaliação Diagnóstica, para toda a rede.
 * O resultado gravado em edu_diario_diagnostico.dgn_opcao continua sendo a chave
 * fixa (autonomia, apoio...) — aqui só muda COMO ela é apresentada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_termo_diagnostico', function (Blueprint $table) {
            $table->id('tdg_id');
            $table->string('tdg_opcao', 20)->unique();
            $table->string('tdg_descricao', 60);
            $table->string('tdg_sigla', 5);
            $table->smallInteger('tdg_ordem')->default(0);
            $table->foreignId('tdg_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tdg_created_at')->nullable();
            $table->timestamp('tdg_updated_at')->nullable();
        });

        // Valores atuais (antes fixos no código) como ponto de partida.
        $agora = now();
        DB::table('cfg_termo_diagnostico')->insert([
            ['tdg_opcao' => 'autonomia',    'tdg_descricao' => 'Realiza com autonomia', 'tdg_sigla' => 'A',  'tdg_ordem' => 1, 'tdg_created_at' => $agora, 'tdg_updated_at' => $agora],
            ['tdg_opcao' => 'apoio',        'tdg_descricao' => 'Realiza com apoio',     'tdg_sigla' => 'Ap', 'tdg_ordem' => 2, 'tdg_created_at' => $agora, 'tdg_updated_at' => $agora],
            ['tdg_opcao' => 'nao_realiza',  'tdg_descricao' => 'Ainda não realiza',     'tdg_sigla' => 'NR', 'tdg_ordem' => 3, 'tdg_created_at' => $agora, 'tdg_updated_at' => $agora],
            ['tdg_opcao' => 'nao_trabalha', 'tdg_descricao' => 'Não trabalha',          'tdg_sigla' => 'NT', 'tdg_ordem' => 4, 'tdg_created_at' => $agora, 'tdg_updated_at' => $agora],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_termo_diagnostico');
    }
};
