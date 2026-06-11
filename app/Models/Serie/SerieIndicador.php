<?php

namespace App\Models\Serie;

use App\Models\Disciplina\Disciplina;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SerieIndicador extends Model
{
    use SoftDeletes;

    protected $table = 'edu_serie_indicador';
    protected $primaryKey = 'ind_id';

    const CREATED_AT = 'ind_created_at';
    const UPDATED_AT = 'ind_updated_at';
    const DELETED_AT = 'ind_deleted_at';

    protected $fillable = [
        'ind_ser_id',
        'ind_dis_id',
        'ind_descricao',
        'ind_fl_ativo',
    ];

    protected $casts = [
        'ind_fl_ativo' => 'boolean',
    ];

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'ind_ser_id', 'ser_id');
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'ind_dis_id', 'dis_id');
    }
}
