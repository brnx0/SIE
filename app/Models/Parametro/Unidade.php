<?php

namespace App\Models\Parametro;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unidade extends Model
{
    protected $table = 'cfg_unidade';
    protected $primaryKey = 'uni_id';

    const CREATED_AT = 'uni_created_at';
    const UPDATED_AT = 'uni_updated_at';

    protected $fillable = [
        'uni_anl_id',
        'uni_tipo',
        'uni_numero',
        'uni_dt_inicio',
        'uni_dt_fim',
        'uni_dias_extensao',
    ];

    protected $casts = [
        'uni_anl_id'        => 'integer',
        'uni_numero'        => 'integer',
        'uni_dias_extensao' => 'integer',
        'uni_dt_inicio'     => 'date:Y-m-d',
        'uni_dt_fim'        => 'date:Y-m-d',
    ];

    public const LIMITES = [
        'bimestre'  => 4,
        'unidade'   => 4,
        'trimestre' => 3,
        'semestre'  => 2,
    ];

    public const LABELS = [
        'bimestre'  => 'Bimestral',
        'unidade'   => 'Por Unidade',
        'trimestre' => 'Trimestral',
        'semestre'  => 'Semestral',
    ];

    public const ORDINAL = [
        1 => '1º',
        2 => '2º',
        3 => '3º',
        4 => '4º',
    ];

    public function getUniDtFimEfetivoAttribute(): ?string
    {
        if (! $this->uni_dt_fim) {
            return null;
        }

        $fim = Carbon::parse($this->uni_dt_fim);

        if ($this->uni_dias_extensao > 0) {
            $fim->addDays($this->uni_dias_extensao);
        }

        return $fim->format('Y-m-d');
    }

    protected $appends = ['uni_dt_fim_efetivo'];

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'uni_anl_id', 'anl_id');
    }

    public function scopePorAno($query, int $anlId)
    {
        return $query->where('uni_anl_id', $anlId)->orderBy('uni_numero');
    }
}
