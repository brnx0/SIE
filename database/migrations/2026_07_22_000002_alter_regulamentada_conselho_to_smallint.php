<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Campo 49 do Reg 00 (Educacenso) exige 3 valores: 0-Não, 1-Sim, 2-Em tramitação.
     * A coluna era boolean (só cobria 0/1). Converte para smallint mantendo os dados
     * existentes (false -> 0, true -> 1).
     */
    public function up(): void
    {
        DB::statement('
            ALTER TABLE edu_escola
            ALTER COLUMN esc_regulamentada_conselho TYPE SMALLINT
            USING (CASE WHEN esc_regulamentada_conselho THEN 1 ELSE 0 END)
        ');
    }

    public function down(): void
    {
        // Valor 2 (Em tramitação) não existe em boolean; colapsa para true.
        DB::statement('
            ALTER TABLE edu_escola
            ALTER COLUMN esc_regulamentada_conselho TYPE BOOLEAN
            USING (esc_regulamentada_conselho <> 0)
        ');
    }
};
