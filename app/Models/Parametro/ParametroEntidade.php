<?php

namespace App\Models\Parametro;

use App\Models\Municipio;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ParametroEntidade extends Model
{
    protected $table = 'cfg_parametros_entidade';
    protected $primaryKey = 'par_id';

    const CREATED_AT = 'par_created_at';
    const UPDATED_AT = 'par_updated_at';

    protected $fillable = [
        'par_nome_entidade',
        'par_msg_cab_secretaria',
        'par_msg_cab_estado',
        'par_endereco',
        'par_mun_id',
        'par_logomarca',
        'par_brasao',
        'par_fl_nome_pessoa_caixa_alta',
        'par_fl_nome_escola_caixa_alta',
        'par_fl_alertar_homonimos',
        'par_fl_alertar_acentos_nomes',
        'par_fl_validar_idade_serie',
        'par_fl_gerar_matricula_auto',
        'par_fl_validar_carga_prof',
        'par_fl_cpf_obrigatorio',
        'par_fl_fardamento_obrigatorio',
        'par_tipo_validacao_carga',
    ];

    protected $casts = [
        'par_fl_nome_pessoa_caixa_alta' => 'boolean',
        'par_fl_nome_escola_caixa_alta' => 'boolean',
        'par_fl_alertar_homonimos' => 'boolean',
        'par_fl_alertar_acentos_nomes' => 'boolean',
        'par_fl_validar_idade_serie' => 'boolean',
        'par_fl_gerar_matricula_auto' => 'boolean',
        'par_fl_validar_carga_prof' => 'boolean',
        'par_fl_cpf_obrigatorio' => 'boolean',
        'par_fl_fardamento_obrigatorio' => 'boolean',
    ];

    protected $appends = ['par_logomarca_url', 'par_brasao_url'];

    protected function parLogomarcaUrl(): Attribute
    {
        return Attribute::get(fn () => $this->par_logomarca ? Storage::disk('public')->url($this->par_logomarca) : null);
    }

    protected function parBrasaoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->par_brasao ? Storage::disk('public')->url($this->par_brasao) : null);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'par_mun_id', 'mun_id');
    }

    public static function current(): self
    {
        return static::firstOrFail();
    }
}
