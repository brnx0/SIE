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
        $this->call(ParametroEntidadeSeeder::class);
        $this->call(SegmentoSeeder::class);
        $this->call(SerieSeeder::class);
        $this->call(CargoSeeder::class);
        $this->call(AreaConhecimentoSeeder::class);
        $this->call(TipoMovimentacaoSeeder::class);

        User::updateOrCreate(
            ['email' => 'admin@sie.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('123456'),
                'role' => 'admin',
                'active' => true,
            ],
        );

        User::factory()->count(8)->create();
    }
}
