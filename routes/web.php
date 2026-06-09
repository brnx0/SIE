<?php

use App\Http\Controllers\Aluno\AlunoController;
use App\Http\Controllers\Aluno\MovimentacaoController;
use App\Http\Controllers\Relatorio\AlunosPorTurmaRelatorioController;
use App\Http\Controllers\Relatorio\RelatorioCentralController;
use App\Http\Controllers\Api\AlunoSearchController;
use App\Http\Controllers\Api\BairroController;
use App\Http\Controllers\Api\MatriculaTurmaController;
use App\Http\Controllers\Api\TurmaAlunoController;
use App\Http\Controllers\Api\TurmaAeeAlunoController;
use App\Http\Controllers\Api\TurmaAeeAtendimentoController;
use App\Http\Controllers\Api\TurmaAtividadeAlunoController;
use App\Http\Controllers\Api\TurmaAtividadeItemController;
use App\Http\Controllers\Matricula\MatriculaController;
use App\Http\Controllers\Api\DisciplinaController as DisciplinaApiController;
use App\Http\Controllers\Api\GerenciaRegionalController;
use App\Http\Controllers\Api\MunicipioController;
use App\Http\Controllers\Api\SegmentoController as SegmentoApiController;
use App\Http\Controllers\Api\SerieController as SerieApiController;
use App\Http\Controllers\Disciplina\DisciplinaController;
use App\Http\Controllers\Serie\SerieIndicadorController;
use App\Http\Controllers\Turma\TurmaController;
use App\Http\Controllers\Turma\TurmaAeeController;
use App\Http\Controllers\Turma\TurmaAeeProfessorController;
use App\Http\Controllers\Turma\TurmaAtividadeController;
use App\Http\Controllers\Turma\TurmaAtividadeProfessorController;
use App\Http\Controllers\Turma\TurmaHorarioController;
use App\Http\Controllers\Turma\TurmaProfessorApoioController;
use App\Http\Controllers\Turma\TurmaProfessorController;
use App\Http\Controllers\Escola\EscolaCensoController;
use App\Http\Controllers\Escola\EscolaSegmentoController;
use App\Http\Controllers\Segmento\SegmentoController;
use App\Http\Controllers\Serie\SerieController;
use App\Http\Controllers\Escola\EscolaController;
use App\Http\Controllers\Api\CargoController as CargoApiController;
use App\Http\Controllers\Api\EscolaController as EscolaApiController;
use App\Http\Controllers\Api\FuncionarioController as FuncionarioApiController;
use App\Http\Controllers\Funcionario\FuncionarioAdmissaoController;
use App\Http\Controllers\Funcionario\FuncionarioController;
use App\Http\Controllers\Funcionario\FuncionarioLotacaoController;
use App\Http\Controllers\Parametro\AnoLetivoController;
use App\Http\Controllers\Parametro\AtendimentoAeeController;
use App\Http\Controllers\Parametro\AtividadeController;
use App\Http\Controllers\Parametro\GradeDisciplinarController;
use App\Http\Controllers\Parametro\GradeHorarioController;
use App\Http\Controllers\Parametro\ParametroController;
use App\Http\Controllers\Parametro\UnidadeController;
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

    Route::get('users/export', [UsersController::class, 'export'])->name('users.export');
    Route::resource('users', UsersController::class)->except(['show']);

    Route::get('alunos/export', [AlunoController::class, 'export'])->name('alunos.export');
    Route::resource('alunos', AlunoController::class)->except(['show']);
    Route::get('escolas/export', [EscolaController::class, 'export'])->name('escolas.export');
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
    Route::get('segmentos/export', [SegmentoController::class, 'export'])->name('segmentos.export');
    Route::get('segmentos/export', [SegmentoController::class, 'export'])->name('segmentos.export');
    Route::resource('segmentos', SegmentoController::class)->except(['show']);
    Route::get('series/export', [SerieController::class, 'export'])->name('series.export');
    Route::resource('series', SerieController::class)->except(['show'])->parameters(['series' => 'serie']);
    Route::get('funcionarios/export', [FuncionarioController::class, 'export'])->name('funcionarios.export');
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

    Route::get('turmas/export', [TurmaController::class, 'export'])->name('turmas.export');
    Route::resource('turmas', TurmaController::class)->except(['show']);
    Route::prefix('turmas/{turma}/professores')->name('turmas.professores.')->group(function () {
        Route::post('/', [TurmaProfessorController::class, 'store'])->name('store');
        Route::put('/{turmaProfessor}', [TurmaProfessorController::class, 'update'])->name('update');
        Route::delete('/{turmaProfessor}', [TurmaProfessorController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('turmas/{turma}/professores-apoio')->name('turmas.professores-apoio.')->group(function () {
        Route::post('/', [TurmaProfessorApoioController::class, 'store'])->name('store');
        Route::put('/{apoio}', [TurmaProfessorApoioController::class, 'update'])->name('update');
        Route::delete('/{apoio}', [TurmaProfessorApoioController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('turmas/{turma}/horarios')->name('turmas.horarios.')->group(function () {
        Route::get('grade-pdf', [TurmaHorarioController::class, 'gradePdf'])->name('grade-pdf');
        Route::post('/', [TurmaHorarioController::class, 'store'])->name('store');
        Route::delete('/{turmaHorario}', [TurmaHorarioController::class, 'destroy'])->name('destroy');
    });

    // Turmas AEE (modalidade Atendimento Educacional Especializado)
    Route::get('turmas-aee/export', [TurmaAeeController::class, 'export'])->name('turmas-aee.export');
    Route::resource('turmas-aee', TurmaAeeController::class)
        ->parameters(['turmas-aee' => 'turmaAee'])
        ->except(['show']);
    Route::prefix('turmas-aee/{turmaAee}/professores')->name('turmas-aee.professores.')->group(function () {
        Route::post('/', [TurmaAeeProfessorController::class, 'store'])->name('store');
        Route::delete('/{turmaProfessor}', [TurmaAeeProfessorController::class, 'destroy'])->name('destroy');
    });
    Route::get('disciplinas/export', [DisciplinaController::class, 'export'])->name('disciplinas.export');
    Route::resource('disciplinas', DisciplinaController::class)->except(['show']);

    Route::prefix('series/{serie}/indicadores')->name('series.indicadores.')->group(function () {
        Route::post('replicar', [SerieIndicadorController::class, 'replicar'])->name('replicar');
        Route::post('/', [SerieIndicadorController::class, 'store'])->name('store');
        Route::put('/{indicador}', [SerieIndicadorController::class, 'update'])->name('update');
        Route::delete('/{indicador}', [SerieIndicadorController::class, 'destroy'])->name('destroy');
    });
    Route::get('api/series/{serie}/indicadores', [SerieIndicadorController::class, 'index'])->name('api.series.indicadores');

    Route::get('relatorios', [RelatorioCentralController::class, 'index'])->name('relatorios.index');
    Route::get('relatorios/alunos-por-turma', [AlunosPorTurmaRelatorioController::class, 'form'])->name('relatorios.alunos-por-turma.form');
    Route::get('relatorios/alunos-por-turma/gerar', [AlunosPorTurmaRelatorioController::class, 'gerar'])->name('relatorios.alunos-por-turma.gerar');

    Route::get('movimentacoes', [MovimentacaoController::class, 'index'])->name('movimentacoes.index');
    Route::get('movimentacoes/create', [MovimentacaoController::class, 'create'])->name('movimentacoes.create');
    Route::post('movimentacoes', [MovimentacaoController::class, 'store'])->name('movimentacoes.store');
    Route::get('movimentacoes/{movimentacao}', [MovimentacaoController::class, 'show'])->name('movimentacoes.show');

    Route::get('matriculas', [MatriculaController::class, 'index'])->name('matriculas.index');
    Route::post('matriculas', [MatriculaController::class, 'store'])->name('matriculas.store');
    Route::get('matriculas/segunda-via', [MatriculaController::class, 'segundaVia'])->name('matriculas.segunda-via');
    Route::get('matriculas/{tma_id}/comprovante', [MatriculaController::class, 'comprovante'])->name('matriculas.comprovante');
    Route::get('api/matriculas/buscar-comprovante', [MatriculaController::class, 'buscarComprovante'])->name('api.matriculas.buscar-comprovante');
    Route::get('api/matriculas/turmas', [MatriculaTurmaController::class, 'index'])->name('api.matriculas.turmas');
    Route::get('api/turmas/{tur_id}/alunos', [TurmaAlunoController::class, 'index'])->name('api.turmas.alunos');
    Route::patch('api/turmas/{tur_id}/alunos/{tma_id}/renovado', [TurmaAlunoController::class, 'toggleRenovado'])->name('api.turmas.alunos.renovado');

    // API turma AEE — alunos alocados / elegíveis
    Route::get('api/turmas-aee/{tur_id}/alunos', [TurmaAeeAlunoController::class, 'index'])->name('api.turmas-aee.alunos');
    Route::get('api/turmas-aee/{tur_id}/alunos/elegiveis', [TurmaAeeAlunoController::class, 'elegiveis'])->name('api.turmas-aee.alunos.elegiveis');
    Route::post('api/turmas-aee/{tur_id}/alunos', [TurmaAeeAlunoController::class, 'store'])->name('api.turmas-aee.alunos.store');
    Route::delete('api/turmas-aee/{tur_id}/alunos/{tma_id}', [TurmaAeeAlunoController::class, 'destroy'])->name('api.turmas-aee.alunos.destroy');

    // API turma AEE — atendimentos oferecidos
    Route::get('api/atendimentos-aee', [TurmaAeeAtendimentoController::class, 'catalogo'])->name('api.atendimentos-aee.catalogo');
    Route::get('api/turmas-aee/{tur_id}/atendimentos', [TurmaAeeAtendimentoController::class, 'index'])->name('api.turmas-aee.atendimentos');
    Route::post('api/turmas-aee/{tur_id}/atendimentos', [TurmaAeeAtendimentoController::class, 'store'])->name('api.turmas-aee.atendimentos.store');
    Route::delete('api/turmas-aee/{tur_id}/atendimentos/{tat_id}', [TurmaAeeAtendimentoController::class, 'destroy'])->name('api.turmas-aee.atendimentos.destroy');

    // Turmas de Atividade
    Route::get('turmas-atividade/export', [TurmaAtividadeController::class, 'export'])->name('turmas-atividade.export');
    Route::resource('turmas-atividade', TurmaAtividadeController::class)
        ->parameters(['turmas-atividade' => 'turmaAtividade'])
        ->except(['show']);
    Route::prefix('turmas-atividade/{turmaAtividade}/professores')->name('turmas-atividade.professores.')->group(function () {
        Route::post('/', [TurmaAtividadeProfessorController::class, 'store'])->name('store');
        Route::delete('/{turmaProfessor}', [TurmaAtividadeProfessorController::class, 'destroy'])->name('destroy');
    });

    // API turma Atividade — alunos
    Route::get('api/turmas-atividade/{tur_id}/alunos', [TurmaAtividadeAlunoController::class, 'index'])->name('api.turmas-atividade.alunos');
    Route::get('api/turmas-atividade/{tur_id}/alunos/elegiveis', [TurmaAtividadeAlunoController::class, 'elegiveis'])->name('api.turmas-atividade.alunos.elegiveis');
    Route::post('api/turmas-atividade/{tur_id}/alunos', [TurmaAtividadeAlunoController::class, 'store'])->name('api.turmas-atividade.alunos.store');
    Route::delete('api/turmas-atividade/{tur_id}/alunos/{tma_id}', [TurmaAtividadeAlunoController::class, 'destroy'])->name('api.turmas-atividade.alunos.destroy');

    // API turma Atividade — atividades ofertadas
    Route::get('api/atividades', [TurmaAtividadeItemController::class, 'catalogo'])->name('api.atividades.catalogo');
    Route::get('api/turmas-atividade/{tur_id}/itens', [TurmaAtividadeItemController::class, 'index'])->name('api.turmas-atividade.itens');
    Route::post('api/turmas-atividade/{tur_id}/itens', [TurmaAtividadeItemController::class, 'store'])->name('api.turmas-atividade.itens.store');
    Route::delete('api/turmas-atividade/{tur_id}/itens/{tta_id}', [TurmaAtividadeItemController::class, 'destroy'])->name('api.turmas-atividade.itens.destroy');
    Route::get('api/matriculas/verificar', [MatriculaController::class, 'verificar'])->name('api.matriculas.verificar');
    Route::get('api/alunos/search', [AlunoSearchController::class, 'search'])->name('api.alunos.search');

    Route::get('api/series', [SerieApiController::class, 'bySegmento'])->name('api.series.bySegmento');
    Route::get('api/series/search', [SerieApiController::class, 'search'])->name('api.series.search');
    Route::get('api/series/by-escola-segmento', [SerieApiController::class, 'byEscolaSegmento'])->name('api.series.byEscolaSegmento');
    Route::get('api/series/by-turmas-abertas', [SerieApiController::class, 'byTurmasAbertas'])->name('api.series.byTurmasAbertas');
    Route::get('api/segmentos/by-escola', [SegmentoApiController::class, 'byEscola'])->name('api.segmentos.byEscola');
    Route::get('api/municipios', [MunicipioController::class, 'search'])->name('api.municipios.search');
    Route::get('api/municipios/by-ibge/{codigo}', [MunicipioController::class, 'byIbge'])->name('api.municipios.byIbge');
    Route::get('api/bairros', [BairroController::class, 'search'])->name('api.bairros.search');
    Route::post('api/bairros', [BairroController::class, 'store'])->name('api.bairros.store');
    Route::get('api/gerencias', [GerenciaRegionalController::class, 'search'])->name('api.gerencias.search');
    Route::get('api/cargos', [CargoApiController::class, 'search'])->name('api.cargos.search');
    Route::get('api/escolas', [EscolaApiController::class, 'search'])->name('api.escolas.search');
    Route::get('api/funcionarios', [FuncionarioApiController::class, 'search'])->name('api.funcionarios.search');
    Route::get('api/disciplinas/search', [DisciplinaApiController::class, 'search'])->name('api.disciplinas.search');
    Route::get('api/grade-disciplinar', [GradeDisciplinarController::class, 'index'])->name('api.grade-disciplinar.index');

    Route::middleware('can:admin')->group(function () {
        Route::get('parametros', [ParametroController::class, 'edit'])->name('parametros.edit');
        Route::put('parametros', [ParametroController::class, 'update'])->name('parametros.update');

        Route::post('parametros/anos-letivos', [AnoLetivoController::class, 'store'])->name('parametros.anos-letivos.store');
        Route::put('parametros/anos-letivos/{anoLetivo}', [AnoLetivoController::class, 'update'])
            ->scopeBindings()->name('parametros.anos-letivos.update');
        Route::delete('parametros/anos-letivos/{anoLetivo}', [AnoLetivoController::class, 'destroy'])
            ->scopeBindings()->name('parametros.anos-letivos.destroy');

        Route::get('atendimentos-aee', [AtendimentoAeeController::class, 'index'])->name('atendimentos-aee.index');
        Route::post('atendimentos-aee', [AtendimentoAeeController::class, 'store'])->name('atendimentos-aee.store');
        Route::put('atendimentos-aee/{atendimentoAee}', [AtendimentoAeeController::class, 'update'])->name('atendimentos-aee.update');
        Route::delete('atendimentos-aee/{atendimentoAee}', [AtendimentoAeeController::class, 'destroy'])->name('atendimentos-aee.destroy');

        Route::get('atividades', [AtividadeController::class, 'index'])->name('atividades.index');
        Route::post('atividades', [AtividadeController::class, 'store'])->name('atividades.store');
        Route::put('atividades/{atividade}', [AtividadeController::class, 'update'])->name('atividades.update');
        Route::delete('atividades/{atividade}', [AtividadeController::class, 'destroy'])->name('atividades.destroy');

        Route::post('parametros/unidades', [UnidadeController::class, 'store'])->name('parametros.unidades.store');
        Route::put('parametros/unidades/{unidade}', [UnidadeController::class, 'update'])->name('parametros.unidades.update');
        Route::delete('parametros/unidades/{unidade}', [UnidadeController::class, 'destroy'])->name('parametros.unidades.destroy');

        Route::post('parametros/grade-horarios', [GradeHorarioController::class, 'store'])->name('parametros.grade-horarios.store');
        Route::put('parametros/grade-horarios/{gradeHorario}', [GradeHorarioController::class, 'update'])->name('parametros.grade-horarios.update');
        Route::delete('parametros/grade-horarios/{gradeHorario}', [GradeHorarioController::class, 'destroy'])->name('parametros.grade-horarios.destroy');

        Route::get('grade-disciplinar', [GradeDisciplinarController::class, 'page'])->name('grade-disciplinar.index');
        Route::post('parametros/grade-disciplinar', [GradeDisciplinarController::class, 'store'])->name('parametros.grade-disciplinar.store');
        Route::post('parametros/grade-disciplinar/clonar', [GradeDisciplinarController::class, 'clonar'])->name('parametros.grade-disciplinar.clonar');
        Route::put('parametros/grade-disciplinar/{grade}', [GradeDisciplinarController::class, 'update'])->name('parametros.grade-disciplinar.update');
        Route::patch('parametros/grade-disciplinar/{grade}/ordem', [GradeDisciplinarController::class, 'reordenar'])->name('parametros.grade-disciplinar.reordenar');
        Route::delete('parametros/grade-disciplinar/{grade}', [GradeDisciplinarController::class, 'destroy'])->name('parametros.grade-disciplinar.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
