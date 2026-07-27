<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código NCM do item (Nomenclatura Comum do Mercosul).
 *
 * Classificação fiscal usada em nota e contrato — 8 dígitos. Fica opcional: nem
 * todo item de merenda tem NCM cadastrado no sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ali_item', function (Blueprint $table) {
            $table->string('itm_ncm', 8)->nullable()->after('itm_cd_item');
        });
    }

    public function down(): void
    {
        Schema::table('ali_item', function (Blueprint $table) {
            $table->dropColumn('itm_ncm');
        });
    }
};
