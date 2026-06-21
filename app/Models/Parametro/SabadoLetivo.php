<?php

namespace App\Models\Parametro;

use App\Models\Escola\Escola;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SabadoLetivo extends Model
{
    protected $table = 'cfg_sabado_letivo';
    protected $primaryKey = 'sbl_id';

    const CREATED_AT = 'sbl_created_at';
    const UPDATED_AT = 'sbl_updated_at';

    protected $fillable = [
        'sbl_anl_id',
        'sbl_dt_sabado',
        'sbl_dia_semana',
        'sbl_created_by_id',
        'sbl_updated_by_id',
    ];

    protected $casts = [
        'sbl_dt_sabado'  => 'date',
        'sbl_dia_semana' => 'integer',
    ];

    public const DIAS_SEMANA = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
    ];

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'sbl_anl_id', 'anl_id');
    }

    /** Escolas que NÃO terão este sábado letivo. */
    public function escolasExcluidas(): BelongsToMany
    {
        return $this->belongsToMany(Escola::class, 'cfg_sabado_letivo_excecao', 'sle_sbl_id', 'sle_esc_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sbl_created_by_id');
    }
}
