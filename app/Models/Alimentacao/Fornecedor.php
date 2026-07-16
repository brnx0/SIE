<?php

namespace App\Models\Alimentacao;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornecedor extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;

    protected $table = 'ali_fornecedor';
    protected $primaryKey = 'frn_id';

    public static $snakeAttributes = false;

    const CREATED_AT = 'frn_created_at';
    const UPDATED_AT = 'frn_updated_at';
    const DELETED_AT = 'frn_deleted_at';

    protected $fillable = [
        'frn_mun_id',
        'frn_bai_id',
        'frn_cnpj',
        'frn_cpf',
        'frn_razao_social',
        'frn_nome_fantasia',
        'frn_fl_agricultura_familiar',
        'frn_email',
        'frn_fone',
        'frn_cep',
        'frn_logradouro',
        'frn_numero',
        'frn_complemento',
        'frn_fl_ativo',
    ];

    protected $casts = [
        'frn_mun_id'                  => 'integer',
        'frn_bai_id'                  => 'integer',
        'frn_fl_agricultura_familiar' => 'boolean',
        'frn_fl_ativo'                => 'boolean',
    ];

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Municipio::class, 'frn_mun_id', 'mun_id');
    }

    public function bairro(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escola\Bairro::class, 'frn_bai_id', 'bai_id');
    }

    public function scopeSearch(Builder $query, string $q): Builder
    {
        $digitos = preg_replace('/\D/', '', $q);
        // O CNPJ pode ser alfanumérico (jul/2026): tirar tudo que não é dígito
        // transformaria "12ABC34501DE35" em "123450135" e a busca não acharia
        // o fornecedor. Para o CNPJ preserva-se [0-9A-Z]; CPF é só dígito.
        $alfanumerico = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $q));

        return $query->where(function ($q2) use ($q, $digitos, $alfanumerico) {
            $q2->whereRaw('frn_razao_social ilike ?', ["%{$q}%"])
               ->orWhereRaw('frn_nome_fantasia ilike ?', ["%{$q}%"]);

            if ($alfanumerico !== '') {
                $q2->orWhere('frn_cnpj', 'like', "%{$alfanumerico}%");
            }

            if ($digitos !== '') {
                $q2->orWhere('frn_cpf', 'like', "%{$digitos}%");
            }
        });
    }
}
