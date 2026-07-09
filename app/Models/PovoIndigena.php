<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PovoIndigena extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'edu_povo_indigena';
    protected $primaryKey = 'pin_id';

    const CREATED_AT = 'pin_created_at';
    const UPDATED_AT = 'pin_updated_at';

    protected $fillable = [
        'pin_cd_censo',
        'pin_nome',
    ];

    protected $casts = [
        'pin_cd_censo' => 'integer',
    ];

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term) {
            // unaccent + ilike → case + accent insensitive (Postgres); código exato também casa.
            $query->where(function ($q) use ($term) {
                $q->whereRaw('unaccent(pin_nome) ILIKE unaccent(?)', ["%{$term}%"]);
                if (ctype_digit($term)) {
                    $q->orWhere('pin_cd_censo', (int) $term);
                }
            });
        }

        return $query->orderBy('pin_nome');
    }
}
