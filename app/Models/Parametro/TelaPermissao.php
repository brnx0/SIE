<?php

namespace App\Models\Parametro;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelaPermissao extends Model
{
    use \App\Models\Concerns\Auditable;
    protected $table = 'cfg_tela_permissao';
    protected $primaryKey = 'tlp_id';

    const CREATED_AT = 'tlp_created_at';
    const UPDATED_AT = 'tlp_updated_at';

    protected $fillable = [
        'tlp_tla_id',
        'tlp_role',
        'tlp_nivel',
    ];

    protected $casts = [
        'tlp_tla_id' => 'integer',
        'tlp_nivel' => 'integer',
    ];

    public function tela(): BelongsTo
    {
        return $this->belongsTo(Tela::class, 'tlp_tla_id', 'tla_id');
    }
}
