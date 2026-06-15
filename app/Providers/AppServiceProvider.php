<?php

namespace App\Providers;

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
        Gate::define('admin', fn ($user) => $user->isAdmin());

        Route::model('funcionario', Funcionario::class);
        Route::model('admissao', FuncionarioAdmissao::class);
        Route::model('lotacao', FuncionarioLotacao::class);
        // NÃO usar Route::model('plano', ...): o parâmetro {plano} é compartilhado
        // pelas rotas de plano de aula regular (DiarioPlanoAula) E de plano AEE
        // (DiarioPlanoAee). Um binding explícito forçaria TODO {plano} para um único
        // model, causando 404 nas rotas AEE (buscava na tabela errada). O binding
        // implícito resolve cada rota pelo type-hint do controller.
    }
}
