<?php

namespace App\Models\Diario;

use App\Models\Serie\SerieIndicador;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiarioPlanoIndicador extends Model
{
    public static $snakeAttributes = false;

    protected $table = 'edu_diario_plano_indicador';
    protected $primaryKey = 'dpi_id';

    const CREATED_AT = 'dpi_created_at';
    const UPDATED_AT = 'dpi_updated_at';

    protected $fillable = [
        'dpi_dpa_id',
        'dpi_ind_id',
    ];

    public function plano(): BelongsTo
    {
        return $this->belongsTo(DiarioPlanoAula::class, 'dpi_dpa_id', 'dpa_id');
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(SerieIndicador::class, 'dpi_ind_id', 'ind_id');
    }
}
