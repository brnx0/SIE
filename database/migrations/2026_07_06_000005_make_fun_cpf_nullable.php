<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Funcionário vindo do censo pode não ter CPF (514 pessoas sem CPF no arquivo de
 * Barreiras). Torna fun_cpf nullable com índice único parcial (permite múltiplos
 * NULL), espelhando o tratamento de aln_cpf. Ver docs/regras/importacao_censo.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->char('fun_cpf', 11)->nullable()->change();
        });

        DB::statement('ALTER TABLE edu_funcionario DROP CONSTRAINT IF EXISTS edu_funcionario_fun_cpf_unique');
        DB::statement('DROP INDEX IF EXISTS edu_funcionario_fun_cpf_unique');
        DB::statement('CREATE UNIQUE INDEX edu_funcionario_fun_cpf_unique ON edu_funcionario (fun_cpf) WHERE fun_cpf IS NOT NULL AND fun_deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS edu_funcionario_fun_cpf_unique');
        Schema::table('edu_funcionario', function (Blueprint $t) {
            $t->char('fun_cpf', 11)->nullable(false)->change();
            $t->unique('fun_cpf', 'edu_funcionario_fun_cpf_unique');
        });
    }
};
