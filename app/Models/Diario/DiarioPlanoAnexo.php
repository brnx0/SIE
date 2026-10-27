<?php

namespace App\Models\Diario;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Anexo (material de apoio) de um plano de aula — arquivo no disco privado. */
class DiarioPlanoAnexo extends Model
{
    use \App\Models\Concerns\Auditable;

    public const DISCO = 'local';

    /** Formatos e limites (validados no servidor; o front só repete para avisar antes). */
    public const EXTENSOES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
    public const MAX_KB = 10240;      // 10 MB por arquivo
    public const MAX_POR_PLANO = 10;

    protected $table = 'edu_diario_plano_anexo';
    protected $primaryKey = 'dax_id';

    const CREATED_AT = 'dax_created_at';
    const UPDATED_AT = 'dax_updated_at';

    protected $fillable = [
        'dax_dpa_id',
        'dax_nome',
        'dax_caminho',
        'dax_mime',
        'dax_tamanho',
        'dax_user_id',
    ];

    protected $casts = [
        'dax_tamanho' => 'integer',
    ];

    protected static function booted(): void
    {
        // Linha removida (anexo ou plano via código) leva o arquivo junto.
        static::deleted(fn (self $a) => Storage::disk(self::DISCO)->delete($a->dax_caminho));
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(DiarioPlanoAula::class, 'dax_dpa_id', 'dpa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dax_user_id', 'id');
    }

    /** Formato para as telas (formulário do plano e modal da coordenação). */
    public function resumo(string $urlBase): array
    {
        return [
            'dax_id'    => $this->dax_id,
            'nome'      => $this->dax_nome,
            'mime'      => $this->dax_mime,
            'tamanho'   => (int) $this->dax_tamanho,
            'eh_imagem' => str_starts_with((string) $this->dax_mime, 'image/'),
            'usuario'   => $this->usuario?->name,
            'criado_em' => $this->dax_created_at?->toIso8601String(),
            'url'       => "{$urlBase}/{$this->dax_id}",
        ];
    }
}
