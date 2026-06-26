<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Interesse de renovação vira TRISTATE:
 *   NULL  = nunca preenchido (sem informação)
 *   true  = deseja renovar
 *   false = não deseja renovar
 * Reseta os valores existentes que eram o default `false` para NULL (não-informado),
 * preservando os `true` já marcados.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE edu_turma_aluno ALTER COLUMN tma_fl_renovado DROP DEFAULT');
        DB::statement('ALTER TABLE edu_turma_aluno ALTER COLUMN tma_fl_renovado DROP NOT NULL');
        DB::statement('ALTER TABLE edu_turma_aluno ALTER COLUMN tma_fl_renovado SET DEFAULT NULL');
        DB::statement('UPDATE edu_turma_aluno SET tma_fl_renovado = NULL WHERE tma_fl_renovado = false');
    }

    public function down(): void
    {
        DB::statement('UPDATE edu_turma_aluno SET tma_fl_renovado = false WHERE tma_fl_renovado IS NULL');
        DB::statement('ALTER TABLE edu_turma_aluno ALTER COLUMN tma_fl_renovado SET DEFAULT false');
        DB::statement('ALTER TABLE edu_turma_aluno ALTER COLUMN tma_fl_renovado SET NOT NULL');
    }
};
