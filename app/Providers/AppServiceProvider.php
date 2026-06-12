<?php

namespace App\Providers;

use App\Models\Diario\DiarioPlanoAula;
use App\Models\Funcionario\Funcionario;
use App\Models\Funcionario\FuncionarioAdmissao;
use App\Models\Funcionario\FuncionarioLotacao;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn ($user) => $user->role === 'admin');

        Route::model('funcionario', Funcionario::class);
        Route::model('admissao', FuncionarioAdmissao::class);
        Route::model('lotacao', FuncionarioLotacao::class);
        Route::model('plano', DiarioPlanoAula::class);
    }
}
