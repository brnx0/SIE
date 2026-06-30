<?php

namespace App\Models\Parametro;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tela extends Model
{
    protected $table = 'cfg_tela';
    protected $primaryKey = 'tla_id';

    const CREATED_AT = 'tla_created_at';
    const UPDATED_AT = 'tla_updated_at';

    protected $fillable = [
        'tla_rota',
        'tla_nome',
        'tla_grupo',
        'tla_ordem',
    ];

    protected $casts = [
        'tla_ordem' => 'integer',
    ];

    public function permissoes(): HasMany
    {
        return $this->hasMany(TelaPermissao::class, 'tlp_tla_id', 'tla_id');
    }
}
