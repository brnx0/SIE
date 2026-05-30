<?php

namespace App\Models\Turma;

use App\Models\Escola\Escola;
use App\Models\Parametro\AnoLetivo;
use App\Models\Segmento\Segmento;
use App\Models\Serie\Serie;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Turma extends Model
{
    use SoftDeletes;

    public static $snakeAttributes = false;

    protected $table = 'edu_turma';
    protected $primaryKey = 'tur_id';

    const CREATED_AT = 'tur_created_at';
    const UPDATED_AT = 'tur_updated_at';
    const DELETED_AT = 'tur_deleted_at';

    const TURNOS = ['INTEGRAL', 'MATUTINO', 'NOTURNO', 'VESPERTINO'];

    const TIPOS_ATENDIMENTO = [
        'NÃO SE APLICA',
        'CLASSE HOSPITALAR',
        'UNIDADE DE INTERNAÇÃO',
        'UNIDADE PRISIONAL',
        'ATIVIDADE COMPLEMENTAR',
        'ATENDIMENTO EDUCACIONAL ESPECIALIZADO - AEE',
    ];

    const TIPOS_MEDIACAO = [
        'PRESENCIAL',
        'SEMIPRESENCIAL',
        'EDUCAÇÃO A DISTÂNCIA',
    ];

    const LOCAIS_DIFERENCIADOS = [
        'NAO ESTA EM LOCAL DIFERENCIADO',
        'SALA ANEXA',
        'UNIDADE DE ATENDIMENTO SOCIOEDUCATIVA',
        'UNIDADE PRISIONAL',
    ];

    const SITUACOES = ['ABERTA', 'ENCERRADA'];

    const SEMESTRES = [1, 2];

    const DIAS_SEMANA = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sab'];

    // Campos estruturais bloqueados após turma ABERTA
    const CAMPOS_ESTRUTURAIS = [
        'tur_esc_id',
        'tur_anl_id',
        'tur_seg_id',
        'tur_ser_id',
        'tur_semestre',
    ];

    protected $fillable = [
        'tur_esc_id',
        'tur_anl_id',
        'tur_seg_id',
        'tur_ser_id',
        'tur_cd_inep',
        'tur_nome',
        'tur_turno',
        'tur_capacidade',
        'tur_semestre',
        'tur_qt_expansao',
        'tur_tipo_atendimento',
        'tur_situacao',
        'tur_hora_inicio',
        'tur_hora_fim',
        'tur_mediacao',
        'tur_local_diferenciado',
        'tur_dias_funcionamento',
        'tur_obs',
    ];

    protected $casts = [
        'tur_capacidade'         => 'integer',
        'tur_semestre'           => 'integer',
        'tur_qt_expansao'        => 'integer',
        'tur_dias_funcionamento' => 'array',
    ];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'tur_esc_id', 'esc_id');
    }

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'tur_anl_id', 'anl_id');
    }

    public function segmento(): BelongsTo
    {
        return $this->belongsTo(Segmento::class, 'tur_seg_id', 'seg_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'tur_ser_id', 'ser_id');
    }

    public function professores(): HasMany
    {
        return $this->hasMany(TurmaProfessor::class, 'tup_tur_id', 'tur_id');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(TurmaHorario::class, 'trh_tur_id', 'tur_id');
    }
}
