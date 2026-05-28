<?php

namespace App\Models\Parametro;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipoUnidade extends Model
{
    protected $table = 'cfg_tipo_unidade';
    protected $primaryKey = 'tun_id';

    const CREATED_AT = 'tun_created_at';
    const UPDATED_AT = 'tun_updated_at';

    protected $fillable = [
        'tun_tipo',
        'tun_anl_id_inicio',
        'tun_anl_id_fim',
    ];

    protected $casts = [
        'tun_anl_id_inicio' => 'integer',
        'tun_anl_id_fim'    => 'integer',
    ];

    public function anoLetivoInicio(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'tun_anl_id_inicio', 'anl_id');
    }

    public function anoLetivoFim(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'tun_anl_id_fim', 'anl_id');
    }

    /** Tipos válidos de unidade */
    public const TIPOS = [
        'unidade_didatica' => 'Unidade Didática',
        'bimestral'        => 'Bimestral',
        'fase'             => 'Fase',
        'semestral'        => 'Semestral',
        'trimestral'       => 'Trimestral',
    ];
}
