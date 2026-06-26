<?php

namespace App\Models\Diario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiarioDiagnostico extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_diagnostico';
    protected $primaryKey = 'dgn_id';

    const CREATED_AT = 'dgn_created_at';
    const UPDATED_AT = 'dgn_updated_at';
    const DELETED_AT = 'dgn_deleted_at';

    /** Opção que, ao ser marcada, propaga para os períodos seguintes. */
    public const OPCAO_AUTONOMIA = 'autonomia';

    /** Opções válidas → rótulos. */
    public const OPCOES = [
        'autonomia'    => 'Realiza com autonomia',
        'apoio'        => 'Realiza com apoio',
        'nao_realiza'  => 'Ainda não realiza',
        'nao_trabalha' => 'Não trabalha',
    ];

    protected $fillable = [
        'dgn_user_id',
        'dgn_esc_id',
        'dgn_anl_id',
        'dgn_tur_id',
        'dgn_dis_id',
        'dgn_uni_id',
        'dgn_aln_id',
        'dgn_ind_id',
        'dgn_opcao',
    ];
}
