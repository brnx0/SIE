<?php

namespace App\Models\Diario;

use App\Models\Aluno\Aluno;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AeeAvaliacao extends Model
{
    use SoftDeletes;

    protected $table = 'edu_diario_aee_avaliacao';
    protected $primaryKey = 'dav_id';

    const CREATED_AT = 'dav_created_at';
    const UPDATED_AT = 'dav_updated_at';
    const DELETED_AT = 'dav_deleted_at';

    protected $fillable = [
        'dav_user_id',
        'dav_esc_id',
        'dav_anl_id',
        'dav_tur_id',
        'dav_uni_id',
        'dav_aln_id',
        'dav_dt',
        'dav_descricao',
    ];

    protected $casts = [
        'dav_dt' => 'date',
    ];

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'dav_aln_id', 'aln_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dav_user_id');
    }
}
