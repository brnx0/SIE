<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $flags = [
        'fl_trouxe_transferencia',
        'fl_trouxe_rg',
        'fl_trouxe_reg_nascimento',
        'fl_bolsa_familia',
        'fl_recebe_merenda',
        'fl_usa_transporte',
        'fl_usa_biblioteca',
        'fl_indigena',
        'fl_creche',
    ];

    public function up(): void
    {
        // 1. Adiciona colunas em edu_aluno
        Schema::table('edu_aluno', function (Blueprint $table) {
            foreach ($this->flags as $f) {
                $table->boolean("aln_{$f}")->default(false);
            }
        });

        // 2. Migra dados — pega valor mais recente por aluno
        foreach ($this->flags as $f) {
            DB::statement("
                UPDATE edu_aluno a
                SET aln_{$f} = sub.tma_{$f}
                FROM (
                    SELECT DISTINCT ON (tma_aln_id) tma_aln_id, tma_{$f}
                    FROM edu_turma_aluno
                    WHERE tma_deleted_at IS NULL
                    ORDER BY tma_aln_id, tma_dt_matricula DESC NULLS LAST, tma_id DESC
                ) sub
                WHERE a.aln_id = sub.tma_aln_id
            ");
        }

        // 3. Remove colunas de edu_turma_aluno
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            foreach ($this->flags as $f) {
                $table->dropColumn("tma_{$f}");
            }
        });
    }

    public function down(): void
    {
        Schema::table('edu_turma_aluno', function (Blueprint $table) {
            foreach ($this->flags as $f) {
                $table->boolean("tma_{$f}")->default(false);
            }
        });

        Schema::table('edu_aluno', function (Blueprint $table) {
            foreach ($this->flags as $f) {
                $table->dropColumn("aln_{$f}");
            }
        });
    }
};
