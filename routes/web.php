<?php

use App\Http\Controllers\Aluno\AlunoController;
use App\Http\Controllers\Api\BairroController;
use App\Http\Controllers\Api\GerenciaRegionalController;
use App\Http\Controllers\Api\MunicipioController;
use App\Http\Controllers\Api\SerieController as SerieApiController;
use App\Http\Controllers\Escola\EscolaCensoController;
use App\Http\Controllers\Escola\EscolaSegmentoController;
use App\Http\Controllers\Segmento\SegmentoController;
use App\Http\Controllers\Serie\SerieController;
use App\Http\Controllers\Escola\EscolaController;
use App\Http\Controllers\Api\CargoController as CargoApiController;
use App\Http\Controllers\Api\EscolaController as EscolaApiController;
use App\Http\Controllers\Funcionario\FuncionarioAdmissaoController;
use App\Http\Controllers\Funcionario\FuncionarioController;
use App\Http\Controllers\Funcionario\FuncionarioLotacaoController;
use App\Http\Controllers\Parametro\AnoLetivoController;
use App\Http\Controllers\Parametro\ParametroController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('users', UsersController::class)->except(['show']);

    Route::resource('alunos', AlunoController::class)->except(['show']);
    Route::resource('escolas', EscolaController::class)->except(['show']);
    Route::prefix('escolas/{escola}/censo')->name('escolas.censo.')->group(function () {
        Route::post('/', [EscolaCensoController::class, 'store'])->name('store');
        Route::get('/{censoEscolar}/edit', [EscolaCensoController::class, 'edit'])->name('edit');
        Route::get('/{censoEscolar}', [EscolaCensoController::class, 'show'])->name('show');
        Route::put('/{censoEscolar}', [EscolaCensoController::class, 'update'])->name('update');
    });
    Route::prefix('escolas/{escola}/segmentos')->name('escolas.segmentos.')->group(function () {
        Route::post('/', [EscolaSegmentoController::class, 'store'])->name('store');
        Route::put('/{esg}', [EscolaSegmentoController::class, 'update'])->name('update');
        Route::delete('/{esg}', [EscolaSegmentoController::class, 'destroy'])->name('destroy');
    });
    Route::resource('segmentos', SegmentoController::class)->except(['show']);
    Route::resource('series', SerieController::class)->except(['show'])->parameters(['series' => 'serie']);
    Route::resource('funcionarios', FuncionarioController::class)->except(['show']);
    Route::prefix('funcionarios/{funcionario}/admissoes')->name('funcionarios.admissoes.')->group(function () {
        Route::post('/', [FuncionarioAdmissaoController::class, 'store'])->name('store');
        Route::put('/{admissao}', [FuncionarioAdmissaoController::class, 'update'])->name('update');
        Route::delete('/{admissao}', [FuncionarioAdmissaoController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('funcionarios/{funcionario}/admissoes/{admissao}/lotacoes')->name('funcionarios.admissoes.lotacoes.')->group(function () {
        Route::post('/', [FuncionarioLotacaoController::class, 'store'])->name('store');
        Route::put('/{lotacao}', [FuncionarioLotacaoController::class, 'update'])->name('update');
        Route::delete('/{lotacao}', [FuncionarioLotacaoController::class, 'destroy'])->name('destroy');
    });

    Route::get('api/series', [SerieApiController::class, 'bySegmento'])->name('api.series.bySegmento');
    Route::get('api/municipios', [MunicipioController::class, 'search'])->name('api.municipios.search');
    Route::get('api/municipios/by-ibge/{codigo}', [MunicipioController::class, 'byIbge'])->name('api.municipios.byIbge');
    Route::get('api/bairros', [BairroController::class, 'search'])->name('api.bairros.search');
    Route::post('api/bairros', [BairroController::class, 'store'])->name('api.bairros.store');
    Route::get('api/gerencias', [GerenciaRegionalController::class, 'search'])->name('api.gerencias.search');
    Route::get('api/cargos', [CargoApiController::class, 'search'])->name('api.cargos.search');
    Route::get('api/escolas', [EscolaApiController::class, 'search'])->name('api.escolas.search');

    Route::middleware('can:admin')->group(function () {
        Route::get('parametros', [ParametroController::class, 'edit'])->name('parametros.edit');
        Route::put('parametros', [ParametroController::class, 'update'])->name('parametros.update');

        Route::post('parametros/anos-letivos', [AnoLetivoController::class, 'store'])->name('parametros.anos-letivos.store');
        Route::put('parametros/anos-letivos/{anoLetivo}', [AnoLetivoController::class, 'update'])
            ->scopeBindings()->name('parametros.anos-letivos.update');
        Route::delete('parametros/anos-letivos/{anoLetivo}', [AnoLetivoController::class, 'destroy'])
            ->scopeBindings()->name('parametros.anos-letivos.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
