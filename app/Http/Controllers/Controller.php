<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /**
     * Tenta forceDelete no modelo. Se houver violação de FK (constraint),
     * retorna redirect com erro orientando inativação. Relança demais exceções.
     * Retorna null em caso de sucesso para o caller encadear o redirect de sucesso.
     */
    protected function safeDelete(Model $model): ?RedirectResponse
    {
        try {
            $model->forceDelete();
            return null;
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                return back()->with('error', 'Este registro está vinculado a outros dados e não pode ser excluído. Inative-o caso não queira mais utilizá-lo.');
            }
            throw $e;
        }
    }
}
