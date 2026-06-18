<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiarioNota extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_nota';
    protected $primaryKey = 'nta_id';

    const CREATED_AT = 'nta_created_at';
    const UPDATED_AT = 'nta_updated_at';
    const DELETED_AT = 'nta_deleted_at';

    protected $fillable = [
        'nta_ava_id',
        'nta_aln_id',
        'nta_cnc_id',
        'nta_valor',
    ];

    protected $casts = [
        'nta_valor' => 'decimal:2',
    ];

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(DiarioAvaliacao::class, 'nta_ava_id', 'ava_id');
    }

    public function conceito(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Parametro\Conceito::class, 'nta_cnc_id', 'cnc_id');
    }
}
