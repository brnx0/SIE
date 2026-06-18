<?php

namespace App\Models\Parametro;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conceito extends Model
{
    protected $table = 'cfg_conceito';
    protected $primaryKey = 'cnc_id';

    const CREATED_AT = 'cnc_created_at';
    const UPDATED_AT = 'cnc_updated_at';

    protected $fillable = [
        'cnc_sigla',
        'cnc_descricao',
        'cnc_limite_inferior',
        'cnc_limite_superior',
        'cnc_peso',
        'cnc_created_by_id',
        'cnc_updated_by_id',
    ];

    protected $casts = [
        'cnc_limite_inferior' => 'decimal:2',
        'cnc_limite_superior' => 'decimal:2',
        'cnc_peso'            => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cnc_created_by_id');
    }
}
