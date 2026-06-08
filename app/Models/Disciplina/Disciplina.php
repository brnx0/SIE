<?php

namespace App\Models\Disciplina;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Disciplina extends Model
{
    use SoftDeletes;

    protected $table = 'edu_disciplina';
    protected $primaryKey = 'dis_id';

    const CREATED_AT = 'dis_created_at';
    const UPDATED_AT = 'dis_updated_at';
    const DELETED_AT = 'dis_deleted_at';

    protected $fillable = [
        'arc_id',
        'dis_cod_ref',
        'dis_nome_mec',
        'dis_nome',
        'dis_sigla',
        'dis_fl_ativo',
    ];

    protected $casts = [
        'arc_id'             => 'integer',
        'dis_cod_ref'        => 'integer',
        'dis_fl_ativo'       => 'boolean',
    ];

    public function areaConhecimento(): BelongsTo
    {
        return $this->belongsTo(AreaConhecimento::class, 'arc_id', 'arc_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->where(function ($q2) use ($q) {
            $q2->whereRaw('dis_nome ilike ?', ["%{$q}%"])
               ->orWhereRaw('dis_nome_mec ilike ?', ["%{$q}%"]);
        });
    }
}
