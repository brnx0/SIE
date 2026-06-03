<?php

namespace App\Models\Aluno;

use App\Models\Matricula\Matricula;
use App\Models\Municipio;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Aluno extends Model
{
    use SoftDeletes;

    protected $table = 'edu_aluno';
    protected $primaryKey = 'aln_id';

    const CREATED_AT = 'aln_created_at';
    const UPDATED_AT = 'aln_updated_at';
    const DELETED_AT = 'aln_deleted_at';

    protected $fillable = [
        'aln_nome',
        'aln_nome_social',
        'aln_dt_nascimento',
        'aln_sexo',
        'aln_cor_raca',
        'aln_pais_origem',
        'aln_mun_id_nasc',
        'aln_cpf',
        'aln_cd_inep',
        'aln_nr_matricula',
        'aln_nr_certidao',
        'aln_nis',
        'aln_filiacao_1',
        'aln_filiacao_1_tipo',
        'aln_filiacao_2',
        'aln_filiacao_2_tipo',
        'aln_cep',
        'aln_logradouro',
        'aln_numero',
        'aln_complemento',
        'aln_bairro',
        'aln_cidade',
        'aln_uf',
        'aln_telefone',
        'aln_email',
        'aln_foto',
        'aln_fl_ativo',
    ];

    protected $casts = [
        'aln_dt_nascimento' => 'date',
        'aln_cor_raca' => 'integer',
        'aln_nr_matricula' => 'integer',
        'aln_fl_ativo' => 'boolean',
    ];

    protected $appends = ['aln_foto_url'];

    protected function alnFotoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->aln_foto ? Storage::disk('public')->url($this->aln_foto) : null);
    }

    public function saude(): HasOne
    {
        return $this->hasOne(AlunoSaude::class, 'als_aln_id', 'aln_id');
    }

    public function municipioNascimento(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'aln_mun_id_nasc', 'mun_id');
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'tma_aln_id', 'aln_id');
    }
}
