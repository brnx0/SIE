<?php

namespace App\Http\Controllers;

use App\Models\Parametro\Tela;
use App\Models\Parametro\TelaPermissao;
use App\Models\User;
use App\Support\PermissaoTela;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PermissaoTelaController extends Controller
{
    public function index(): Response
    {
        $telas = Tela::with('permissoes')
            ->orderBy('tla_ordem')
            ->get()
            ->map(fn (Tela $t) => [
                'tla_id'    => $t->tla_id,
                'tla_rota'  => $t->tla_rota,
                'tla_nome'  => $t->tla_nome,
                'tla_grupo' => $t->tla_grupo,
                'niveis'    => $t->permissoes
                    ->mapWithKeys(fn ($p) => [$p->tlp_role => (int) $p->tlp_nivel])
                    ->all(),
            ]);

        return Inertia::render('permissoes/Index', [
            'telas' => $telas,
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'permissoes'             => ['required', 'array'],
            'permissoes.*.tla_id'    => ['required', 'integer', 'exists:cfg_tela,tla_id'],
            'permissoes.*.niveis'    => ['required', 'array'],
        ]);

        $validRoles = array_keys(User::ROLES);

        DB::transaction(function () use ($data, $validRoles) {
            foreach ($data['permissoes'] as $linha) {
                foreach ($linha['niveis'] as $role => $nivel) {
                    if (! in_array($role, $validRoles, true)) continue;
                    $nivel = (int) $nivel;
                    if (! in_array($nivel, [0, 1, 2], true)) continue;

                    TelaPermissao::updateOrCreate(
                        ['tlp_tla_id' => (int) $linha['tla_id'], 'tlp_role' => $role],
                        ['tlp_nivel' => $nivel],
                    );
                }
            }
        });

        PermissaoTela::clearCache();

        return back()->with('success', 'Permissões atualizadas com sucesso.');
    }
}
