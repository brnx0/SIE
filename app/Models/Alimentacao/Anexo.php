<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Anexo polimórfico (PDF/imagem) de um registro da alimentação.
 *
 * O `type`/`id` do dono são explícitos por causa da convenção de colunas do
 * módulo (prefixo `anx_`, `$snakeAttributes = false`).
 */
class Anexo extends Model
{
    protected $table = 'ali_anexo';
    protected $primaryKey = 'anx_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'anx_created_at';
    const UPDATED_AT = 'anx_updated_at';

    protected $fillable = [
        'anx_anexavel_type',
        'anx_anexavel_id',
        'anx_nome',
        'anx_caminho',
        'anx_mime',
        'anx_tamanho',
        'anx_usr_id',
    ];

    protected $casts = [
        'anx_anexavel_id' => 'integer',
        'anx_tamanho'     => 'integer',
        'anx_usr_id'      => 'integer',
    ];

    public function anexavel(): MorphTo
    {
        return $this->morphTo('anexavel', 'anx_anexavel_type', 'anx_anexavel_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'anx_usr_id', 'id');
    }

    /** True quando o anexo é imagem (para a tela decidir preview vs. ícone). */
    public function ehImagem(): bool
    {
        return str_starts_with((string) $this->anx_mime, 'image/');
    }
}
