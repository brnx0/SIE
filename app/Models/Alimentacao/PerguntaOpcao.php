<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Opção de uma pergunta de múltipla escolha. O peso permite pontuar a visita. */
class PerguntaOpcao extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_pergunta_opcao';
    protected $primaryKey = 'pro_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'pro_created_at';
    const UPDATED_AT = 'pro_updated_at';

    protected $fillable = ['pro_prg_id', 'pro_ordem', 'pro_texto', 'pro_peso'];

    protected $casts = [
        'pro_prg_id' => 'integer',
        'pro_ordem'  => 'integer',
        'pro_peso'   => 'decimal:2',
    ];

    public function pergunta(): BelongsTo
    {
        return $this->belongsTo(Pergunta::class, 'pro_prg_id', 'prg_id');
    }
}
