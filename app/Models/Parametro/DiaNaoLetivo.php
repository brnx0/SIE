<?php

namespace App\Models\Parametro;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DiaNaoLetivo extends Model
{
    use \App\Models\Concerns\Auditable;
    protected $table = 'cfg_dia_nao_letivo';
    protected $primaryKey = 'dnl_id';

    const CREATED_AT = 'dnl_created_at';
    const UPDATED_AT = 'dnl_updated_at';

    protected $fillable = [
        'dnl_anl_id',
        'dnl_dt_dia',
        'dnl_dt_fim',
        'dnl_descricao',
        'dnl_created_by_id',
        'dnl_updated_by_id',
    ];

    protected $casts = [
        'dnl_dt_dia' => 'date',
        'dnl_dt_fim' => 'date',
    ];

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class, 'dnl_anl_id', 'anl_id');
    }

    /**
     * Datas (Y-m-d) marcadas como NÃO letivas de um ano, dentro de [dtInicio, dtFim].
     *
     * Um registro pode ser um dia único (dnl_dt_fim null) ou um período contínuo
     * (feriado prolongado): aqui os intervalos são EXPANDIDOS dia a dia e recortados
     * à janela pedida, para o consumidor só precisar de um `in_array($dt, ...)`.
     *
     * @return list<string>
     */
    public static function datasNoPeriodo(int $anlId, string $dtInicio, string $dtFim): array
    {
        $registros = static::query()
            ->where('dnl_anl_id', $anlId)
            // Sobreposição com a janela: começo <= fim da janela E fim(coalesce) >= início.
            ->whereDate('dnl_dt_dia', '<=', $dtFim)
            ->whereRaw('COALESCE(dnl_dt_fim, dnl_dt_dia) >= ?', [$dtInicio])
            ->get(['dnl_dt_dia', 'dnl_dt_fim']);

        $datas = [];
        foreach ($registros as $r) {
            $ini = Carbon::parse($r->dnl_dt_dia);
            $fim = $r->dnl_dt_fim ? Carbon::parse($r->dnl_dt_fim) : $ini->copy();
            for ($d = $ini->copy(); $d->lte($fim); $d->addDay()) {
                $s = $d->toDateString();
                if ($s >= $dtInicio && $s <= $dtFim) {
                    $datas[$s] = true; // chave dedup: períodos podem se sobrepor
                }
            }
        }

        ksort($datas);

        return array_keys($datas);
    }

    /** Os anl_ids (dentre os informados) que marcam `$dt` como não letivo. */
    public static function anosComDiaNaoLetivo(array $anlIds, string $dt): array
    {
        if (! $anlIds) {
            return [];
        }

        return static::query()
            ->whereIn('dnl_anl_id', $anlIds)
            ->whereDate('dnl_dt_dia', '<=', $dt)
            ->whereRaw('COALESCE(dnl_dt_fim, dnl_dt_dia) >= ?', [$dt])
            ->pluck('dnl_anl_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dnl_created_by_id');
    }
}
