<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Trilha de auditoria padrão do projeto: registra quem alterou, o quê e quando.
 *
 * - Loga apenas os campos `$fillable` do model (evita ruído de colunas técnicas).
 * - `logOnlyDirty`: em updates, guarda só o que mudou (valores antigo → novo).
 * - `dontSubmitEmptyLogs`: não grava update sem alteração real.
 * - Ignora campos sensíveis (senha/token).
 * - `useLogName`: nome do log = nome curto do model (ex.: "Aluno", "Matricula").
 *
 * O "quem" (causer) é resolvido automaticamente pelo usuário autenticado.
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['password', 'remember_token', 'senha'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(class_basename(static::class));
    }
}
