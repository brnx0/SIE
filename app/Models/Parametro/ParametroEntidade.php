<?php

namespace App\Models\Parametro;

use App\Models\Municipio;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ParametroEntidade extends Model
{
    use \App\Models\Concerns\Auditable;
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
        'par_fl_cpf_obrigatorio',
        'par_fl_replicar_conteudo_diario',
    ];

    protected $casts = [
        'par_fl_nome_pessoa_caixa_alta' => 'boolean',
        'par_fl_nome_escola_caixa_alta' => 'boolean',
        'par_fl_alertar_homonimos' => 'boolean',
        'par_fl_alertar_acentos_nomes' => 'boolean',
        'par_fl_validar_idade_serie' => 'boolean',
        'par_fl_gerar_matricula_auto' => 'boolean',
        'par_fl_cpf_obrigatorio' => 'boolean',
        'par_fl_replicar_conteudo_diario' => 'boolean',
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

    /** Rótulo do município p/ cabeçalho de relatórios: "Cidade / UF" (ou null). */
    protected function municipioLabel(): Attribute
    {
        return Attribute::get(fn () => $this->municipio
            ? $this->municipio->mun_nome . ' / ' . $this->municipio->mun_uf
            : null);
    }

    /**
     * Payload padrão do cabeçalho institucional dos relatórios.
     * Fonte única para todos os controllers de Relatório (inclui município).
     *
     * @return array<string,mixed>
     */
    public function cabecalhoRelatorio(): array
    {
        return [
            'nome_entidade'      => $this->par_nome_entidade,
            'msg_cab_secretaria' => $this->par_msg_cab_secretaria,
            'msg_cab_estado'     => $this->par_msg_cab_estado,
            'endereco'           => $this->par_endereco,
            'municipio'          => $this->municipio_label,
            'logomarca_url'      => $this->par_logomarca_url,
            'brasao_url'         => $this->par_brasao_url,
        ];
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'par_mun_id', 'mun_id');
    }

    public static function current(): self
    {
        return Cache::remember('parametro_entidade', 3600, fn () => static::firstOrFail());
    }

    public static function clearCache(): void
    {
        Cache::forget('parametro_entidade');
    }
}
