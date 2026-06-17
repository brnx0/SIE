<?php

use App\Http\Controllers\Aluno\AlunoController;
use App\Http\Controllers\Aluno\MovimentacaoController;
use App\Http\Controllers\Relatorio\AlunosDeficienciaController;
use App\Http\Controllers\Relatorio\AlunosTranstornoController;
use App\Http\Controllers\Relatorio\AlunosPorTurmaRelatorioController;
use App\Http\Controllers\Relatorio\DadosAlunosTurmaController;
use App\Http\Controllers\Relatorio\RelacaoTurmasAeeController;
use App\Http\Controllers\Relatorio\RelacaoTurmasAtividadeController;
use App\Http\Controllers\Relatorio\FichaMatriculaController;
use App\Http\Controllers\Relatorio\SumarioMatriculasController;
use App\Http\Controllers\Relatorio\DeclaracaoMatriculaController;
use App\Http\Controllers\Relatorio\FormacaoClassesAeeController;
use App\Http\Controllers\Relatorio\FormacaoClassesController;
use App\Http\Controllers\Relatorio\ParecerDescritivoController;
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
use App\Http\Controllers\Parametro\SabadoLetivoController;
use App\Http\Controllers\Parametro\ConceitoController;
use App\Http\Controllers\Parametro\DiaNaoLetivoController;
use App\Http\Controllers\Parametro\MediaEscolaController;
use App\Http\Controllers\Parametro\SituacaoBloqueioController;
use App\Http\Controllers\Parametro\UnidadeController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\Secretaria\AcessoProfessorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $anlId = \Illuminate\Support\Facades\DB::table('cfg_ano_letivo')
            ->where('anl_fl_em_exercicio', true)
            ->value('anl_id');

        return Inertia::render('Dashboard', [
            'stats' => [
                'alunos_matriculados' => \Illuminate\Support\Facades\DB::table('edu_turma_aluno')
                    ->where('tma_situacao', 'ATIVA')
                    ->when($anlId, fn ($q) => $q->where('tma_anl_id', $anlId))
                    ->whereNull('tma_deleted_at')
                    ->distinct('tma_aln_id')
                    ->count('tma_aln_id'),
                'funcionarios_ativos' => \Illuminate\Support\Facades\DB::table('edu_funcionario')
                    ->where('fun_fl_ativo', true)
                    ->whereNull('fun_deleted_at')
                    ->count(),
                'turmas_andamento' => \Illuminate\Support\Facades\DB::table('edu_turma')
                    ->where('tur_situacao', 'ABERTA')
                    ->when($anlId, fn ($q) => $q->where('tur_anl_id', $anlId))
                    ->whereNull('tur_deleted_at')
                    ->count(),
                'escolas_ativas' => \Illuminate\Support\Facades\DB::table('edu_escola')
                    ->where('esc_fl_ativo', true)
                    ->whereNull('esc_deleted_at')
                    ->count(),
            ],
        ]);
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
        Route::post('replicar-ano', [SerieIndicadorController::class, 'replicarAno'])->name('replicar-ano');
        Route::post('/', [SerieIndicadorController::class, 'store'])->name('store');
        Route::put('/{indicador}', [SerieIndicadorController::class, 'update'])->name('update');
        Route::delete('/{indicador}', [SerieIndicadorController::class, 'destroy'])->name('destroy');
    });
    Route::get('api/series/{serie}/indicadores', [SerieIndicadorController::class, 'index'])->name('api.series.indicadores');

    Route::get('relatorios', [RelatorioCentralController::class, 'index'])->name('relatorios.index');
    Route::get('relatorios-escola', [RelatorioCentralController::class, 'escola'])->name('relatorios.escola');
    Route::get('relatorios-diario', [RelatorioCentralController::class, 'diario'])->name('relatorios.diario');
    Route::get('relatorios/parecer-descritivo', [ParecerDescritivoController::class, 'form'])->name('relatorios.parecer-descritivo.form');
    Route::get('relatorios/parecer-descritivo/unidades', [ParecerDescritivoController::class, 'unidades'])->name('relatorios.parecer-descritivo.unidades');
    Route::get('relatorios/parecer-descritivo/turmas', [ParecerDescritivoController::class, 'turmas'])->name('relatorios.parecer-descritivo.turmas');
    Route::get('relatorios/parecer-descritivo/disciplinas', [ParecerDescritivoController::class, 'disciplinas'])->name('relatorios.parecer-descritivo.disciplinas');
    Route::get('relatorios/parecer-descritivo/alunos', [ParecerDescritivoController::class, 'alunos'])->name('relatorios.parecer-descritivo.alunos');
    Route::get('relatorios/parecer-descritivo/gerar', [ParecerDescritivoController::class, 'gerar'])->name('relatorios.parecer-descritivo.gerar');
    Route::get('relatorios/alunos-por-turma', [AlunosPorTurmaRelatorioController::class, 'form'])->name('relatorios.alunos-por-turma.form');
    Route::get('relatorios/alunos-por-turma/gerar', [AlunosPorTurmaRelatorioController::class, 'gerar'])->name('relatorios.alunos-por-turma.gerar');
    Route::get('relatorios/dados-alunos-turma', [DadosAlunosTurmaController::class, 'form'])->name('relatorios.dados-alunos-turma.form');
    Route::get('relatorios/dados-alunos-turma/gerar', [DadosAlunosTurmaController::class, 'gerar'])->name('relatorios.dados-alunos-turma.gerar');
    Route::get('relatorios/relacao-turmas-aee', [RelacaoTurmasAeeController::class, 'form'])->name('relatorios.relacao-turmas-aee.form');
    Route::get('relatorios/relacao-turmas-aee/gerar', [RelacaoTurmasAeeController::class, 'gerar'])->name('relatorios.relacao-turmas-aee.gerar');
    Route::get('relatorios/relacao-turmas-atividade', [RelacaoTurmasAtividadeController::class, 'form'])->name('relatorios.relacao-turmas-atividade.form');
    Route::get('relatorios/relacao-turmas-atividade/gerar', [RelacaoTurmasAtividadeController::class, 'gerar'])->name('relatorios.relacao-turmas-atividade.gerar');
    Route::get('relatorios/declaracao-matricula', [DeclaracaoMatriculaController::class, 'form'])->name('relatorios.declaracao-matricula.form');
    Route::get('relatorios/declaracao-matricula/gerar', [DeclaracaoMatriculaController::class, 'gerar'])->name('relatorios.declaracao-matricula.gerar');
    Route::get('relatorios/formacao-classes', [FormacaoClassesController::class, 'form'])->name('relatorios.formacao-classes.form');
    Route::get('relatorios/formacao-classes/gerar', [FormacaoClassesController::class, 'gerar'])->name('relatorios.formacao-classes.gerar');
    Route::get('relatorios/formacao-classes-aee', [FormacaoClassesAeeController::class, 'form'])->name('relatorios.formacao-classes-aee.form');
    Route::get('relatorios/formacao-classes-aee/gerar', [FormacaoClassesAeeController::class, 'gerar'])->name('relatorios.formacao-classes-aee.gerar');
    Route::get('relatorios/alunos-deficiencia', [AlunosDeficienciaController::class, 'form'])->name('relatorios.alunos-deficiencia.form');
    Route::get('relatorios/alunos-deficiencia/gerar', [AlunosDeficienciaController::class, 'gerar'])->name('relatorios.alunos-deficiencia.gerar');
    Route::get('relatorios/alunos-transtorno', [AlunosTranstornoController::class, 'form'])->name('relatorios.alunos-transtorno.form');
    Route::get('relatorios/alunos-transtorno/gerar', [AlunosTranstornoController::class, 'gerar'])->name('relatorios.alunos-transtorno.gerar');
    Route::get('relatorios/ficha-matricula', [FichaMatriculaController::class, 'form'])->name('relatorios.ficha-matricula.form');
    Route::get('relatorios/ficha-matricula/gerar', [FichaMatriculaController::class, 'gerar'])->name('relatorios.ficha-matricula.gerar');
    Route::get('relatorios/sumario-matriculas', [SumarioMatriculasController::class, 'form'])->name('relatorios.sumario-matriculas.form');
    Route::get('relatorios/sumario-matriculas/gerar', [SumarioMatriculasController::class, 'gerar'])->name('relatorios.sumario-matriculas.gerar');

    Route::get('movimentacoes', [MovimentacaoController::class, 'index'])->name('movimentacoes.index');
    Route::get('movimentacoes/create', [MovimentacaoController::class, 'create'])->name('movimentacoes.create');
    Route::post('movimentacoes', [MovimentacaoController::class, 'store'])->name('movimentacoes.store');
    Route::get('movimentacoes/{movimentacao}', [MovimentacaoController::class, 'show'])->name('movimentacoes.show');
    Route::post('movimentacoes/{movimentacao}/desfazer', [MovimentacaoController::class, 'desfazer'])->name('movimentacoes.desfazer');
    Route::get('movimentacoes/{movimentacao}/declaracao-transferencia', [MovimentacaoController::class, 'declaracaoTransferencia'])->name('movimentacoes.declaracao-transferencia');

    Route::get('matriculas', [MatriculaController::class, 'index'])->name('matriculas.index');
    Route::post('matriculas', [MatriculaController::class, 'store'])->name('matriculas.store');
    Route::get('matriculas/segunda-via', [MatriculaController::class, 'segundaVia'])->name('matriculas.segunda-via');
    Route::get('matriculas-aee', [MatriculaController::class, 'aee'])->name('matriculas-aee.index');
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

    // Diário — Plano de Aula (turmas regulares)
    Route::prefix('diario')->name('diario.')->group(function () {
        // Diário de Classe — seletor de contexto do professor
        Route::get('/', [\App\Http\Controllers\Diario\DiarioController::class, 'index'])->name('index');

        Route::get('planos/export', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'export'])->name('planos.export');
        Route::get('planos', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'index'])->name('planos.index');
        Route::get('planos/create', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'create'])->name('planos.create');
        Route::post('planos', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'store'])->name('planos.store');
        Route::get('planos/{plano}/edit', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'edit'])->name('planos.edit');
        Route::put('planos/{plano}', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'update'])->name('planos.update');
        Route::delete('planos/{plano}', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'destroy'])->name('planos.destroy');
        Route::get('planos/{plano}/pdf', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'pdf'])->name('planos.pdf');

        Route::get('planos-aee/export', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'export'])->name('planos-aee.export');
        Route::get('planos-aee', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'index'])->name('planos-aee.index');
        Route::get('planos-aee/create', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'create'])->name('planos-aee.create');
        Route::post('planos-aee', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'store'])->name('planos-aee.store');
        Route::get('planos-aee/{plano}/edit', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'edit'])->name('planos-aee.edit');
        Route::put('planos-aee/{plano}', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'update'])->name('planos-aee.update');
        Route::delete('planos-aee/{plano}', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'destroy'])->name('planos-aee.destroy');
        Route::get('planos-aee/{plano}/pdf', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'pdf'])->name('planos-aee.pdf');

        Route::get('instrumentos-avaliativos/export', [\App\Http\Controllers\Diario\InstrumentoAvaliativoController::class, 'export'])->name('instrumentos-avaliativos.export');
        Route::resource('instrumentos-avaliativos', \App\Http\Controllers\Diario\InstrumentoAvaliativoController::class)
            ->parameters(['instrumentos-avaliativos' => 'instrumento'])
            ->except(['show']);

        Route::get('quadro-horario', [\App\Http\Controllers\Diario\QuadroHorarioController::class, 'index'])->name('quadro-horario.index');
        Route::post('quadro-horario/{turma}/horarios', [\App\Http\Controllers\Diario\QuadroHorarioController::class, 'store'])->name('quadro-horario.store');
        Route::delete('quadro-horario/{turma}/horarios/{turmaHorario}', [\App\Http\Controllers\Diario\QuadroHorarioController::class, 'destroy'])->name('quadro-horario.destroy');
    });
    Route::prefix('api/diario')->name('api.diario.')->group(function () {
        // Diário de Classe — lookups de contexto
        Route::get('contexto/escolas', [\App\Http\Controllers\Diario\DiarioController::class, 'lookupEscolas'])->name('contexto.escolas');
        Route::get('contexto/turmas', [\App\Http\Controllers\Diario\DiarioController::class, 'lookupTurmas'])->name('contexto.turmas');
        Route::get('contexto/disciplinas', [\App\Http\Controllers\Diario\DiarioController::class, 'lookupDisciplinas'])->name('contexto.disciplinas');
        Route::get('contexto/unidades', [\App\Http\Controllers\Diario\DiarioController::class, 'lookupUnidades'])->name('contexto.unidades');

        // Lançamento de avaliação descritiva (autosave por aluno)
        Route::get('avaliacao-descritiva/alunos', [\App\Http\Controllers\Diario\AvaliacaoDescritivaController::class, 'alunos'])->name('avaliacao-descritiva.alunos');
        Route::post('avaliacao-descritiva', [\App\Http\Controllers\Diario\AvaliacaoDescritivaController::class, 'salvar'])->name('avaliacao-descritiva.salvar');

        Route::get('quadro-horario/escolas', [\App\Http\Controllers\Diario\QuadroHorarioController::class, 'lookupEscolas'])->name('quadro-horario.escolas');
        Route::get('quadro-horario/turmas', [\App\Http\Controllers\Diario\QuadroHorarioController::class, 'lookupTurmas'])->name('quadro-horario.turmas');
        Route::get('quadro-horario/grade', [\App\Http\Controllers\Diario\QuadroHorarioController::class, 'grade'])->name('quadro-horario.grade');

        // Lançamento de notas (avaliação numérica / conceitual)
        Route::get('notas/contexto', [\App\Http\Controllers\Diario\NotaController::class, 'contexto'])->name('notas.contexto');
        Route::post('notas/avaliacoes', [\App\Http\Controllers\Diario\NotaController::class, 'storeAvaliacao'])->name('notas.avaliacoes.store');
        Route::put('notas/avaliacoes/{avaliacao}', [\App\Http\Controllers\Diario\NotaController::class, 'updateAvaliacao'])->name('notas.avaliacoes.update');
        Route::delete('notas/avaliacoes/{avaliacao}', [\App\Http\Controllers\Diario\NotaController::class, 'destroyAvaliacao'])->name('notas.avaliacoes.destroy');
        Route::post('notas/salvar', [\App\Http\Controllers\Diario\NotaController::class, 'salvarNota'])->name('notas.salvar');
        Route::get('planos/escolas', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'lookupEscolas'])->name('planos.escolas');
        Route::get('planos/turmas', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'lookupTurmas'])->name('planos.turmas');
        Route::get('planos/disciplinas', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'lookupDisciplinas'])->name('planos.disciplinas');
        Route::get('planos/unidades', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'lookupUnidades'])->name('planos.unidades');
        Route::get('planos/indicadores', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'lookupIndicadores'])->name('planos.indicadores');

        Route::get('planos-aee/escolas', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'lookupEscolas'])->name('planos-aee.escolas');
        Route::get('planos-aee/turmas', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'lookupTurmas'])->name('planos-aee.turmas');
    });

    // Coordenador Pedagógico — Validação de Planos
    Route::prefix('coordenador')->name('coordenador.')->middleware('role:coordenador')->group(function () {
        Route::get('planos/export', [\App\Http\Controllers\Coordenador\PlanoValidacaoController::class, 'export'])->name('planos.export-lista');
        Route::get('planos', [\App\Http\Controllers\Coordenador\PlanoValidacaoController::class, 'index'])->name('planos.index');
        Route::put('planos/{plano}/status', [\App\Http\Controllers\Coordenador\PlanoValidacaoController::class, 'update'])->name('planos.status');
        Route::get('planos/{plano}/pdf', [\App\Http\Controllers\Coordenador\PlanoValidacaoController::class, 'pdf'])->name('planos.pdf');
    });
    Route::prefix('api/coordenador')->name('api.coordenador.')->middleware('role:coordenador')->group(function () {
        Route::get('planos/{plano}',          [\App\Http\Controllers\Coordenador\PlanoValidacaoController::class, 'show'])->name('planos.show');
        Route::get('planos-lookup/anos',      [\App\Http\Controllers\Coordenador\PlanoValidacaoController::class, 'lookupAnos'])->name('planos.anos');
        Route::get('planos-lookup/escolas',   [\App\Http\Controllers\Coordenador\PlanoValidacaoController::class, 'lookupEscolas'])->name('planos.escolas');
        Route::get('planos-lookup/segmentos', [\App\Http\Controllers\Coordenador\PlanoValidacaoController::class, 'lookupSegmentos'])->name('planos.segmentos');
        Route::get('planos-lookup/series',    [\App\Http\Controllers\Coordenador\PlanoValidacaoController::class, 'lookupSeries'])->name('planos.series');
        Route::get('planos-lookup/turmas',    [\App\Http\Controllers\Coordenador\PlanoValidacaoController::class, 'lookupTurmas'])->name('planos.turmas');
    });

    // Coordenador Pedagógico Interno — Validação de Planos AEE
    Route::prefix('coordenador-interno')->name('coordenador-interno.')->middleware('role:coordenador_interno')->group(function () {
        Route::get('planos-aee', [\App\Http\Controllers\Coordenador\PlanoAeeValidacaoController::class, 'index'])->name('planos-aee.index');
        Route::put('planos-aee/{plano}/status', [\App\Http\Controllers\Coordenador\PlanoAeeValidacaoController::class, 'update'])->name('planos-aee.status');
        Route::get('planos-aee/{plano}/pdf', [\App\Http\Controllers\Coordenador\PlanoAeeValidacaoController::class, 'pdf'])->name('planos-aee.pdf');
    });
    Route::prefix('api/coordenador-interno')->name('api.coordenador-interno.')->middleware('role:coordenador_interno')->group(function () {
        Route::get('planos-aee/{plano}',         [\App\Http\Controllers\Coordenador\PlanoAeeValidacaoController::class, 'show'])->name('planos-aee.show');
        Route::get('planos-aee-lookup/anos',     [\App\Http\Controllers\Coordenador\PlanoAeeValidacaoController::class, 'lookupAnos'])->name('planos-aee.anos');
        Route::get('planos-aee-lookup/escolas',  [\App\Http\Controllers\Coordenador\PlanoAeeValidacaoController::class, 'lookupEscolas'])->name('planos-aee.escolas');
        Route::get('planos-aee-lookup/turmas',   [\App\Http\Controllers\Coordenador\PlanoAeeValidacaoController::class, 'lookupTurmas'])->name('planos-aee.turmas');
    });

    // Secretaria Escolar — geração de acessos de professores
    Route::prefix('secretaria')->name('secretaria.')->middleware('role:secretaria_escola')->group(function () {
        Route::get('acessos-professores/export', [AcessoProfessorController::class, 'export'])->name('acessos-professores.export');
        Route::get('acessos-professores', [AcessoProfessorController::class, 'index'])->name('acessos-professores.index');
        Route::post('acessos-professores/gerar', [AcessoProfessorController::class, 'gerar'])->name('acessos-professores.gerar');
    });

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

        Route::post('parametros/dias-nao-letivos', [DiaNaoLetivoController::class, 'store'])->name('parametros.dias-nao-letivos.store');
        Route::put('parametros/dias-nao-letivos/{diaNaoLetivo}', [DiaNaoLetivoController::class, 'update'])->name('parametros.dias-nao-letivos.update');
        Route::delete('parametros/dias-nao-letivos/{diaNaoLetivo}', [DiaNaoLetivoController::class, 'destroy'])->name('parametros.dias-nao-letivos.destroy');

        Route::post('parametros/medias-escola', [MediaEscolaController::class, 'store'])->name('parametros.medias-escola.store');
        Route::put('parametros/medias-escola/{mediaEscola}', [MediaEscolaController::class, 'update'])->name('parametros.medias-escola.update');
        Route::delete('parametros/medias-escola/{mediaEscola}', [MediaEscolaController::class, 'destroy'])->name('parametros.medias-escola.destroy');
        Route::post('parametros/medias-escola/replicar', [MediaEscolaController::class, 'replicar'])->name('parametros.medias-escola.replicar');

        Route::post('parametros/conceitos', [ConceitoController::class, 'store'])->name('parametros.conceitos.store');
        Route::put('parametros/conceitos/{conceito}', [ConceitoController::class, 'update'])->name('parametros.conceitos.update');
        Route::delete('parametros/conceitos/{conceito}', [ConceitoController::class, 'destroy'])->name('parametros.conceitos.destroy');

        Route::post('parametros/situacoes-bloqueio', [SituacaoBloqueioController::class, 'store'])->name('parametros.situacoes-bloqueio.store');
        Route::delete('parametros/situacoes-bloqueio/{situacaoBloqueio}', [SituacaoBloqueioController::class, 'destroy'])->name('parametros.situacoes-bloqueio.destroy');

        Route::post('parametros/grade-horarios', [GradeHorarioController::class, 'store'])->name('parametros.grade-horarios.store');
        Route::put('parametros/grade-horarios/{gradeHorario}', [GradeHorarioController::class, 'update'])->name('parametros.grade-horarios.update');
        Route::delete('parametros/grade-horarios/{gradeHorario}', [GradeHorarioController::class, 'destroy'])->name('parametros.grade-horarios.destroy');

        Route::get('sabados-letivos', [SabadoLetivoController::class, 'index'])->name('sabados-letivos.index');
        Route::post('sabados-letivos', [SabadoLetivoController::class, 'store'])->name('sabados-letivos.store');
        Route::delete('sabados-letivos/{sabadoLetivo}', [SabadoLetivoController::class, 'destroy'])->name('sabados-letivos.destroy');

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
