<?php

namespace App\Models\Censo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CensoLote extends Model
{
    protected $table = 'imp_censo_lote';
    protected $primaryKey = 'icl_id';

    const CREATED_AT = 'icl_created_at';
    const UPDATED_AT = 'icl_updated_at';

    protected $fillable = [
        'icl_arquivo_nome',
        'icl_arquivo_path',
        'icl_anl_id',
        'icl_versao_layout',
        'icl_status',
        'icl_total_linhas',
        'icl_total_erros',
        'icl_contadores',
        'icl_mensagem',
        'icl_created_by',
    ];

    protected $casts = [
        'icl_contadores' => 'array',
    ];

    public function registros(): HasMany
    {
        return $this->hasMany(CensoRegistro::class, 'icr_icl_id', 'icl_id');
    }
}
