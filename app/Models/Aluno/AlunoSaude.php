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
    ];

    protected $casts = [
        'als_fl_pcd' => 'boolean',
    ];

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'als_aln_id', 'aln_id');
    }
}
