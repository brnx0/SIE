<?php

namespace App\Models\Aluno;

use Illuminate\Database\Eloquent\Model;

class TipoMovimentacao extends Model
{
    protected $table = 'edu_tipo_movimentacao';
    protected $primaryKey = 'tmv_cod';
    public $incrementing = false;
    protected $keyType = 'int';

    const CREATED_AT = 'tmv_created_at';
    const UPDATED_AT = 'tmv_updated_at';

    protected $fillable = [
        'tmv_cod',
        'tmv_descricao',
        'tmv_tas_cod_entrada',
        'tmv_tas_cod_saida',
        'tmv_fl_ativo',
    ];

    protected $casts = [
        'tmv_fl_ativo' => 'boolean',
    ];

    public const SEM_DESTINO = [1, 3, 6, 11, 15];
    public const COM_DESTINO = [5, 7];

    public function exigeDestino(): bool
    {
        return in_array($this->tmv_cod, self::COM_DESTINO, true);
    }
}
