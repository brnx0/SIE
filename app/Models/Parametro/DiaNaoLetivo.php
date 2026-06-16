<?php

namespace App\Models\Parametro;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaNaoLetivo extends Model
{
    protected $table = 'cfg_dia_nao_letivo';
    protected $primaryKey = 'dnl_id';

    const CREATED_AT = 'dnl_created_at';
    const UPDATED_AT = 'dnl_updated_at';

    protected $fillable = [
        'dnl_anl_id',
        'dnl_dt_dia',
        'dnl_dt_fim',
        'dnl_descricao',
        'dnl_created_by_id',
        'dnl_updated_by_id',
    ];

    protected $casts = [
        'dnl_dt_dia' => 'date',
        'dnl_dt_fim' => 'date',
    ];

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'dnl_anl_id', 'anl_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dnl_created_by_id');
    }
}
