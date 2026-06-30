<?php

namespace App\Support;

use App\Models\Parametro\Tela;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PermissaoTela
{
    private const CACHE_KEY = 'permissao_tela_matriz';

    /**
     * Nível: 0 = sem acesso, 1 = ver+editar, 2 = só visualizar.
     */
    public const SEM_ACESSO = 0;
    public const EDITAR = 1;
    public const VISUALIZAR = 2;

    /**
     * Matriz completa em cache: [ rota => [ role => nivel ] ].
     */
    public static function matriz(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            $matriz = [];
            foreach (Tela::with('permissoes')->get() as $tela) {
                $linha = [];
                foreach ($tela->permissoes as $p) {
                    $linha[$p->tlp_role] = (int) $p->tlp_nivel;
                }
                $matriz[$tela->tla_rota] = $linha;
            }
            return $matriz;
        });
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Rotas de tela registradas (chaves da matriz).
     */
    public static function rotasRegistradas(): array
    {
        return array_keys(self::matriz());
    }

    /**
     * Nível efetivo do usuário para uma rota de tela (já resolvida).
     * Admin → sempre 1. Tela não registrada → 1 (não gateada).
     */
    public static function nivelParaRota(?User $user, string $rota): int
    {
        if ($user && $user->isAdmin()) return self::EDITAR;

        $matriz = self::matriz();
        if (! array_key_exists($rota, $matriz)) return self::EDITAR;
        if (! $user) return self::SEM_ACESSO;

        $linha = $matriz[$rota];
        $nivel = self::SEM_ACESSO;
        foreach ($user->roles as $role) {
            $nivel = max($nivel, $linha[$role] ?? self::SEM_ACESSO);
        }
        return $nivel;
    }

    /**
     * Resolve o path de uma request para a tela "dona" via longest-prefix
     * por fronteira de segmento. Ex.: /turmas/5/edit → /turmas.
     */
    public static function resolverTela(string $path): ?string
    {
        $path = '/' . trim(self::soPath($path), '/');

        $melhor = null;
        foreach (self::rotasRegistradas() as $rota) {
            if ($path === $rota || str_starts_with($path, rtrim($rota, '/') . '/')) {
                if ($melhor === null || strlen($rota) > strlen($melhor)) {
                    $melhor = $rota;
                }
            }
        }
        return $melhor;
    }

    /**
     * Nível efetivo do usuário para a request atual (resolve a tela).
     * Path não mapeado → 1 (liberado).
     */
    public static function nivelDaRequest(?User $user, Request $request): int
    {
        $rota = self::resolverTela($request->path());
        if ($rota === null) return self::EDITAR;
        return self::nivelParaRota($user, $rota);
    }

    /**
     * Telas de turma onde a Secretaria Escolar pode editar as abas de COMPOSIÇÃO
     * (professores, horários, alunos, atendimentos, itens) mesmo tendo nível 2 na
     * tela — apenas a aba "Dados Gerais" (recurso base) permanece somente-leitura.
     */
    public const TELAS_COMPOSICAO_TURMA = ['/turmas', '/turmas-aee', '/turmas-atividade'];

    /**
     * Roles que podem editar as abas de composição da turma (mas não Dados Gerais).
     */
    public const ROLES_COMPOSICAO_TURMA = ['secretaria_escola', 'diretor'];

    /**
     * True quando a request é uma escrita em SUB-recurso de turma e o usuário é
     * uma das roles de composição. Sub-recurso = caminho mais profundo que
     * `recurso/{id}` (≥ 3 segmentos: ex. `turmas/5/professores`). O recurso base
     * (`turmas/{id}`, POST `turmas`) NÃO entra — Dados Gerais segue bloqueado.
     */
    public static function permiteComposicaoTurma(?User $user, Request $request): bool
    {
        if (! $user || ! $user->hasAnyRole(self::ROLES_COMPOSICAO_TURMA)) return false;

        $tela = self::resolverTela($request->path());
        if (! in_array($tela, self::TELAS_COMPOSICAO_TURMA, true)) return false;

        $segs = array_values(array_filter(explode('/', $request->path()), fn ($s) => $s !== ''));
        return count($segs) >= 3;
    }

    /**
     * Mapa { rota => nivelEfetivo } das telas com nível ≥ 1 (consumo frontend/sidebar).
     */
    public static function mapaParaUsuario(?User $user): array
    {
        if (! $user) return [];

        $admin = $user->isAdmin();
        $roles = $user->roles;
        $mapa = [];

        foreach (self::matriz() as $rota => $linha) {
            if ($admin) {
                $mapa[$rota] = self::EDITAR;
                continue;
            }
            $nivel = self::SEM_ACESSO;
            foreach ($roles as $role) {
                $nivel = max($nivel, $linha[$role] ?? self::SEM_ACESSO);
            }
            if ($nivel >= self::EDITAR) {
                $mapa[$rota] = $nivel;
            }
        }
        return $mapa;
    }

    private static function soPath(string $url): string
    {
        $p = parse_url($url, PHP_URL_PATH);
        return $p === false || $p === null ? $url : $p;
    }
}
