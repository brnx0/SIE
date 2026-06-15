<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Substitui o "autor" dos planos (aula e AEE) de fun_id (edu_funcionario)
 * para o id do usuário (users.id), espelhando dpa_validado_por_user_id.
 *
 * dpa_fun_id -> dpa_user_id  /  dae_fun_id -> dae_user_id
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->migrarTabela(
            tabela: 'edu_diario_plano_aula',
            colAntiga: 'dpa_fun_id',
            colNova: 'dpa_user_id',
            idxFun: 'edu_diario_plano_aula_fun_idx',
            idxUser: 'edu_diario_plano_aula_user_idx',
            uniqueIdx: 'edu_diario_plano_aula_unico',
            uniqueCols: 'dpa_user_id, dpa_tur_id, dpa_dis_id, dpa_dt_inicio, dpa_dt_fim',
            deletedCol: 'dpa_deleted_at',
        );

        $this->migrarTabela(
            tabela: 'edu_diario_plano_aee',
            colAntiga: 'dae_fun_id',
            colNova: 'dae_user_id',
            idxFun: 'edu_diario_plano_aee_fun_idx',
            idxUser: 'edu_diario_plano_aee_user_idx',
            uniqueIdx: 'edu_diario_plano_aee_unico',
            uniqueCols: 'dae_user_id, dae_tur_id, dae_dt_inicio, dae_dt_fim',
            deletedCol: 'dae_deleted_at',
        );
    }

    private function migrarTabela(
        string $tabela,
        string $colAntiga,
        string $colNova,
        string $idxFun,
        string $idxUser,
        string $uniqueIdx,
        string $uniqueCols,
        string $deletedCol,
    ): void {
        // 1. Dropa FK p/ edu_funcionario (nome pode variar entre ambientes)
        DB::statement("
            DO \$\$
            DECLARE r RECORD;
            BEGIN
                FOR r IN
                    SELECT conname FROM pg_constraint
                    WHERE conrelid = '{$tabela}'::regclass
                      AND contype = 'f'
                      AND conname ILIKE '%{$colAntiga}%'
                LOOP
                    EXECUTE 'ALTER TABLE {$tabela} DROP CONSTRAINT ' || quote_ident(r.conname);
                END LOOP;
            END\$\$;
        ");

        // 2. Dropa índices (único e simples) que referenciam a coluna antiga
        DB::statement("DROP INDEX IF EXISTS {$uniqueIdx}");
        DB::statement("DROP INDEX IF EXISTS {$idxFun}");

        // 3. Renomeia a coluna
        DB::statement("ALTER TABLE {$tabela} RENAME COLUMN {$colAntiga} TO {$colNova}");

        // 4. Converte os valores: fun_id -> users.id (via users.fun_id)
        DB::statement("
            UPDATE {$tabela} AS p
            SET {$colNova} = u.id
            FROM users u
            WHERE u.fun_id = p.{$colNova}
        ");

        // 5. Recria índice simples + única parcial com a nova coluna
        DB::statement("CREATE INDEX {$idxUser} ON {$tabela} ({$colNova})");
        DB::statement("
            CREATE UNIQUE INDEX {$uniqueIdx} ON {$tabela}
            ({$uniqueCols})
            WHERE {$deletedCol} IS NULL
        ");

        // 6. Recria a FK apontando p/ users.id
        DB::statement("
            ALTER TABLE {$tabela}
            ADD CONSTRAINT {$tabela}_{$colNova}_foreign
            FOREIGN KEY ({$colNova}) REFERENCES users (id) ON DELETE RESTRICT
        ");
    }

    public function down(): void
    {
        $this->reverterTabela(
            tabela: 'edu_diario_plano_aula',
            colNova: 'dpa_user_id',
            colAntiga: 'dpa_fun_id',
            idxUser: 'edu_diario_plano_aula_user_idx',
            idxFun: 'edu_diario_plano_aula_fun_idx',
            uniqueIdx: 'edu_diario_plano_aula_unico',
            uniqueCols: 'dpa_fun_id, dpa_tur_id, dpa_dis_id, dpa_dt_inicio, dpa_dt_fim',
            deletedCol: 'dpa_deleted_at',
        );

        $this->reverterTabela(
            tabela: 'edu_diario_plano_aee',
            colNova: 'dae_user_id',
            colAntiga: 'dae_fun_id',
            idxUser: 'edu_diario_plano_aee_user_idx',
            idxFun: 'edu_diario_plano_aee_fun_idx',
            uniqueIdx: 'edu_diario_plano_aee_unico',
            uniqueCols: 'dae_fun_id, dae_tur_id, dae_dt_inicio, dae_dt_fim',
            deletedCol: 'dae_deleted_at',
        );
    }

    private function reverterTabela(
        string $tabela,
        string $colNova,
        string $colAntiga,
        string $idxUser,
        string $idxFun,
        string $uniqueIdx,
        string $uniqueCols,
        string $deletedCol,
    ): void {
        DB::statement("
            DO \$\$
            DECLARE r RECORD;
            BEGIN
                FOR r IN
                    SELECT conname FROM pg_constraint
                    WHERE conrelid = '{$tabela}'::regclass
                      AND contype = 'f'
                      AND conname ILIKE '%{$colNova}%'
                LOOP
                    EXECUTE 'ALTER TABLE {$tabela} DROP CONSTRAINT ' || quote_ident(r.conname);
                END LOOP;
            END\$\$;
        ");

        DB::statement("DROP INDEX IF EXISTS {$uniqueIdx}");
        DB::statement("DROP INDEX IF EXISTS {$idxUser}");

        DB::statement("ALTER TABLE {$tabela} RENAME COLUMN {$colNova} TO {$colAntiga}");

        DB::statement("
            UPDATE {$tabela} AS p
            SET {$colAntiga} = u.fun_id
            FROM users u
            WHERE u.id = p.{$colAntiga} AND u.fun_id IS NOT NULL
        ");

        DB::statement("CREATE INDEX {$idxFun} ON {$tabela} ({$colAntiga})");
        DB::statement("
            CREATE UNIQUE INDEX {$uniqueIdx} ON {$tabela}
            ({$uniqueCols})
            WHERE {$deletedCol} IS NULL
        ");

        DB::statement("
            ALTER TABLE {$tabela}
            ADD CONSTRAINT {$tabela}_{$colAntiga}_foreign
            FOREIGN KEY ({$colAntiga}) REFERENCES edu_funcionario (fun_id) ON DELETE RESTRICT
        ");
    }
};
