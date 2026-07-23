<?php

namespace App\Models\Aluno;

use App\Models\Matricula\Matricula;
use App\Models\Municipio;
use App\Models\PovoIndigena;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Aluno extends Model
{
    use \App\Models\Concerns\Auditable;
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
        'aln_pin_id',
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
        'aln_mun_id_resid',
        'aln_uf',
        'aln_zona_residencia',
        'aln_telefone',
        'aln_email',
        'aln_foto',
        'aln_fl_ativo',
        'aln_fl_trouxe_transferencia',
        'aln_fl_trouxe_rg',
        'aln_fl_trouxe_reg_nascimento',
        'aln_fl_bolsa_familia',
        'aln_fl_recebe_merenda',
        'aln_fl_usa_transporte',
        'aln_fl_usa_biblioteca',
        'aln_fl_indigena',
        'aln_fl_creche',
    ];

    protected $casts = [
        'aln_dt_nascimento' => 'date',
        'aln_cor_raca' => 'integer',
        'aln_nr_matricula' => 'integer',
        'aln_zona_residencia' => 'integer',
        'aln_fl_ativo' => 'boolean',
        'aln_fl_trouxe_transferencia' => 'boolean',
        'aln_fl_trouxe_rg' => 'boolean',
        'aln_fl_trouxe_reg_nascimento' => 'boolean',
        'aln_fl_bolsa_familia' => 'boolean',
        'aln_fl_recebe_merenda' => 'boolean',
        'aln_fl_usa_transporte' => 'boolean',
        'aln_fl_usa_biblioteca' => 'boolean',
        'aln_fl_indigena' => 'boolean',
        'aln_fl_creche' => 'boolean',
    ];

    protected $appends = ['aln_foto_url'];

    protected function alnFotoUrl(): Attribute
    {
        // Servida via rota PHP (evita 403 do symlink /storage em alguns ambientes).
        return Attribute::get(fn () => $this->aln_foto && $this->aln_id
            ? route('midia.aluno.foto', $this->aln_id)
            : null);
    }

    public function saude(): HasOne
    {
        return $this->hasOne(AlunoSaude::class, 'als_aln_id', 'aln_id');
    }

    public function municipioNascimento(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'aln_mun_id_nasc', 'mun_id');
    }

    public function municipioResidencia(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'aln_mun_id_resid', 'mun_id');
    }

    public function povoIndigena(): BelongsTo
    {
        return $this->belongsTo(PovoIndigena::class, 'aln_pin_id', 'pin_id');
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'tma_aln_id', 'aln_id');
    }

    /**
     * Público-alvo do AEE: flag única "Aluno com Deficiência (PCD), TGD
     * ou Altas Habilidades" no quadro de saúde (als_fl_pcd).
     */
    public function scopePublicoAee($query)
    {
        return $query->whereHas('saude', fn ($q) => $q->where('als_fl_pcd', true));
    }
}
