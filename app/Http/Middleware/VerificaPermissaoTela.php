<?php

namespace App\Http\Middleware;

use App\Support\PermissaoTela;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificaPermissaoTela
{
    /**
     * Aplica a matriz de permissão por tela:
     * - nível 0 → 403 (sem acesso)
     * - nível 2 + verbo de escrita → 403 (somente leitura)
     * - nível 1 (ou rota não mapeada) → segue
     *
     * APIs (/api/*) ficam fora: são infra cross-tela. A escrita real é
     * barrada nas rotas de página (store/update/destroy/aninhadas).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (str_starts_with($request->path(), 'api/')) {
            return $next($request);
        }

        $nivel = PermissaoTela::nivelDaRequest($request->user(), $request);

        if ($nivel === PermissaoTela::SEM_ACESSO) {
            abort(403, 'Acesso negado: perfil sem permissão para esta tela.');
        }

        if ($nivel === PermissaoTela::VISUALIZAR && ! $this->ehLeitura($request)) {
            // Exceção: Secretaria Escolar pode editar abas de composição da turma
            // (professores/horários/alunos), mantendo Dados Gerais somente-leitura.
            if (PermissaoTela::permiteComposicaoTurma($request->user(), $request)) {
                return $next($request);
            }
            abort(403, 'Acesso somente leitura: perfil não pode alterar esta tela.');
        }

        return $next($request);
    }

    private function ehLeitura(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }
}
