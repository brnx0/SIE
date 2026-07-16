<?php

use App\Support\PermissaoTela;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gestor Secretaria passa a criar/editar Planos de Aula e Planos de Aula AEE
 * (antes: nível 2, só visualizava — e a tela ainda oferecia o botão "Novo plano").
 *
 * UPDATE direcionado em vez de rodar o TelaPermissaoSeeder: o seeder reescreve a
 * matriz inteira e apagaria ajustes feitos pela tela de Permissões.
 */
return new class extends Migration
{
    private const ROTAS = ['/diario/planos', '/diario/planos-aee'];

    public function up(): void
    {
        $this->definirNivel(PermissaoTela::EDITAR);
    }

    public function down(): void
    {
        $this->definirNivel(PermissaoTela::VISUALIZAR);
    }

    private function definirNivel(int $nivel): void
    {
        $telaIds = DB::table('cfg_tela')->whereIn('tla_rota', self::ROTAS)->pluck('tla_id');

        if ($telaIds->isEmpty()) {
            return;
        }

        DB::table('cfg_tela_permissao')
            ->whereIn('tlp_tla_id', $telaIds)
            ->where('tlp_role', 'gestor_secretaria')
            ->update(['tlp_nivel' => $nivel, 'tlp_updated_at' => now()]);

        PermissaoTela::clearCache();
    }
};
