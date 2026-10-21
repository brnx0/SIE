<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Diario\AeeAvaliacoesController;
use App\Http\Controllers\Api\V1\Diario\AeeContextoController;
use App\Http\Controllers\Api\V1\Diario\AeeFrequenciaController;
use App\Http\Controllers\Api\V1\Diario\AeePlanosController;
use App\Http\Controllers\Api\V1\Diario\ContextoController;
use App\Http\Controllers\Api\V1\Diario\ConsultasController;
use App\Http\Controllers\Api\V1\Diario\DiagnosticoController;
use App\Http\Controllers\Api\V1\Diario\FrequenciaController;
use App\Http\Controllers\Api\V1\Diario\NotasController;
use App\Http\Controllers\Api\V1\Diario\ParecerController;
use App\Http\Controllers\Api\V1\Diario\PlanosController;
use App\Http\Controllers\Api\V1\Diario\RelatoriosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — App mobile do Diário (professor)
|--------------------------------------------------------------------------
|
| Contrato ESTÁVEL e versionado consumido pelo app Expo (repo separado).
| Autenticação por token (Laravel Sanctum) — guard `sanctum`. CORS restrito
| a `api/v1/*` (config/cors.php). Ver docs/app-diario-mobile/PLANO.md.
|
| Prefixo efetivo: /api/v1  (o "/api" vem do withRouting(api:) em bootstrap/app.php).
|
*/

Route::prefix('v1')->group(function () {
    // ---- Público (sem token) ----
    // throttle:6,1 — anti brute force no login (o rate-limit por login+ip fica no request).
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    // ---- Autenticado por token ----
    Route::middleware('auth:sanctum')->group(function () {
        // Qualquer token válido pode revogar a si mesmo (logout sem gate de role).
        Route::post('logout', [AuthController::class, 'logout']);

        // Área do app: SOMENTE professor / professor_aee (sem bypass de admin).
        Route::middleware('app.access')->group(function () {
            Route::get('me', [AuthController::class, 'me']);

            // Contexto "minhas turmas" do Diário regular (escopo por fun_id).
            Route::prefix('diario/contexto')->group(function () {
                Route::get('anos-letivos', [ContextoController::class, 'anosLetivos']);
                Route::get('escolas', [ContextoController::class, 'escolas']);
                Route::get('turmas', [ContextoController::class, 'turmas']);
                Route::get('disciplinas', [ContextoController::class, 'disciplinas']);
                Route::get('unidades', [ContextoController::class, 'unidades']);
            });

            // Consultas somente-leitura. A média e a completude vêm do
            // CalculoNota — o app não recalcula nada.
            Route::prefix('diario/consultas')->group(function () {
                Route::get('mapa-notas', [ConsultasController::class, 'mapaNotas']);
                Route::get('pendencias', [ConsultasController::class, 'pendencias']);
                Route::get('frequencia', [ConsultasController::class, 'frequencia']);
            });

            // Relatórios de leitura — mesmos números do web (CalculoNota /
            // FaltasAluno), sem o papel timbrado. Autorização por REGÊNCIA na
            // turma, em qualquer disciplina.
            Route::prefix('diario/relatorios')->group(function () {
                Route::get('notas-por-unidade', [RelatoriosController::class, 'notasPorUnidade']);
                Route::get('boletim-avaliativo', [RelatoriosController::class, 'boletimAvaliativo']);
                Route::get('conteudo-ministrado', [RelatoriosController::class, 'conteudoMinistrado']);
            });

            // Parecer descritivo — a granularidade (por aluno / por disciplina)
            // vem da série, e o backend decide onde gravar.
            Route::prefix('diario/parecer')->group(function () {
                Route::get('contexto', [ParecerController::class, 'contexto']);
                Route::post('salvar', [ParecerController::class, 'salvar']);
                // Parecer inicial: sem unidade letiva, antes dos pareceres das unidades.
                Route::get('inicial/contexto', [ParecerController::class, 'contextoInicial']);
                Route::post('inicial/salvar', [ParecerController::class, 'salvarInicial']);
            });

            // Diagnóstico — grade aluno × indicador. Marcar "autonomia"
            // propaga para os períodos seguintes ainda vazios (regra do web).
            Route::prefix('diario/diagnostico')->group(function () {
                Route::get('contexto', [DiagnosticoController::class, 'contexto']);
                Route::post('celula', [DiagnosticoController::class, 'salvarCelula']);
            });

            // Planos de aula — escopo estrito nos planos do próprio professor.
            // O status é do backend: nasce 'pendente' e volta a 'pendente' ao
            // reenviar um plano em correção. A validação é da coordenação.
            Route::prefix('diario/planos')->group(function () {
                Route::get('/', [PlanosController::class, 'index']);
                Route::get('lookups', [PlanosController::class, 'lookups']);
                Route::get('indicadores', [PlanosController::class, 'indicadores']);
                Route::get('{plano}', [PlanosController::class, 'show']);
                Route::post('/', [PlanosController::class, 'store']);
                Route::put('{plano}', [PlanosController::class, 'update']);
                Route::delete('{plano}', [PlanosController::class, 'destroy']);
            });

            // Notas — avaliações e autosave por célula. As regras (soma ≤ 10,
            // nota ≤ valor, exclusão numérica × conceitual) e a média calculada
            // vêm do backend; o app não reimplementa nenhuma delas.
            Route::prefix('diario/notas')->group(function () {
                Route::get('contexto', [NotasController::class, 'contexto']);
                Route::post('avaliacoes', [NotasController::class, 'storeAvaliacao']);
                Route::put('avaliacoes/{avaliacao}', [NotasController::class, 'updateAvaliacao']);
                Route::delete('avaliacoes/{avaliacao}', [NotasController::class, 'destroyAvaliacao']);
                Route::post('nota', [NotasController::class, 'salvarNota']);
            });

            // Frequência (chamada) — escopo estrito pela lotação do professor.
            // As 3 travas de escrita vivem em App\Support\Diario\DiarioEscritaGuard.
            Route::prefix('diario/frequencia')->group(function () {
                // Agenda do dia: os tempos do professor atravessando turmas.
                Route::get('agenda', [FrequenciaController::class, 'agenda']);
                Route::get('contexto', [FrequenciaController::class, 'contexto']);
                Route::post('presenca', [FrequenciaController::class, 'salvarPresenca']);
                Route::post('lote', [FrequenciaController::class, 'salvarLote']);
                Route::post('conteudo', [FrequenciaController::class, 'salvarConteudo']);
                Route::post('aula-nao-executada', [FrequenciaController::class, 'salvarAulaNaoExecutada']);
            });
        });

        // ---- Diário AEE (Fase 4) ----
        //
        // Grupo IRMÃO do `app.access` acima, não filho: o AEE é só para quem
        // tem a role professor_aee — um professor regular não enxerga nada
        // daqui. Espelha o `abortIfNotProfessorAee` do diário AEE web.
        Route::middleware('app.access:professor_aee')->prefix('diario-aee')->group(function () {
            // Turma AEE não tem série nem disciplina: o contexto é sala,
            // horário e dias de atendimento.
            Route::prefix('contexto')->group(function () {
                Route::get('anos-letivos', [AeeContextoController::class, 'anosLetivos']);
                Route::get('escolas', [AeeContextoController::class, 'escolas']);
                Route::get('turmas', [AeeContextoController::class, 'turmas']);
                Route::get('unidades', [AeeContextoController::class, 'unidades']);
            });

            // Chamada por DIA de atendimento — sem tempo, sem disciplina.
            Route::prefix('frequencia')->group(function () {
                Route::get('agenda', [AeeFrequenciaController::class, 'agenda']);
                Route::get('contexto', [AeeFrequenciaController::class, 'contexto']);
                Route::post('presenca', [AeeFrequenciaController::class, 'salvarPresenca']);
                Route::post('lote', [AeeFrequenciaController::class, 'salvarLote']);
                Route::post('conteudo', [AeeFrequenciaController::class, 'salvarConteudo']);
            });

            // Avaliação descritiva: histórico de N registros datados por aluno.
            Route::prefix('avaliacoes')->group(function () {
                Route::get('contexto', [AeeAvaliacoesController::class, 'contexto']);
                Route::post('salvar', [AeeAvaliacoesController::class, 'salvar']);
                Route::delete('{avaliacao}', [AeeAvaliacoesController::class, 'destroy']);
            });

            // Plano AEE: por TURMA (não por aluno, não por série).
            Route::prefix('planos')->group(function () {
                Route::get('/', [AeePlanosController::class, 'index']);
                Route::get('lookups', [AeePlanosController::class, 'lookups']);
                Route::get('{plano}', [AeePlanosController::class, 'show']);
                Route::post('/', [AeePlanosController::class, 'store']);
                Route::put('{plano}', [AeePlanosController::class, 'update']);
                Route::delete('{plano}', [AeePlanosController::class, 'destroy']);
            });
        });
    });
});
