<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro de Curso de Formação Superior (ARD 8):
 *  - cfs_cd_area: código INEP da área detalhada (4 dígitos, Cine Brasil).
 *    O código do curso (8 caracteres, ex.: 0111C012) começa pelo código da área.
 *  - cfs_ngr_id: nível de graduação acadêmica (FK edu_nivel_graduacao).
 *  - FK da escolaridade do funcionário passa de NULL ON DELETE para RESTRICT,
 *    para que a exclusão de um curso vinculado não apague o vínculo em silêncio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_curso_formacao_superior', function (Blueprint $t) {
            $t->string('cfs_cd_area', 4)->nullable()->after('cfs_codigo');
            $t->unsignedBigInteger('cfs_ngr_id')->nullable()->after('cfs_grau');

            $t->foreign('cfs_ngr_id')
                ->references('ngr_id')
                ->on('edu_nivel_graduacao')
                ->restrictOnDelete();

            $t->index('cfs_cd_area');
        });

        // Backfill a partir dos dados INEP já semeados.
        DB::statement("UPDATE edu_curso_formacao_superior SET cfs_cd_area = SUBSTRING(cfs_codigo FROM 1 FOR 4) WHERE cfs_codigo ~ '^[0-9]{4}'");
        DB::statement('
            UPDATE edu_curso_formacao_superior c
               SET cfs_ngr_id = n.ngr_id
              FROM edu_nivel_graduacao n
             WHERE UPPER(n.ngr_nome) = UPPER(c.cfs_grau)
        ');

        $this->trocarFkEscolaridade('RESTRICT');
    }

    public function down(): void
    {
        $this->trocarFkEscolaridade('SET NULL');

        Schema::table('edu_curso_formacao_superior', function (Blueprint $t) {
            $t->dropForeign(['cfs_ngr_id']);
            $t->dropIndex(['cfs_cd_area']);
            $t->dropColumn(['cfs_cd_area', 'cfs_ngr_id']);
        });
    }

    private function trocarFkEscolaridade(string $acao): void
    {
        DB::statement("
            DO \$\$
            DECLARE r RECORD;
            BEGIN
                FOR r IN
                    SELECT conname FROM pg_constraint
                    WHERE conrelid = 'edu_funcionario_escolaridade'::regclass
                      AND contype = 'f'
                      AND confrelid = 'edu_curso_formacao_superior'::regclass
                LOOP
                    EXECUTE 'ALTER TABLE edu_funcionario_escolaridade DROP CONSTRAINT ' || quote_ident(r.conname);
                END LOOP;
            END\$\$;
        ");

        DB::statement("
            ALTER TABLE edu_funcionario_escolaridade
              ADD CONSTRAINT edu_funcionario_escolaridade_fes_cfs_id_foreign
              FOREIGN KEY (fes_cfs_id) REFERENCES edu_curso_formacao_superior (cfs_id)
              ON DELETE {$acao}
        ");
    }
};
