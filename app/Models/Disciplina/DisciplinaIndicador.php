<?php

namespace App\Models\Disciplina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisciplinaIndicador extends Model
{
    use SoftDeletes;

    protected $table = 'edu_disciplina_indicador';
    protected $primaryKey = 'ind_id';

    const CREATED_AT = 'ind_created_at';
    const UPDATED_AT = 'ind_updated_at';
    const DELETED_AT = 'ind_deleted_at';

    protected $fillable = [
        'ind_dis_id',
        'ind_descricao',
        'ind_fl_ativo',
    ];

    protected $casts = [
        'ind_dis_id'  => 'integer',
        'ind_fl_ativo' => 'boolean',
    ];

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'ind_dis_id', 'dis_id');
    }
}
