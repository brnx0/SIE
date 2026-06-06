<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $renames = [
        'mat_id'                      => 'tma_id',
        'mat_aln_id'                  => 'tma_aln_id',
        'mat_tur_id'                  => 'tma_tur_id',
        'mat_anl_id'                  => 'tma_anl_id',

        'mat_situacao'                => 'tma_situacao',
        'mat_dt_matricula'            => 'tma_dt_matricula',
        'mat_dt_saida'                => 'tma_dt_saida',
        'mat_obs'                     => 'tma_obs',
        'mat_created_at'              => 'tma_created_at',
        'mat_updated_at'              => 'tma_updated_at',
        'mat_deleted_at'              => 'tma_deleted_at',
        'mat_fl_trouxe_transferencia' => 'tma_fl_trouxe_transferencia',
        'mat_fl_trouxe_rg'            => 'tma_fl_trouxe_rg',
        'mat_fl_trouxe_reg_nascimento'=> 'tma_fl_trouxe_reg_nascimento',
        'mat_fl_bolsa_familia'        => 'tma_fl_bolsa_familia',
        'mat_fl_recebe_merenda'       => 'tma_fl_recebe_merenda',
        'mat_fl_usa_transporte'       => 'tma_fl_usa_transporte',
        'mat_fl_usa_biblioteca'       => 'tma_fl_usa_biblioteca',
        'mat_fl_indigena'             => 'tma_fl_indigena',
        'mat_fl_creche'               => 'tma_fl_creche',
        'mat_created_by_id'           => 'tma_created_by_id',
    ];

    public function up(): void
    {
        foreach ($this->renames as $old => $new) {
            DB::statement("ALTER TABLE edu_turma_aluno RENAME COLUMN {$old} TO {$new}");
        }

        // Recriar índice parcial que referencia colunas renomeadas
        DB::statement('DROP INDEX IF EXISTS turma_aluno_ativa_por_ano');
        DB::statement("
            CREATE UNIQUE INDEX turma_aluno_ativa_por_ano
            ON edu_turma_aluno (tma_aln_id, tma_anl_id)
            WHERE tma_situacao = 'ATIVA' AND tma_deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS turma_aluno_ativa_por_ano');

        foreach (array_flip($this->renames) as $old => $new) {
            DB::statement("ALTER TABLE edu_turma_aluno RENAME COLUMN {$old} TO {$new}");
        }

        DB::statement("
            CREATE UNIQUE INDEX turma_aluno_ativa_por_ano
            ON edu_turma_aluno (mat_aln_id, mat_anl_id)
            WHERE mat_situacao = 'ATIVA' AND mat_deleted_at IS NULL
        ");
    }
};
