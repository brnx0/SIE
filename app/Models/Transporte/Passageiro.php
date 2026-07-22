<?php

namespace App\Models\Transporte;

use App\Models\Aluno\Aluno;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Passageiro extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'edu_transporte_passageiro';
    protected $primaryKey = 'pas_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'pas_created_at';
    const UPDATED_AT = 'pas_updated_at';
    const DELETED_AT = 'pas_deleted_at';

    protected $fillable = [
        'pas_rot_id',
        'pas_pto_id',
        'pas_aln_id',
        'pas_tue_id',
        'pas_dt_inicio',
        'pas_dt_fim',
        'pas_obs',
    ];

    protected $casts = [
        'pas_rot_id' => 'integer',
        'pas_pto_id' => 'integer',
        'pas_aln_id' => 'integer',
        'pas_tue_id' => 'integer',
    ];

    public function rota(): BelongsTo
    {
        return $this->belongsTo(Rota::class, 'pas_rot_id', 'rot_id');
    }

    public function ponto(): BelongsTo
    {
        return $this->belongsTo(Ponto::class, 'pas_pto_id', 'pto_id');
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'pas_aln_id', 'aln_id');
    }

    public function usuarioExterno(): BelongsTo
    {
        return $this->belongsTo(UsuarioExterno::class, 'pas_tue_id', 'tue_id');
    }
}
