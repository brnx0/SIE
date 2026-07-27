<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'ali_item';
    protected $primaryKey = 'itm_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'itm_created_at';
    const UPDATED_AT = 'itm_updated_at';
    const DELETED_AT = 'itm_deleted_at';

    protected $fillable = [
        'itm_tpi_id',
        'itm_cat_id',
        'itm_umd_id',
        'itm_cd_item',
        'itm_ncm',
        'itm_nome',
        'itm_especificacao',
        'itm_fl_perecivel',
        'itm_fl_agricultura_familiar',
        'itm_validade_dias',
        'itm_estoque_minimo',
        'itm_fl_ativo',
    ];

    protected $casts = [
        'itm_tpi_id'                  => 'integer',
        'itm_cat_id'                  => 'integer',
        'itm_umd_id'                  => 'integer',
        'itm_fl_perecivel'            => 'boolean',
        'itm_fl_agricultura_familiar' => 'boolean',
        'itm_validade_dias'           => 'integer',
        'itm_estoque_minimo'          => 'decimal:4',
        'itm_fl_ativo'                => 'boolean',
    ];

    public function tipoItem(): BelongsTo
    {
        return $this->belongsTo(TipoItem::class, 'itm_tpi_id', 'tpi_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaAlimento::class, 'itm_cat_id', 'cat_id');
    }

    /** Unidade base de controle: saldo e movimento são sempre convertidos para ela. */
    public function unidadeMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadeMedida::class, 'itm_umd_id', 'umd_id');
    }

    public function conversoes(): HasMany
    {
        return $this->hasMany(ConversaoUnidade::class, 'cvu_itm_id', 'itm_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->where(function ($q2) use ($q) {
            $q2->whereRaw('itm_nome ilike ?', ["%{$q}%"])
               ->orWhereRaw('itm_cd_item ilike ?', ["%{$q}%"])
               ->orWhereRaw('itm_especificacao ilike ?', ["%{$q}%"]);
        });
    }
}
