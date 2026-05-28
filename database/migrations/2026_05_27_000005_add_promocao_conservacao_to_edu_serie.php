<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_serie', function (Blueprint $table) {
            $table->unsignedBigInteger('ser_promo_ser_id_1')->nullable()->after('ser_tipo_avaliacao_descritiva');
            $table->unsignedBigInteger('ser_promo_ser_id_2')->nullable()->after('ser_promo_ser_id_1');
            $table->unsignedBigInteger('ser_cons_ser_id_1')->nullable()->after('ser_promo_ser_id_2');
            $table->unsignedBigInteger('ser_cons_ser_id_2')->nullable()->after('ser_cons_ser_id_1');

            $table->foreign('ser_promo_ser_id_1')->references('ser_id')->on('edu_serie')->nullOnDelete();
            $table->foreign('ser_promo_ser_id_2')->references('ser_id')->on('edu_serie')->nullOnDelete();
            $table->foreign('ser_cons_ser_id_1')->references('ser_id')->on('edu_serie')->nullOnDelete();
            $table->foreign('ser_cons_ser_id_2')->references('ser_id')->on('edu_serie')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('edu_serie', function (Blueprint $table) {
            $table->dropForeign(['ser_promo_ser_id_1']);
            $table->dropForeign(['ser_promo_ser_id_2']);
            $table->dropForeign(['ser_cons_ser_id_1']);
            $table->dropForeign(['ser_cons_ser_id_2']);
            $table->dropColumn(['ser_promo_ser_id_1', 'ser_promo_ser_id_2', 'ser_cons_ser_id_1', 'ser_cons_ser_id_2']);
        });
    }
};
