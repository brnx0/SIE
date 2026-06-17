<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiarioAvaliacao extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_avaliacao';
    protected $primaryKey = 'ava_id';

    const CREATED_AT = 'ava_created_at';
    const UPDATED_AT = 'ava_updated_at';
    const DELETED_AT = 'ava_deleted_at';

    public const TIPO_NUMERICA   = 'numerica';
    public const TIPO_CONCEITUAL = 'conceitual';

    protected $fillable = [
        'ava_user_id',
        'ava_esc_id',
        'ava_anl_id',
        'ava_tur_id',
        'ava_dis_id',
        'ava_uni_id',
        'ava_iav_id',
        'ava_tipo',
        'ava_descricao',
        'ava_dt',
        'ava_valor',
        'ava_fl_recuperacao',
    ];

    protected $casts = [
        'ava_dt'             => 'date',
        'ava_valor'          => 'decimal:2',
        'ava_fl_recuperacao' => 'boolean',
    ];

    public function notas(): HasMany
    {
        return $this->hasMany(DiarioNota::class, 'nta_ava_id', 'ava_id');
    }

    public function instrumento(): BelongsTo
    {
        return $this->belongsTo(InstrumentoAvaliativo::class, 'ava_iav_id', 'iav_id');
    }
}
