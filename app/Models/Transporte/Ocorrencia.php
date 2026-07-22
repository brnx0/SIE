<?php

namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ocorrencia extends Model
{
    use \App\Models\Concerns\Auditable;

    public const TIPOS = [
        'atraso'          => 'Atraso',
        'pane'            => 'Pane/Avaria',
        'acidente'        => 'Acidente',
        'indisciplina'    => 'Indisciplina',
        'via_interditada' => 'Via interditada',
        'outro'           => 'Outro',
    ];

    protected $table = 'edu_transporte_ocorrencia';
    protected $primaryKey = 'toc_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'toc_created_at';
    const UPDATED_AT = 'toc_updated_at';

    protected $fillable = [
        'toc_tdb_id',
        'toc_tipo',
        'toc_hr',
        'toc_descricao',
    ];

    protected $casts = [
        'toc_tdb_id' => 'integer',
    ];

    public function diario(): BelongsTo
    {
        return $this->belongsTo(DiarioBordo::class, 'toc_tdb_id', 'tdb_id');
    }
}
