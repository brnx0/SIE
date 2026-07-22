<?php

namespace App\Models\Funcionario;

use App\Models\Municipio;
use App\Models\PovoIndigena;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Funcionario extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'edu_funcionario';
    protected $primaryKey = 'fun_id';

    const CREATED_AT = 'fun_created_at';
    const UPDATED_AT = 'fun_updated_at';
    const DELETED_AT = 'fun_deleted_at';

    /** Categorias válidas de CNH. */
    public const CNH_CATEGORIAS = ['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'];

    protected $fillable = [
        // Dados pessoais
        'fun_nome',
        'fun_nome_social',
        'fun_dt_nascimento',
        'fun_sexo',
        'fun_cor_raca',
        'fun_nacionalidade',
        'fun_pais_origem',
        'fun_mun_id_nasc',
        'fun_cpf',
        'fun_religiao',
        'fun_estado_civil',
        'fun_povo_indigena',
        'fun_pin_id',
        'fun_cd_censo',

        // Documentação
        'fun_rg_numero',
        'fun_rg_dt_emissao',
        'fun_rg_uf',
        'fun_rg_orgao_emissor',
        'fun_certidao_modelo',
        'fun_certidao_tipo',
        'fun_certidao_dt_emissao',
        'fun_certidao_numero',
        'fun_certidao_livro',
        'fun_certidao_pagina',
        'fun_certidao_mun_id',
        'fun_certidao_cartorio',
        'fun_ctps_numero',
        'fun_ctps_serie',
        'fun_pis_pasep',
        'fun_titulo_eleitor',
        'fun_titulo_zona',
        'fun_titulo_secao',
        'fun_certificado_reservista',

        // CNH — habilita o funcionário a conduzir veículo do transporte escolar
        'fun_cnh_numero',
        'fun_cnh_categoria',
        'fun_cnh_dt_validade',

        // Endereço
        'fun_cep',
        'fun_logradouro',
        'fun_numero',
        'fun_complemento',
        'fun_bairro',
        'fun_cidade',
        'fun_uf',

        // Contato
        'fun_telefone',
        'fun_celular',
        'fun_email',

        'fun_fl_usa_transporte',
        'fun_transporte_tipo',

        'fun_foto',
        'fun_fl_ativo',
    ];

    protected $casts = [
        'fun_dt_nascimento' => 'date',
        'fun_rg_dt_emissao' => 'date',
        'fun_certidao_dt_emissao' => 'date',
        'fun_cnh_dt_validade' => 'date',
        'fun_cor_raca' => 'integer',
        'fun_estado_civil' => 'integer',
        'fun_fl_usa_transporte' => 'boolean',
        'fun_fl_ativo' => 'boolean',
    ];

    protected $appends = ['fun_foto_url'];

    protected function funFotoUrl(): Attribute
    {
        // Servida via rota PHP (evita 403 do symlink /storage em alguns ambientes).
        return Attribute::get(fn () => $this->fun_foto && $this->fun_id
            ? route('midia.funcionario.foto', $this->fun_id)
            : null);
    }

    public function municipioNascimento(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'fun_mun_id_nasc', 'mun_id');
    }

    public function povoIndigena(): BelongsTo
    {
        return $this->belongsTo(PovoIndigena::class, 'fun_pin_id', 'pin_id');
    }

    public function municipioCertidao(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'fun_certidao_mun_id', 'mun_id');
    }

    public function admissoes(): HasMany
    {
        return $this->hasMany(FuncionarioAdmissao::class, 'adm_fun_id', 'fun_id');
    }

    public function escolaridades(): HasMany
    {
        return $this->hasMany(FuncionarioEscolaridade::class, 'fes_fun_id', 'fun_id');
    }
}
