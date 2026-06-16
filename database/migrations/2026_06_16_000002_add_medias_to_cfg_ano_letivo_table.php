<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfg_ano_letivo', function (Blueprint $table) {
            // Frequência mínima (%) que o aluno deve comparecer às aulas
            $table->decimal('anl_frequencia_minima', 5, 2)->nullable()->after('anl_fl_aprovacao_conselho_freq');
            // Média geral para aprovação das notas do aluno
            $table->decimal('anl_media_geral', 4, 2)->nullable()->after('anl_frequencia_minima');
        });
    }

    public function down(): void
    {
        Schema::table('cfg_ano_letivo', function (Blueprint $table) {
            $table->dropColumn(['anl_frequencia_minima', 'anl_media_geral']);
        });
    }
};
