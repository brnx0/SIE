<?php

namespace App\Models\Parametro;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Instrumentos avaliativos (e valor máximo de cada) parametrizados para uma unidade. */
class ParametrizacaoAvaliacao extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'cfg_parametrizacao_avaliacao';
    protected $primaryKey = 'pav_id';

    const CREATED_AT = 'pav_created_at';
    const UPDATED_AT = 'pav_updated_at';

    protected $fillable = [
        'pav_uni_id',
        'pav_fl_bloqueado',
        'pav_updated_by_id',
    ];

    protected $casts = [
        'pav_fl_bloqueado' => 'boolean',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(ParametrizacaoAvaliacaoItem::class, 'pai_pav_id', 'pav_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'pav_uni_id', 'uni_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pav_updated_by_id');
    }
}
