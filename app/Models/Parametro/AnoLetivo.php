<?php

namespace App\Models\Parametro;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnoLetivo extends Model
{
    use SoftDeletes;

    protected $table = 'cfg_ano_letivo';
    protected $primaryKey = 'anl_id';

    const CREATED_AT = 'anl_created_at';
    const UPDATED_AT = 'anl_updated_at';
    const DELETED_AT = 'anl_deleted_at';

    protected $fillable = [
        'anl_ano',
        'anl_dt_inicio_ano',
        'anl_dt_inicio_1sem',
        'anl_dt_inicio_2sem',
        'anl_dt_fim',
        'anl_dt_censo',
        'anl_fl_em_exercicio',
        'anl_fl_progressao_parcial',
        'anl_fl_aprovacao_conselho_freq',
        'anl_created_by_id',
        'anl_updated_by_id',
    ];

    protected $casts = [
        'anl_ano' => 'integer',
        'anl_dt_inicio_ano' => 'date',
        'anl_dt_inicio_1sem' => 'date',
        'anl_dt_inicio_2sem' => 'date',
        'anl_dt_fim' => 'date',
        'anl_dt_censo' => 'date',
        'anl_fl_em_exercicio' => 'boolean',
        'anl_fl_progressao_parcial' => 'boolean',
        'anl_fl_aprovacao_conselho_freq' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anl_created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anl_updated_by_id');
    }
}
