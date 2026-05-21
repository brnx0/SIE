<?php

namespace App\Models\Escola;

use App\Models\Municipio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bairro extends Model
{
    protected $table = 'edu_bairro';
    protected $primaryKey = 'bai_id';

    const CREATED_AT = 'bai_created_at';
    const UPDATED_AT = 'bai_updated_at';

    protected $fillable = [
        'bai_mun_id',
        'bai_nome',
        'bai_fl_ativo',
    ];

    protected $casts = [
        'bai_fl_ativo' => 'boolean',
    ];

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'bai_mun_id', 'mun_id');
    }

    public function scopeSearch(Builder $query, ?string $term, ?int $munId = null): Builder
    {
        if ($munId) {
            $query->where('bai_mun_id', $munId);
        }
        if ($term) {
            $query->where('bai_nome', 'ilike', "%{$term}%");
        }
        return $query->orderBy('bai_nome');
    }
}
