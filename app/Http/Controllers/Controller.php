<?php

namespace App\Http\Controllers;

use App\Support\PermissaoTela;
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
     * Nível efetivo do usuário na tela da request atual (matriz de permissão).
     * 0 = sem acesso, 1 = ver+editar, 2 = só visualizar.
     */
    protected function nivelTela(?Request $request = null): int
    {
        $request ??= request();
        return PermissaoTela::nivelDaRequest($request->user(), $request);
    }

    /**
     * True quando o usuário está em modo somente-visualização nesta tela.
     * Use para pular filtros de autoria (ex.: "só meus registros") e liberar
     * a leitura completa a perfis de supervisão. A escrita já é barrada pelo
     * middleware `tela` (nível 2 + verbo de escrita → 403).
     */
    protected function soVisualizacaoTela(?Request $request = null): bool
    {
        return $this->nivelTela($request) === PermissaoTela::VISUALIZAR;
    }

    /**
     * "Vê tudo" nos lookups do diário (escolas/turmas): admin/gestor global, OU
     * perfil só-visualização (nível 2) na tela informada — ex.: secretaria vendo
     * o Diário. Usa a rota EXPLÍCITA da tela porque os lookups vivem em `api/*`.
     */
    protected function veTudoNoDiario($user, string $rota): bool
    {
        return $user->isGestorGlobal()
            || PermissaoTela::nivelParaRota($user, $rota) === PermissaoTela::VISUALIZAR;
    }

    /**
     * Guard de LEITURA das telas do Diário: libera o professor lotado, admin/gestor
     * global e os perfis de supervisão (secretaria/coordenação) que têm a tela como
     * só-visualização na matriz.
     *
     * Só serve para leitura. Os endpoints do diário vivem em `api/*`, que o middleware
     * `tela` ignora de propósito, então cada endpoint de escrita mantém o seu próprio
     * guard de professor — não dá para confiar na matriz para barrar escrita aqui.
     */
    protected function abortIfNaoPodeVerDiario(string $rota = '/diario', string $role = 'professor'): void
    {
        $user = request()->user();
        abort_unless($user, 403, 'Acesso restrito.');

        if ($user->isAdmin() || $this->veTudoNoDiario($user, $rota)) {
            return;
        }

        abort_unless($user->hasRole($role), 403, 'Acesso restrito a professores.');
    }

    /**
     * True quando o usuário apenas consulta o Diário e não lança nada.
     *
     * Checa o ROLE, e não o users.fun_id: um usuário de secretaria pode estar ligado
     * a um funcionário que também é professor, e nesse caso os filtros por fun_id o
     * tratariam como regente da turma.
     */
    protected function soConsultaDiario(string $role = 'professor'): bool
    {
        $user = request()->user();

        return $user && ! $user->isAdmin() && ! $user->hasRole($role);
    }

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
