<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MunicipioSeeder::class);
        $this->call(PovoIndigenaSeeder::class);
        $this->call(ParametroEntidadeSeeder::class);
        $this->call(SegmentoSeeder::class);
        $this->call(SerieSeeder::class);
        $this->call(CargoSeeder::class);
        $this->call(AreaConhecimentoSeeder::class);
        $this->call(DisciplinaSeeder::class);
        $this->call(SerieIndicadorSeeder::class);
        $this->call(TipoMovimentacaoSeeder::class);
        $this->call(InstrumentoAvaliativoSeeder::class);
        $this->call(ConceitoSeeder::class);
        $this->call(MotivoBaixaFrequenciaSeeder::class);
        $this->call(TelaPermissaoSeeder::class);

        // Importação do Censo — de-para (códigos INEP). Mapeamento p/ série/cargo/
        // disciplina é por base (feito na tela de importação). Ver docs/regras/importacao_censo.md.
        $this->call(CensoEtapaDeparaSeeder::class);
        $this->call(CensoFuncaoDeparaSeeder::class);
        $this->call(CensoDisciplinaDeparaSeeder::class);

        $admin = User::updateOrCreate(
            ['email' => 'admin@sie.local'],
            [
                'name' => 'Administrador',
                'login' => 'admin',
                'password' => Hash::make('123456'),
                'active' => true,
            ],
        );

        $admin->syncRoles(['admin']);
    }
}
