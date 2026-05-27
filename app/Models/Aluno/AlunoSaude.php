<?php

namespace App\Models\Aluno;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlunoSaude extends Model
{
    protected $table = 'edu_aluno_saude';
    protected $primaryKey = 'als_id';

    const CREATED_AT = 'als_created_at';
    const UPDATED_AT = 'als_updated_at';

    protected $fillable = [
        'als_aln_id',
        'als_tipo_sanguineo',
        'als_ds_alergias',
        'als_fl_pcd',
        'als_contato_emergencia',
        'als_telefone_emergencia',
        'als_plano_saude',
        'als_cartao_sus',
        'als_alergia_a',
        'als_remedio_febre',
        'als_remedio_cefaleia',
        'als_patologias',
        'als_outra_doenca',
        'als_patologias_infancia',
        'als_outra_doenca_infancia',
        'als_deficiencias',
        'als_transtornos_globais',
        'als_transtornos_aprendizagem',
        'als_deficiencia_outro',
        'als_fl_altas_habilidades',
        'als_cid',
        'als_observacao',
        'als_clinicas',
        'als_recursos_inep',
    ];

    protected $casts = [
        'als_fl_pcd' => 'boolean',
        'als_fl_altas_habilidades' => 'boolean',
        'als_patologias' => 'array',
        'als_patologias_infancia' => 'array',
        'als_deficiencias' => 'array',
        'als_transtornos_globais' => 'array',
        'als_transtornos_aprendizagem' => 'array',
        'als_clinicas' => 'array',
        'als_recursos_inep' => 'array',
    ];

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'als_aln_id', 'aln_id');
    }
}
