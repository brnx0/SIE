<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $deprecated = [
        'Síndrome de Asperger',
        'Síndrome de Rett',
        'Transt. Des. da Infância',
        'Trans. Global de Desenvolvimento',
        'Síndrome de Down',
        'Microcefalia',
    ];

    public function up(): void
    {
        DB::table('edu_aluno_saude')
            ->whereNotNull('als_transtornos_globais')
            ->get(['als_id', 'als_transtornos_globais'])
            ->each(function ($row) {
                $values = json_decode($row->als_transtornos_globais, true) ?? [];
                $cleaned = array_values(array_filter($values, fn($v) => !in_array($v, $this->deprecated)));
                if (count($cleaned) !== count($values)) {
                    DB::table('edu_aluno_saude')
                        ->where('als_id', $row->als_id)
                        ->update(['als_transtornos_globais' => json_encode($cleaned)]);
                }
            });
    }

    public function down(): void
    {
        // valores removidos não são restaurados
    }
};
