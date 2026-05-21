<?php

namespace App\Models\Escola;

use App\Models\Municipio;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Escola extends Model
{
    use SoftDeletes;

    protected $table = 'edu_escola';
    protected $primaryKey = 'esc_id';

    const CREATED_AT = 'esc_created_at';
    const UPDATED_AT = 'esc_updated_at';
    const DELETED_AT = 'esc_deleted_at';

    protected $fillable = [
        'esc_cd_inep', 'esc_cnpj', 'esc_nome', 'esc_apelido', 'esc_cd_escola', 'esc_logo',
        'esc_cep', 'esc_logradouro', 'esc_numero', 'esc_complemento',
        'esc_bai_id', 'esc_mun_id', 'esc_zona', 'esc_localizacao_dif',
        'esc_latitude', 'esc_longitude', 'esc_caixa_postal',
        'esc_ddd', 'esc_telefone_fixo', 'esc_fax', 'esc_telefone_2', 'esc_telefone_3',
        'esc_email', 'esc_site',
        'esc_dep_administrativa', 'esc_proprietario_imovel', 'esc_forma_ocupacao',
        'esc_situacao_func', 'esc_regulamentada_conselho', 'esc_turno_escolar',
        'esc_ger_id', 'esc_orgao_regional_ensino',
        'esc_fl_creche', 'esc_fl_predio_compartilhado', 'esc_fl_sorteio_vagas',
        'esc_fl_ativo',
    ];

    protected $casts = [
        'esc_localizacao_dif' => 'integer',
        'esc_dep_administrativa' => 'integer',
        'esc_proprietario_imovel' => 'integer',
        'esc_forma_ocupacao' => 'integer',
        'esc_situacao_func' => 'integer',
        'esc_regulamentada_conselho' => 'boolean',
        'esc_fl_creche' => 'boolean',
        'esc_fl_predio_compartilhado' => 'boolean',
        'esc_fl_sorteio_vagas' => 'boolean',
        'esc_fl_ativo' => 'boolean',
        'esc_latitude' => 'decimal:7',
        'esc_longitude' => 'decimal:7',
    ];

    protected $appends = ['esc_logo_url'];

    protected function escLogoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->esc_logo ? Storage::disk('public')->url($this->esc_logo) : null);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'esc_mun_id', 'mun_id');
    }

    public function bairro(): BelongsTo
    {
        return $this->belongsTo(Bairro::class, 'esc_bai_id', 'bai_id');
    }

    public function gerencia(): BelongsTo
    {
        return $this->belongsTo(GerenciaRegional::class, 'esc_ger_id', 'ger_id');
    }
}
