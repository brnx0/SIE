<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('edu_instrumento_avaliativo', 'iav_fl_recuperacao')) {
            Schema::table('edu_instrumento_avaliativo', function (Blueprint $t) {
                $t->boolean('iav_fl_recuperacao')->default(false)->after('iav_fl_ativo');
            });
        }

        $now = now();
        $instrumentos = [
            ['iav_nome' => 'Prova',                 'iav_fl_recuperacao' => false],
            ['iav_nome' => 'Trabalho em Grupo',     'iav_fl_recuperacao' => false],
            ['iav_nome' => 'Trabalho individual',   'iav_fl_recuperacao' => false],
            ['iav_nome' => 'Observação',            'iav_fl_recuperacao' => false],
            ['iav_nome' => 'Prova de Recuperação',  'iav_fl_recuperacao' => true],
        ];

        foreach ($instrumentos as $i) {
            DB::table('edu_instrumento_avaliativo')->updateOrInsert(
                ['iav_nome' => $i['iav_nome']],
                [
                    'iav_fl_ativo'       => true,
                    'iav_fl_recuperacao' => $i['iav_fl_recuperacao'],
                    'iav_updated_at'     => $now,
                    'iav_created_at'     => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('edu_instrumento_avaliativo', 'iav_fl_recuperacao')) {
            Schema::table('edu_instrumento_avaliativo', function (Blueprint $t) {
                $t->dropColumn('iav_fl_recuperacao');
            });
        }
    }
};
