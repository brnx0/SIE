<?php

use App\Http\Controllers\Transporte\VeiculoController;
use App\Http\Controllers\Transporte\MotoristaController;
use App\Http\Controllers\Transporte\MonitorController;
use App\Http\Controllers\Transporte\InstituicaoController;
use App\Http\Controllers\Transporte\UsuarioExternoController;
use App\Http\Controllers\Transporte\RotaController;
use App\Http\Controllers\Transporte\RotaPontoController;
use App\Http\Controllers\Transporte\RotaPassageiroController;
use App\Http\Controllers\Transporte\DiarioBordoController;
use App\Http\Controllers\Transporte\SimuladorController;
use App\Http\Controllers\Api\Transporte\InstituicaoController as InstituicaoApiController;
use App\Http\Controllers\Api\Transporte\GeoController as TransporteGeoController;
use App\Http\Controllers\Api\Transporte\VeiculoController as VeiculoApiController;
use App\Http\Controllers\Api\Transporte\MotoristaController as MotoristaApiController;
use App\Http\Controllers\Api\Transporte\MonitorController as MonitorApiController;
use App\Http\Controllers\Api\Transporte\RotaController as RotaApiController;
use App\Http\Controllers\Api\Transporte\UsuarioExternoController as UsuarioExternoApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do módulo Transporte Escolar
|--------------------------------------------------------------------------
| Incluído por routes/web.php dentro do grupo ['auth', 'verified', 'tela'].
| O acesso às telas /transporte/* é governado pela matriz cfg_tela
| (resolverTela por longest-prefix); os lookups api/transporte/* seguem o
| padrão do projeto (incluirIds + filtroAtivoOuIncluso).
*/

Route::prefix('transporte')->name('transporte.')->group(function () {
    // Cadastros (Fase 1)
    Route::get('veiculos/export', [VeiculoController::class, 'export'])->name('veiculos.export');
    Route::resource('veiculos', VeiculoController::class)->except(['show']);

    Route::get('motoristas/export', [MotoristaController::class, 'export'])->name('motoristas.export');
    Route::resource('motoristas', MotoristaController::class)->except(['show']);

    Route::get('monitores/export', [MonitorController::class, 'export'])->name('monitores.export');
    Route::resource('monitores', MonitorController::class)
        ->parameters(['monitores' => 'monitor'])
        ->except(['show']);

    Route::get('instituicoes/export', [InstituicaoController::class, 'export'])->name('instituicoes.export');
    Route::resource('instituicoes', InstituicaoController::class)
        ->parameters(['instituicoes' => 'instituicao'])
        ->except(['show']);

    Route::get('usuarios/export', [UsuarioExternoController::class, 'export'])->name('usuarios.export');
    Route::resource('usuarios', UsuarioExternoController::class)
        ->parameters(['usuarios' => 'usuario'])
        ->except(['show']);

    // Rotas + filhos (Fase 2 — padrão turmas/{turma}/professores)
    Route::get('rotas/export', [RotaController::class, 'export'])->name('rotas.export');
    Route::resource('rotas', RotaController::class)->except(['show']);
    Route::post('rotas/{rota}/pontos', [RotaPontoController::class, 'store'])->name('rotas.pontos.store');
    Route::put('rotas/{rota}/pontos-reordenar', [RotaPontoController::class, 'reordenar'])->name('rotas.pontos.reordenar');
    Route::put('rotas/{rota}/pontos/{ponto}', [RotaPontoController::class, 'update'])->name('rotas.pontos.update');
    Route::delete('rotas/{rota}/pontos/{ponto}', [RotaPontoController::class, 'destroy'])->name('rotas.pontos.destroy');
    Route::post('rotas/{rota}/passageiros', [RotaPassageiroController::class, 'store'])->name('rotas.passageiros.store');
    Route::delete('rotas/{rota}/passageiros/{passageiro}', [RotaPassageiroController::class, 'destroy'])->name('rotas.passageiros.destroy');

    // Diário de bordo (Fase 3)
    Route::get('diario-bordo', [DiarioBordoController::class, 'index'])->name('diario-bordo.index');
    Route::post('diario-bordo', [DiarioBordoController::class, 'store'])->name('diario-bordo.store');
    Route::get('diario-bordo/{diario}/edit', [DiarioBordoController::class, 'edit'])->name('diario-bordo.edit');
    Route::put('diario-bordo/{diario}', [DiarioBordoController::class, 'update'])->name('diario-bordo.update');
    Route::delete('diario-bordo/{diario}', [DiarioBordoController::class, 'destroy'])->name('diario-bordo.destroy');
    Route::put('diario-bordo/{diario}/chamada', [DiarioBordoController::class, 'chamada'])->name('diario-bordo.chamada');
    Route::put('diario-bordo/{diario}/sincronizar-chamada', [DiarioBordoController::class, 'sincronizarChamada'])->name('diario-bordo.sincronizar-chamada');
    Route::post('diario-bordo/{diario}/ocorrencias', [DiarioBordoController::class, 'storeOcorrencia'])->name('diario-bordo.ocorrencias.store');
    Route::delete('diario-bordo/{diario}/ocorrencias/{ocorrencia}', [DiarioBordoController::class, 'destroyOcorrencia'])->name('diario-bordo.ocorrencias.destroy');

    // Simulador georreferenciado (Fase 5)
    Route::get('simulador', [SimuladorController::class, 'index'])->name('simulador');
    Route::put('simulador/{rota}/efetivar', [SimuladorController::class, 'efetivar'])->name('simulador.efetivar');
});

// Lookups de API (padrão do projeto: em routes web sob prefixo api/)
Route::get('api/transporte/instituicoes/search', [InstituicaoApiController::class, 'search'])->name('api.transporte.instituicoes.search');
Route::get('api/transporte/veiculos/search', [VeiculoApiController::class, 'search'])->name('api.transporte.veiculos.search');
Route::get('api/transporte/motoristas/search', [MotoristaApiController::class, 'search'])->name('api.transporte.motoristas.search');
Route::get('api/transporte/monitores/search', [MonitorApiController::class, 'search'])->name('api.transporte.monitores.search');
Route::get('api/transporte/rotas/search', [RotaApiController::class, 'search'])->name('api.transporte.rotas.search');
Route::get('api/transporte/usuarios/search', [UsuarioExternoApiController::class, 'search'])->name('api.transporte.usuarios.search');

// Proxies de georreferenciamento (Fase 5) — rate-limit por respeito aos serviços públicos.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('api/transporte/geo/geocode', [TransporteGeoController::class, 'geocode'])->name('api.transporte.geo.geocode');
    Route::post('api/transporte/geo/rotear', [TransporteGeoController::class, 'rotear'])->name('api.transporte.geo.rotear');
});
