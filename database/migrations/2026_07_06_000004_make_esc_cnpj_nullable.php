<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escola municipal pública em geral não tem CNPJ no censo, e várias colidiriam
 * no UNIQUE. Torna esc_cnpj nullable e remove o unique.
 * Ver docs/regras/importacao_censo.md (decisão de import).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_escola', function (Blueprint $t) {
            $t->dropUnique(['esc_cnpj']);
        });

        Schema::table('edu_escola', function (Blueprint $t) {
            $t->char('esc_cnpj', 14)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('edu_escola', function (Blueprint $t) {
            $t->char('esc_cnpj', 14)->nullable(false)->change();
        });

        Schema::table('edu_escola', function (Blueprint $t) {
            $t->unique('esc_cnpj');
        });
    }
};
