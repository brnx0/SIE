<?php

namespace App\Models\Parametro;

use App\Models\Matricula\TurmaAlunoSituacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SituacaoBloqueio extends Model
{
    protected $table = 'cfg_situacao_bloqueio';
    protected $primaryKey = 'sba_id';

    const CREATED_AT = 'sba_created_at';
    const UPDATED_AT = 'sba_updated_at';

    protected $fillable = [
        'sba_tas_cod',
        'sba_created_by_id',
    ];

    protected $casts = [
        'sba_tas_cod' => 'integer',
    ];

    public function situacao(): BelongsTo
    {
        return $this->belongsTo(TurmaAlunoSituacao::class, 'sba_tas_cod', 'tas_cod');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sba_created_by_id');
    }
}
