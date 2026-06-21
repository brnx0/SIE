<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    /**
     * Data de saída (tma_dt_saida) do aluno na turma — string Y-m-d — ou null
     * se o aluno está ativo / sem saída registrada. Saída = tma_tas_cod_saida preenchido.
     */
    protected function dataSaidaAluno(int $turId, int $alnId): ?string
    {
        $dt = DB::table('edu_turma_aluno')
            ->where('tma_tur_id', $turId)
            ->where('tma_aln_id', $alnId)
            ->whereNull('tma_deleted_at')
            ->whereNotNull('tma_tas_cod_saida')
            ->value('tma_dt_saida');

        return $dt ? Carbon::parse($dt)->toDateString() : null;
    }

    /**
     * Bloqueia lançamento no diário quando a data de referência (dia da aula,
     * data da avaliação ou início do período) for posterior à saída do aluno.
     */
    protected function assertSemSaidaNaData(int $turId, int $alnId, string $dtReferencia): void
    {
        $saida = $this->dataSaidaAluno($turId, $alnId);
        if ($saida && Carbon::parse($dtReferencia)->toDateString() > $saida) {
            abort(422, 'Aluno saiu da turma em '.Carbon::parse($saida)->format('d/m/Y').'. Lançamentos após a saída estão bloqueados.');
        }
    }
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

    /**
     * Lê e normaliza o parâmetro `incluir_ids` (string CSV ou array) → array<int>.
     * Usado em lookups para garantir que IDs já vinculados a um registro sendo
     * editado continuem visíveis mesmo se o item-fonte estiver inativo.
     */
    protected function incluirIds(Request $request, string $param = 'incluir_ids'): array
    {
        $raw = $request->input($param, []);
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(array_map('intval', $list))));
    }

    /**
     * Aplica filtro `coluna_ativo = true OR pk IN (incluir_ids)` em uma query de lookup.
     *
     * Boas práticas para lookups consumidos por formulários:
     * - Lista padrão = apenas registros ativos
     * - Edit de registro existente = passar `incluir_ids` com a(s) FK(s) já salva(s)
     *   para que apareçam mesmo se o item-fonte foi inativado depois.
     */
    protected function filtroAtivoOuIncluso(Builder $query, string $colunaAtivo, string $colunaPk, array $incluirIds): Builder
    {
        return $query->where(function ($w) use ($colunaAtivo, $colunaPk, $incluirIds) {
            $w->where($colunaAtivo, true);
            if (! empty($incluirIds)) {
                $w->orWhereIn($colunaPk, $incluirIds);
            }
        });
    }
}
