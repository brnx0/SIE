<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardapioDia extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'ali_cardapio_dia';
    protected $primaryKey = 'cdi_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'cdi_created_at';
    const UPDATED_AT = 'cdi_updated_at';

    protected $fillable = [
        'cdi_crd_id',
        'cdi_prt_id',
        'cdi_semana',
        'cdi_dia_semana',
        'cdi_turno',
        'cdi_refeicao',
    ];

    protected $casts = [
        'cdi_crd_id'     => 'integer',
        'cdi_prt_id'     => 'integer',
        'cdi_semana'     => 'integer',
        'cdi_dia_semana' => 'integer',
    ];

    /** 1 = segunda ... 7 = domingo (mesma convenção ISO do banco). */
    public const DIAS = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public static function nomeDia(int $dia): string
    {
        return self::DIAS[$dia] ?? '';
    }

    public function cardapio(): BelongsTo
    {
        return $this->belongsTo(Cardapio::class, 'cdi_crd_id', 'crd_id');
    }

    public function prato(): BelongsTo
    {
        return $this->belongsTo(Prato::class, 'cdi_prt_id', 'prt_id');
    }
}
