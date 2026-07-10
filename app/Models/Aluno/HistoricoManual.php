<?php

namespace App\Models\Aluno;

use App\Models\Disciplina\Disciplina;
use App\Models\Escola\Escola;
use App\Models\Matricula\TurmaAlunoSituacao;
use App\Models\Serie\Serie;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cabeçalho de um ano escolar lançado MANUALMENTE no histórico do aluno.
 * Só estes registros são editáveis pela tela — anos do sistema não.
 */
class HistoricoManual extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'edu_historico_manual';
    protected $primaryKey = 'hmn_id';

    const CREATED_AT = 'hmn_created_at';
    const UPDATED_AT = 'hmn_updated_at';
    const DELETED_AT = 'hmn_deleted_at';

    protected $fillable = [
        'hmn_aln_id',
        'hmn_ano',
        'hmn_ser_id',
        'hmn_esc_id',
        'hmn_esc_nome',
        'hmn_mun_id',
        'hmn_carga_horaria',
        'hmn_dias_letivos',
        'hmn_frequencia',
        'hmn_tas_cod',
        'hmn_user_id',
    ];

    protected $casts = [
        'hmn_ano'           => 'integer',
        'hmn_frequencia'    => 'decimal:2',
        'hmn_carga_horaria' => 'integer',
        'hmn_dias_letivos'  => 'integer',
        'hmn_tas_cod'       => 'integer',
    ];

    public function disciplinas(): HasMany
    {
        return $this->hasMany(HistoricoManualDisciplina::class, 'hmd_hmn_id', 'hmn_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'hmn_ser_id', 'ser_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'hmn_esc_id', 'esc_id');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Municipio::class, 'hmn_mun_id', 'mun_id');
    }

    public function situacao(): BelongsTo
    {
        return $this->belongsTo(TurmaAlunoSituacao::class, 'hmn_tas_cod', 'tas_cod');
    }
}
