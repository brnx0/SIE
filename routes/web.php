<?php

use App\Http\Controllers\Aluno\AlunoController;
use App\Http\Controllers\Aluno\MovimentacaoController;
use App\Http\Controllers\Relatorio\AlunosDeficienciaController;
use App\Http\Controllers\Relatorio\AlunosTranstornoController;
use App\Http\Controllers\Relatorio\AlunosPorTurmaRelatorioController;
use App\Http\Controllers\Relatorio\AlunosPorEscolaController;
use App\Http\Controllers\Relatorio\CensoCriticasController;
use App\Http\Controllers\Censo\CensoExportController;
use App\Http\Controllers\Relatorio\DadosAlunosTurmaController;
use App\Http\Controllers\Relatorio\EquipeGestoraController;
use App\Http\Controllers\Relatorio\AlunosAptosEjaController;
use App\Http\Controllers\Relatorio\DistorcaoIdadeController;
use App\Http\Controllers\Relatorio\TurmasMultisseriadasController;
use App\Http\Controllers\Relatorio\LevantamentoMunicipioController;
use App\Http\Controllers\Relatorio\PendenciaEncerramentoController;
use App\Http\Controllers\Relatorio\RelacaoEscolasController;
use App\Http\Controllers\Relatorio\DesempenhoAeeController;
use App\Http\Controllers\Relatorio\RelacaoTurmasAeeController;
use App\Http\Controllers\Relatorio\RelacaoTurmasAtividadeController;
use App\Http\Controllers\Relatorio\FichaMatriculaController;
use App\Http\Controllers\Relatorio\SumarioMatriculasController;
use App\Http\Controllers\Relatorio\DeclaracaoConclusaoController;
use App\Http\Controllers\Relatorio\DeclaracaoMatriculaController;
use App\Http\Controllers\Relatorio\TermoRenovacaoController;
use App\Http\Controllers\Relatorio\FormacaoClassesAeeController;
use App\Http\Controllers\Relatorio\FormacaoClassesController;
use App\Http\Controllers\Relatorio\ParecerDescritivoController;
use App\Http\Controllers\Relatorio\BoletimController;
use App\Http\Controllers\Relatorio\ConteudoMinistradoController;
use App\Http\Controllers\Relatorio\BoletimAvaliativoController;
use App\Http\Controllers\Relatorio\NotasPorUnidadeController;
use App\Http\Controllers\Relatorio\MapaNotasDisciplinaController;
use App\Http\Controllers\Relatorio\AtaFinalController;
use App\Http\Controllers\Relatorio\DiagnosticoRelatorioController;
use App\Http\Controllers\Relatorio\FinalEducacaoInfantilController;
use App\Http\Controllers\Relatorio\FrequenciaMensalController;
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
use App\Http\Controllers\Api\PovoIndigenaController;
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
use App\Http\Controllers\Censo\CensoImportacaoController;
use App\Http\Controllers\Api\CargoController as CargoApiController;
use App\Http\Controllers\Api\CursoFormacaoSuperiorController as CursoFormacaoApiController;
use App\Http\Controllers\Api\InstituicaoEnsinoSuperiorController as InstituicaoEnsinoApiController;
use App\Http\Controllers\Api\EscolaController as EscolaApiController;
use App\Http\Controllers\Api\FuncionarioController as FuncionarioApiController;
use App\Http\Controllers\Funcionario\FuncionarioAdmissaoController;
use App\Http\Controllers\Funcionario\FuncionarioController;
use App\Http\Controllers\Funcionario\FuncionarioEscolaridadeController;
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
use App\Http\Controllers\Parametro\DiasLetivosController;
use App\Http\Controllers\Parametro\MediaEscolaController;
use App\Http\Controllers\Parametro\MotivoBaixaFrequenciaController;
use App\Http\Controllers\Diario\JustificativaFaltaController;
use App\Http\Controllers\Parametro\SituacaoBloqueioController;
use App\Http\Controllers\Parametro\UnidadeController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\Secretaria\AcessoProfessorController;
use App\Http\Controllers\Secretaria\ConteudoMinistradoController as SecretariaConteudoMinistradoController;
use App\Http\Controllers\Secretaria\LancamentoManualController;
use App\Http\Controllers\Secretaria\RenovacaoMatriculaController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Fallback público p/ arquivos do disco "public" (logos, fotos) quando o symlink
// public/storage não alcança os uploads no deploy (nginx no host + storage/app em
// volume do container). Controller (não closure) p/ funcionar com route:cache.
Route::get('storage/{path}', \App\Http\Controllers\PublicStorageController::class)
    ->where('path', '.*')->name('storage.public');

// Central de Ajuda — pública (sem login). Conteúdo em resources/ajuda/**.md.
Route::get('ajuda', [\App\Http\Controllers\AjudaController::class, 'index'])->name('ajuda.index');

// Logout forçado quando a sessão/CSRF expira (GET → isento de CSRF, que já falhou).
// Invalida a sessão de fato e leva ao login — evita voltar logado.
Route::get('sessao-expirada', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login')->with('error', 'Sua sessão expirou. Faça login novamente.');
})->name('sessao.expirada');

Route::middleware(['auth', 'verified', 'tela'])->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Importação do Censo (migração INEP) — governada pela matriz cfg_tela (/censo/importacao).
    Route::prefix('censo/importacao')->name('censo.importacao.')->group(function () {
        Route::get('/', [CensoImportacaoController::class, 'index'])->name('index');
        Route::post('/', [CensoImportacaoController::class, 'store'])->name('store');
        Route::post('/{lote}/commit', [CensoImportacaoController::class, 'commit'])->name('commit');
        Route::delete('/{lote}', [CensoImportacaoController::class, 'destroy'])->name('destroy');
    });

    Route::get('users/export', [UsersController::class, 'export'])->name('users.export');
    Route::resource('users', UsersController::class)->except(['show']);

    // Permissões de Telas — editor da matriz role × tela (governado pela própria matriz: admin)
    Route::get('permissoes', [\App\Http\Controllers\PermissaoTelaController::class, 'index'])->name('permissoes.index');
    Route::put('permissoes', [\App\Http\Controllers\PermissaoTelaController::class, 'update'])->name('permissoes.update');

    // Auditoria — trilha de quem alterou o quê e quando (governado pela matriz: admin)
    Route::get('auditoria', [\App\Http\Controllers\AuditoriaController::class, 'index'])->name('auditoria.index');

    // Fotos servidas via PHP (evita 403 do symlink /storage em alguns ambientes)
    Route::get('midia/aluno/{aluno}/foto', [\App\Http\Controllers\MidiaController::class, 'alunoFoto'])->name('midia.aluno.foto');
    Route::get('midia/funcionario/{funcionario}/foto', [\App\Http\Controllers\MidiaController::class, 'funcionarioFoto'])->name('midia.funcionario.foto');

    Route::get('alunos/export', [AlunoController::class, 'export'])->name('alunos.export');
    // Histórico Escolar / Certificado (só secretaria/diretor/admin — guard no controller)
    Route::get('alunos/{aluno}/historico',[\App\Http\Controllers\Aluno\HistoricoEscolarController::class, 'pdf'])->name('alunos.historico.pdf');
    // Lançamento MANUAL de anos do histórico (só secretaria/diretor/admin — guard no controller)
    Route::get('alunos/{aluno}/historico-manual', [\App\Http\Controllers\Aluno\HistoricoManualController::class, 'listar'])->name('alunos.historico-manual.listar');
    Route::post('alunos/{aluno}/historico-manual', [\App\Http\Controllers\Aluno\HistoricoManualController::class, 'salvar'])->name('alunos.historico-manual.salvar');
    Route::delete('alunos/{aluno}/historico-manual/{historicoManual}', [\App\Http\Controllers\Aluno\HistoricoManualController::class, 'remover'])->name('alunos.historico-manual.remover');
    Route::post('alunos/{aluno}/gerar-matricula', [AlunoController::class, 'gerarMatricula'])->name('alunos.gerar-matricula');
    Route::resource('alunos', AlunoController::class)->except(['show']);
    Route::get('escolas/export', [EscolaController::class, 'export'])->name('escolas.export');
    Route::resource('escolas', EscolaController::class)->except(['show']);
    Route::prefix('escolas/{escola}/censo')->name('escolas.censo.')->group(function () {
        Route::post('/', [EscolaCensoController::class, 'store'])->name('store');
        Route::get('/{censoEscolar}/edit', [EscolaCensoController::class, 'edit'])->name('edit');
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
    Route::prefix('funcionarios/{funcionario}/escolaridades')->name('funcionarios.escolaridades.')->group(function () {
        Route::post('/', [FuncionarioEscolaridadeController::class, 'store'])->name('store');
        Route::put('/{escolaridade}', [FuncionarioEscolaridadeController::class, 'update'])->name('update');
        Route::delete('/{escolaridade}', [FuncionarioEscolaridadeController::class, 'destroy'])->name('destroy');
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

    // Transporte Escolar — rotas em arquivo próprio (governadas pela matriz cfg_tela: /transporte/*)
    require __DIR__.'/transporte.php';

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
    Route::get('relatorios-secretaria', [RelatorioCentralController::class, 'secretaria'])->name('relatorios.secretaria')->middleware('role:admin,gestor_secretaria,diretor,secretaria_escola');
    Route::get('relatorios-pedagogico', [RelatorioCentralController::class, 'pedagogico'])->name('relatorios.pedagogico');
    // Cards dos relatórios da alimentação. Os relatórios em si moram em
    // routes/alimentacao.php, junto do resto do módulo.
    Route::get('relatorios-alimentacao', [RelatorioCentralController::class, 'alimentacao'])->name('relatorios.alimentacao');

    // Encerramento do Ano Letivo — acesso governado pela matriz de telas.
    Route::group([], function () {
        Route::get('encerramento-turmas', [\App\Http\Controllers\Encerramento\EncerramentoTurmaController::class, 'index'])->name('encerramento.turmas.index');
        Route::get('encerramento-turmas/dados', [\App\Http\Controllers\Encerramento\EncerramentoTurmaController::class, 'dados'])->name('encerramento.turmas.dados');
        Route::post('encerramento-turmas/encerrar', [\App\Http\Controllers\Encerramento\EncerramentoTurmaController::class, 'encerrar'])->name('encerramento.turmas.encerrar');
        Route::post('encerramento-turmas/cancelar', [\App\Http\Controllers\Encerramento\EncerramentoTurmaController::class, 'cancelar'])->name('encerramento.turmas.cancelar');
        Route::post('encerramento-turmas/cancelar-aluno', [\App\Http\Controllers\Encerramento\EncerramentoTurmaController::class, 'cancelarAluno'])->name('encerramento.turmas.cancelar-aluno');

        Route::get('duplicar-turmas', [\App\Http\Controllers\Encerramento\DuplicarTurmaController::class, 'index'])->name('duplicar-turmas.index');
        Route::get('duplicar-turmas/dados', [\App\Http\Controllers\Encerramento\DuplicarTurmaController::class, 'dados'])->name('duplicar-turmas.dados');
        Route::post('duplicar-turmas/duplicar', [\App\Http\Controllers\Encerramento\DuplicarTurmaController::class, 'duplicar'])->name('duplicar-turmas.duplicar');
        Route::post('duplicar-turmas/duplicar-todas', [\App\Http\Controllers\Encerramento\DuplicarTurmaController::class, 'duplicarTodas'])->name('duplicar-turmas.duplicar-todas');

        Route::get('confirmacao-renovacao', [\App\Http\Controllers\Encerramento\ConfirmacaoRenovacaoController::class, 'index'])->name('confirmacao-renovacao.index');
        Route::get('confirmacao-renovacao/turmas', [\App\Http\Controllers\Encerramento\ConfirmacaoRenovacaoController::class, 'turmas'])->name('confirmacao-renovacao.turmas');
        Route::get('confirmacao-renovacao/alunos', [\App\Http\Controllers\Encerramento\ConfirmacaoRenovacaoController::class, 'alunos'])->name('confirmacao-renovacao.alunos');
        Route::get('confirmacao-renovacao/turmas-destino', [\App\Http\Controllers\Encerramento\ConfirmacaoRenovacaoController::class, 'turmasDestino'])->name('confirmacao-renovacao.turmas-destino');
        Route::post('confirmacao-renovacao/confirmar', [\App\Http\Controllers\Encerramento\ConfirmacaoRenovacaoController::class, 'confirmar'])->name('confirmacao-renovacao.confirmar');
        Route::post('confirmacao-renovacao/confirmar-lote', [\App\Http\Controllers\Encerramento\ConfirmacaoRenovacaoController::class, 'confirmarLote'])->name('confirmacao-renovacao.confirmar-lote');
    });
    Route::get('relatorios/parecer-descritivo', [ParecerDescritivoController::class, 'form'])->name('relatorios.parecer-descritivo.form');
    Route::get('relatorios/parecer-descritivo/unidades', [ParecerDescritivoController::class, 'unidades'])->name('relatorios.parecer-descritivo.unidades');
    Route::get('relatorios/parecer-descritivo/turmas', [ParecerDescritivoController::class, 'turmas'])->name('relatorios.parecer-descritivo.turmas');
    Route::get('relatorios/parecer-descritivo/disciplinas', [ParecerDescritivoController::class, 'disciplinas'])->name('relatorios.parecer-descritivo.disciplinas');
    Route::get('relatorios/parecer-descritivo/alunos', [ParecerDescritivoController::class, 'alunos'])->name('relatorios.parecer-descritivo.alunos');
    Route::get('relatorios/parecer-descritivo/gerar', [ParecerDescritivoController::class, 'gerar'])->name('relatorios.parecer-descritivo.gerar');
    Route::get('relatorios/parecer-descritivo/xlsx', [ParecerDescritivoController::class, 'exportarXlsx'])->name('relatorios.parecer-descritivo.xlsx');

    // Boletim do aluno
    Route::get('relatorios/boletim', [BoletimController::class, 'form'])->name('relatorios.boletim.form');
    Route::get('relatorios/boletim/turmas', [BoletimController::class, 'turmas'])->name('relatorios.boletim.turmas');
    Route::get('relatorios/boletim/unidades', [BoletimController::class, 'unidades'])->name('relatorios.boletim.unidades');
    Route::get('relatorios/boletim/alunos', [BoletimController::class, 'alunos'])->name('relatorios.boletim.alunos');
    Route::get('relatorios/boletim/gerar', [BoletimController::class, 'gerar'])->name('relatorios.boletim.gerar');

    // Mapa de notas da turma
    Route::get('relatorios/notas-por-unidade', [NotasPorUnidadeController::class, 'form'])->name('relatorios.notas-por-unidade.form');
    Route::get('relatorios/notas-por-unidade/turmas', [NotasPorUnidadeController::class, 'turmas'])->name('relatorios.notas-por-unidade.turmas');
    Route::get('relatorios/notas-por-unidade/unidades', [NotasPorUnidadeController::class, 'unidades'])->name('relatorios.notas-por-unidade.unidades');
    Route::get('relatorios/notas-por-unidade/gerar', [NotasPorUnidadeController::class, 'gerar'])->name('relatorios.notas-por-unidade.gerar');
    Route::get('relatorios/mapa-notas-disciplina', [MapaNotasDisciplinaController::class, 'form'])->name('relatorios.mapa-notas-disciplina.form');
    Route::get('relatorios/mapa-notas-disciplina/turmas', [MapaNotasDisciplinaController::class, 'turmas'])->name('relatorios.mapa-notas-disciplina.turmas');
    Route::get('relatorios/mapa-notas-disciplina/disciplinas', [MapaNotasDisciplinaController::class, 'disciplinas'])->name('relatorios.mapa-notas-disciplina.disciplinas');
    Route::get('relatorios/mapa-notas-disciplina/unidades', [MapaNotasDisciplinaController::class, 'unidades'])->name('relatorios.mapa-notas-disciplina.unidades');
    Route::get('relatorios/mapa-notas-disciplina/gerar', [MapaNotasDisciplinaController::class, 'gerar'])->name('relatorios.mapa-notas-disciplina.gerar');
    Route::get('relatorios/historico-escolar', [\App\Http\Controllers\Aluno\HistoricoEscolarController::class, 'form'])->name('relatorios.historico-escolar.form');
    Route::get('relatorios/ata-final', [AtaFinalController::class, 'form'])->name('relatorios.ata-final.form');
    Route::get('relatorios/ata-final/turmas', [AtaFinalController::class, 'turmas'])->name('relatorios.ata-final.turmas');
    Route::get('relatorios/ata-final/gerar', [AtaFinalController::class, 'gerar'])->name('relatorios.ata-final.gerar');
    Route::get('relatorios/final-educacao-infantil', [FinalEducacaoInfantilController::class, 'form'])->name('relatorios.final-educacao-infantil.form');
    Route::get('relatorios/final-educacao-infantil/turmas', [FinalEducacaoInfantilController::class, 'turmas'])->name('relatorios.final-educacao-infantil.turmas');
    Route::get('relatorios/final-educacao-infantil/gerar', [FinalEducacaoInfantilController::class, 'gerar'])->name('relatorios.final-educacao-infantil.gerar');
    Route::get('relatorios/diagnostico', [DiagnosticoRelatorioController::class, 'form'])->name('relatorios.diagnostico.form');
    Route::get('relatorios/diagnostico/turmas', [DiagnosticoRelatorioController::class, 'turmas'])->name('relatorios.diagnostico.turmas');
    Route::get('relatorios/diagnostico/disciplinas', [DiagnosticoRelatorioController::class, 'disciplinas'])->name('relatorios.diagnostico.disciplinas');
    Route::get('relatorios/diagnostico/alunos', [DiagnosticoRelatorioController::class, 'alunos'])->name('relatorios.diagnostico.alunos');
    Route::get('relatorios/diagnostico/gerar', [DiagnosticoRelatorioController::class, 'gerar'])->name('relatorios.diagnostico.gerar');

    // Diário de Frequência Mensal
    Route::get('relatorios/frequencia-mensal', [FrequenciaMensalController::class, 'form'])->name('relatorios.frequencia-mensal.form');
    Route::get('relatorios/frequencia-mensal/turmas', [FrequenciaMensalController::class, 'turmas'])->name('relatorios.frequencia-mensal.turmas');
    Route::get('relatorios/frequencia-mensal/gerar', [FrequenciaMensalController::class, 'gerar'])->name('relatorios.frequencia-mensal.gerar');

    // Boletim Avaliativo (por disciplina da grade, 1 página cada)
    Route::get('relatorios/boletim-avaliativo', [BoletimAvaliativoController::class, 'form'])->name('relatorios.boletim-avaliativo.form');
    Route::get('relatorios/boletim-avaliativo/turmas', [BoletimAvaliativoController::class, 'turmas'])->name('relatorios.boletim-avaliativo.turmas');
    Route::get('relatorios/boletim-avaliativo/disciplinas', [BoletimAvaliativoController::class, 'disciplinas'])->name('relatorios.boletim-avaliativo.disciplinas');
    Route::get('relatorios/boletim-avaliativo/gerar', [BoletimAvaliativoController::class, 'gerar'])->name('relatorios.boletim-avaliativo.gerar');

    // Conteúdo ministrado por disciplina/dia
    Route::get('relatorios/conteudo-ministrado', [ConteudoMinistradoController::class, 'form'])->name('relatorios.conteudo-ministrado.form');
    Route::get('relatorios/conteudo-ministrado/turmas', [ConteudoMinistradoController::class, 'turmas'])->name('relatorios.conteudo-ministrado.turmas');
    Route::get('relatorios/conteudo-ministrado/unidades', [ConteudoMinistradoController::class, 'unidades'])->name('relatorios.conteudo-ministrado.unidades');
    Route::get('relatorios/conteudo-ministrado/gerar', [ConteudoMinistradoController::class, 'gerar'])->name('relatorios.conteudo-ministrado.gerar');
    Route::get('relatorios/alunos-por-turma', [AlunosPorTurmaRelatorioController::class, 'form'])->name('relatorios.alunos-por-turma.form');
    Route::get('relatorios/alunos-por-turma/gerar', [AlunosPorTurmaRelatorioController::class, 'gerar'])->name('relatorios.alunos-por-turma.gerar');
    Route::get('relatorios/alunos-por-turma/xlsx', [AlunosPorTurmaRelatorioController::class, 'exportarXlsx'])->name('relatorios.alunos-por-turma.xlsx');
    Route::get('relatorios/alunos-por-escola', [AlunosPorEscolaController::class, 'form'])->name('relatorios.alunos-por-escola.form');
    Route::get('relatorios/alunos-por-escola/gerar', [AlunosPorEscolaController::class, 'gerar'])->name('relatorios.alunos-por-escola.gerar');
    Route::get('relatorios/alunos-por-escola/xlsx', [AlunosPorEscolaController::class, 'exportarXlsx'])->name('relatorios.alunos-por-escola.xlsx');

    Route::get('relatorios/censo-criticas', [CensoCriticasController::class, 'form'])->name('relatorios.censo-criticas.form');
    Route::get('relatorios/censo-criticas/gerar', [CensoCriticasController::class, 'gerar'])->name('relatorios.censo-criticas.gerar');

    Route::get('relatorios/censo-exportacao', [CensoExportController::class, 'form'])->name('relatorios.censo-exportacao.form');
    Route::get('relatorios/censo-exportacao/download', [CensoExportController::class, 'download'])->name('relatorios.censo-exportacao.download');
    Route::get('relatorios/dados-alunos-turma', [DadosAlunosTurmaController::class, 'form'])->name('relatorios.dados-alunos-turma.form');
    Route::get('relatorios/dados-alunos-turma/gerar', [DadosAlunosTurmaController::class, 'gerar'])->name('relatorios.dados-alunos-turma.gerar');
    Route::get('relatorios/relacao-turmas-aee', [RelacaoTurmasAeeController::class, 'form'])->name('relatorios.relacao-turmas-aee.form');
    Route::get('relatorios/relacao-turmas-aee/gerar', [RelacaoTurmasAeeController::class, 'gerar'])->name('relatorios.relacao-turmas-aee.gerar');

    Route::get('relatorios/desempenho-aee', [DesempenhoAeeController::class, 'form'])->name('relatorios.desempenho-aee.form');
    Route::get('relatorios/desempenho-aee/turmas', [DesempenhoAeeController::class, 'turmas'])->name('relatorios.desempenho-aee.turmas');
    Route::get('relatorios/desempenho-aee/gerar', [DesempenhoAeeController::class, 'gerar'])->name('relatorios.desempenho-aee.gerar');
    Route::get('relatorios/relacao-turmas-atividade', [RelacaoTurmasAtividadeController::class, 'form'])->name('relatorios.relacao-turmas-atividade.form');
    Route::get('relatorios/relacao-turmas-atividade/gerar', [RelacaoTurmasAtividadeController::class, 'gerar'])->name('relatorios.relacao-turmas-atividade.gerar');
    Route::get('relatorios/declaracao-matricula', [DeclaracaoMatriculaController::class, 'form'])->name('relatorios.declaracao-matricula.form');
    Route::get('relatorios/declaracao-matricula/gerar', [DeclaracaoMatriculaController::class, 'gerar'])->name('relatorios.declaracao-matricula.gerar');
    Route::get('relatorios/declaracao-conclusao', [DeclaracaoConclusaoController::class, 'form'])->name('relatorios.declaracao-conclusao.form');
    Route::get('relatorios/declaracao-conclusao/gerar', [DeclaracaoConclusaoController::class, 'gerar'])->name('relatorios.declaracao-conclusao.gerar');
    Route::get('relatorios/termo-renovacao', [TermoRenovacaoController::class, 'form'])->name('relatorios.termo-renovacao.form');
    Route::get('relatorios/termo-renovacao/gerar', [TermoRenovacaoController::class, 'gerar'])->name('relatorios.termo-renovacao.gerar');
    Route::get('relatorios/formacao-classes', [FormacaoClassesController::class, 'form'])->name('relatorios.formacao-classes.form');
    Route::get('relatorios/formacao-classes/gerar', [FormacaoClassesController::class, 'gerar'])->name('relatorios.formacao-classes.gerar');
    Route::get('relatorios/formacao-classes/xlsx', [FormacaoClassesController::class, 'exportarXlsx'])->name('relatorios.formacao-classes.xlsx');
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
    Route::get('relatorios/equipe-gestora', [EquipeGestoraController::class, 'form'])->name('relatorios.equipe-gestora.form');
    Route::get('relatorios/equipe-gestora/gerar', [EquipeGestoraController::class, 'gerar'])->name('relatorios.equipe-gestora.gerar');
    Route::get('relatorios/equipe-gestora/xlsx', [EquipeGestoraController::class, 'exportarXlsx'])->name('relatorios.equipe-gestora.xlsx');
    Route::get('relatorios/pendencias-encerramento', [PendenciaEncerramentoController::class, 'form'])->name('relatorios.pendencias-encerramento.form');
    Route::get('relatorios/pendencias-encerramento/gerar', [PendenciaEncerramentoController::class, 'gerar'])->name('relatorios.pendencias-encerramento.gerar');
    Route::get('relatorios/pendencias-encerramento/xlsx', [PendenciaEncerramentoController::class, 'exportarXlsx'])->name('relatorios.pendencias-encerramento.xlsx');
    Route::get('relatorios/levantamento-municipio', [LevantamentoMunicipioController::class, 'form'])->name('relatorios.levantamento-municipio.form');
    Route::get('relatorios/levantamento-municipio/gerar', [LevantamentoMunicipioController::class, 'gerar'])->name('relatorios.levantamento-municipio.gerar');
    Route::get('relatorios/levantamento-municipio/xlsx', [LevantamentoMunicipioController::class, 'exportarXlsx'])->name('relatorios.levantamento-municipio.xlsx');
    Route::get('relatorios/relacao-escolas', [RelacaoEscolasController::class, 'form'])->name('relatorios.relacao-escolas.form');
    Route::get('relatorios/relacao-escolas/gerar', [RelacaoEscolasController::class, 'gerar'])->name('relatorios.relacao-escolas.gerar');
    Route::get('relatorios/relacao-escolas/xlsx', [RelacaoEscolasController::class, 'exportarXlsx'])->name('relatorios.relacao-escolas.xlsx');
    Route::get('relatorios/alunos-aptos-eja', [AlunosAptosEjaController::class, 'form'])->name('relatorios.alunos-aptos-eja.form');
    Route::get('relatorios/alunos-aptos-eja/gerar', [AlunosAptosEjaController::class, 'gerar'])->name('relatorios.alunos-aptos-eja.gerar');
    Route::get('relatorios/alunos-aptos-eja/xlsx', [AlunosAptosEjaController::class, 'exportarXlsx'])->name('relatorios.alunos-aptos-eja.xlsx');
    Route::get('relatorios/distorcao-idade', [DistorcaoIdadeController::class, 'form'])->name('relatorios.distorcao-idade.form');
    Route::get('relatorios/distorcao-idade/gerar', [DistorcaoIdadeController::class, 'gerar'])->name('relatorios.distorcao-idade.gerar');
    Route::get('relatorios/distorcao-idade/xlsx', [DistorcaoIdadeController::class, 'exportarXlsx'])->name('relatorios.distorcao-idade.xlsx');
    Route::get('relatorios/turmas-multisseriadas', [TurmasMultisseriadasController::class, 'form'])->name('relatorios.turmas-multisseriadas.form');
    Route::get('relatorios/turmas-multisseriadas/gerar', [TurmasMultisseriadasController::class, 'gerar'])->name('relatorios.turmas-multisseriadas.gerar');
    Route::get('relatorios/turmas-multisseriadas/xlsx', [TurmasMultisseriadasController::class, 'exportarXlsx'])->name('relatorios.turmas-multisseriadas.xlsx');

    Route::get('movimentacoes', [MovimentacaoController::class, 'index'])->name('movimentacoes.index');
    Route::get('movimentacoes/create', [MovimentacaoController::class, 'create'])->name('movimentacoes.create');
    Route::post('movimentacoes', [MovimentacaoController::class, 'store'])->name('movimentacoes.store');
    Route::get('movimentacoes/{movimentacao}', [MovimentacaoController::class, 'show'])->name('movimentacoes.show');
    Route::post('movimentacoes/{movimentacao}/desfazer', [MovimentacaoController::class, 'desfazer'])->name('movimentacoes.desfazer');
    Route::get('movimentacoes/{movimentacao}/declaracao-transferencia', [MovimentacaoController::class, 'declaracaoTransferencia'])->name('movimentacoes.declaracao-transferencia');

    // Remanejamento (troca de alunos entre turmas) — governado pela matriz de telas.
    Route::group([], function () {
        Route::get('remanejamento-turmas', [\App\Http\Controllers\Secretaria\RemanejamentoController::class, 'index'])->name('remanejamento-turmas.index');
        Route::get('remanejamento-turmas/turmas', [\App\Http\Controllers\Secretaria\RemanejamentoController::class, 'turmas'])->name('remanejamento-turmas.turmas');
        Route::get('remanejamento-turmas/alunos', [\App\Http\Controllers\Secretaria\RemanejamentoController::class, 'alunos'])->name('remanejamento-turmas.alunos');
        Route::post('remanejamento-turmas/remanejar', [\App\Http\Controllers\Secretaria\RemanejamentoController::class, 'remanejar'])->name('remanejamento-turmas.remanejar');
        Route::post('remanejamento-turmas/trocar', [\App\Http\Controllers\Secretaria\RemanejamentoController::class, 'trocar'])->name('remanejamento-turmas.trocar');
    });

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
    Route::get('api/povos-indigenas', [PovoIndigenaController::class, 'search'])->name('api.povos-indigenas.search');
    Route::get('api/bairros', [BairroController::class, 'search'])->name('api.bairros.search');
    Route::post('api/bairros', [BairroController::class, 'store'])->name('api.bairros.store');
    Route::get('api/gerencias', [GerenciaRegionalController::class, 'search'])->name('api.gerencias.search');
    Route::get('api/cargos', [CargoApiController::class, 'search'])->name('api.cargos.search');
    Route::get('api/cursos-formacao-superior', [CursoFormacaoApiController::class, 'search'])->name('api.cursos-formacao-superior.search');
    Route::get('api/instituicoes-ensino-superior', [InstituicaoEnsinoApiController::class, 'search'])->name('api.instituicoes-ensino-superior.search');
    Route::get('api/escolas', [EscolaApiController::class, 'search'])->name('api.escolas.search');
    Route::get('api/funcionarios', [FuncionarioApiController::class, 'search'])->name('api.funcionarios.search');
    Route::get('api/disciplinas/search', [DisciplinaApiController::class, 'search'])->name('api.disciplinas.search');
    Route::get('api/grade-disciplinar', [GradeDisciplinarController::class, 'index'])->name('api.grade-disciplinar.index');

    // Diário — Plano de Aula (turmas regulares). Acesso governado pela matriz
    // de telas (cada rota resolve sua própria tela: /diario, /diario/planos...).
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
    Route::prefix('api/diario')->name('api.diario.')->middleware('role:professor,coordenador,coordenador_interno,secretaria_escola')->group(function () {
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

        // Avaliação por diagnóstico (indicadores × alunos)
        Route::get('diagnostico/contexto', [\App\Http\Controllers\Diario\DiagnosticoController::class, 'contexto'])->name('diagnostico.contexto');
        Route::post('diagnostico/salvar', [\App\Http\Controllers\Diario\DiagnosticoController::class, 'salvar'])->name('diagnostico.salvar');

        // Lançamento de frequência (faltas/presenças por tempo do quadro de horário)
        Route::get('faltas/contexto', [\App\Http\Controllers\Diario\FaltaController::class, 'contexto'])->name('faltas.contexto');
        Route::post('faltas/salvar', [\App\Http\Controllers\Diario\FaltaController::class, 'salvarPresenca'])->name('faltas.salvar');
        Route::post('faltas/lote', [\App\Http\Controllers\Diario\FaltaController::class, 'salvarLote'])->name('faltas.lote');
        Route::post('faltas/conteudo', [\App\Http\Controllers\Diario\FaltaController::class, 'salvarConteudo'])->name('faltas.conteudo');
        Route::post('faltas/aula-nao-executada', [\App\Http\Controllers\Diario\FaltaController::class, 'salvarAulaNaoExecutada'])->name('faltas.aula-nao-executada');
        Route::get('planos/escolas', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'lookupEscolas'])->name('planos.escolas');
        Route::get('planos/series', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'lookupSeries'])->name('planos.series');
        Route::get('planos/disciplinas', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'lookupDisciplinas'])->name('planos.disciplinas');
        Route::get('planos/unidades', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'lookupUnidades'])->name('planos.unidades');
        Route::get('planos/indicadores', [\App\Http\Controllers\Diario\PlanoAulaController::class, 'lookupIndicadores'])->name('planos.indicadores');
    });

    // Lookups do Plano de Aula AEE — quem cadastra/valida plano AEE.
    Route::prefix('api/diario')->name('api.diario.')->middleware('role:professor_aee,coordenador_interno,gestor_secretaria')->group(function () {
        Route::get('planos-aee/escolas', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'lookupEscolas'])->name('planos-aee.escolas');
        Route::get('planos-aee/turmas', [\App\Http\Controllers\Diario\PlanoAeeController::class, 'lookupTurmas'])->name('planos-aee.turmas');
    });

    // Diário AEE — acesso governado pela matriz de telas.
    Route::prefix('diario-aee')->name('diario-aee.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Diario\DiarioAeeController::class, 'index'])->name('index');
    });
    Route::prefix('api/diario-aee')->name('api.diario-aee.')->middleware('role:professor_aee,gestor_secretaria,secretaria_escola')->group(function () {
        Route::get('contexto/escolas', [\App\Http\Controllers\Diario\DiarioAeeController::class, 'lookupEscolas'])->name('contexto.escolas');
        Route::get('contexto/turmas', [\App\Http\Controllers\Diario\DiarioAeeController::class, 'lookupTurmas'])->name('contexto.turmas');
        Route::get('contexto/unidades', [\App\Http\Controllers\Diario\DiarioAeeController::class, 'lookupUnidades'])->name('contexto.unidades');
        Route::get('contexto/atendimentos', [\App\Http\Controllers\Diario\DiarioAeeController::class, 'lookupAtendimentos'])->name('contexto.atendimentos');

        // Frequência AEE (chamada por dia de atendimento)
        Route::get('frequencia/contexto', [\App\Http\Controllers\Diario\AeeFrequenciaController::class, 'contexto'])->name('frequencia.contexto');
        Route::post('frequencia/salvar', [\App\Http\Controllers\Diario\AeeFrequenciaController::class, 'salvarPresenca'])->name('frequencia.salvar');
        Route::post('frequencia/lote', [\App\Http\Controllers\Diario\AeeFrequenciaController::class, 'salvarLote'])->name('frequencia.lote');
        Route::post('frequencia/conteudo', [\App\Http\Controllers\Diario\AeeFrequenciaController::class, 'salvarConteudo'])->name('frequencia.conteudo');

        // Avaliações descritivas AEE (aluno + data + texto rico)
        Route::get('avaliacoes/contexto', [\App\Http\Controllers\Diario\AeeAvaliacaoController::class, 'contexto'])->name('avaliacoes.contexto');
        Route::post('avaliacoes/salvar', [\App\Http\Controllers\Diario\AeeAvaliacaoController::class, 'salvar'])->name('avaliacoes.salvar');
        Route::delete('avaliacoes/{avaliacao}', [\App\Http\Controllers\Diario\AeeAvaliacaoController::class, 'destroy'])->name('avaliacoes.destroy');
    });

    // Diário de Atividade — acesso governado pela matriz de telas.
    Route::prefix('diario-atividade')->name('diario-atividade.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Diario\DiarioAtividadeController::class, 'index'])->name('index');
    });
    Route::prefix('api/diario-atividade')->name('api.diario-atividade.')->middleware('role:professor,gestor_secretaria')->group(function () {
        Route::get('contexto/escolas', [\App\Http\Controllers\Diario\DiarioAtividadeController::class, 'lookupEscolas'])->name('contexto.escolas');
        Route::get('contexto/turmas', [\App\Http\Controllers\Diario\DiarioAtividadeController::class, 'lookupTurmas'])->name('contexto.turmas');
        Route::get('contexto/unidades', [\App\Http\Controllers\Diario\DiarioAtividadeController::class, 'lookupUnidades'])->name('contexto.unidades');

        // Frequência das turmas de atividade (chamada por dia de atendimento)
        Route::get('frequencia/contexto', [\App\Http\Controllers\Diario\AtividadeFrequenciaController::class, 'contexto'])->name('frequencia.contexto');
        Route::post('frequencia/salvar', [\App\Http\Controllers\Diario\AtividadeFrequenciaController::class, 'salvarPresenca'])->name('frequencia.salvar');
        Route::post('frequencia/lote', [\App\Http\Controllers\Diario\AtividadeFrequenciaController::class, 'salvarLote'])->name('frequencia.lote');
        Route::post('frequencia/conteudo', [\App\Http\Controllers\Diario\AtividadeFrequenciaController::class, 'salvarConteudo'])->name('frequencia.conteudo');
    });

    // Coordenador Pedagógico — Validação de Planos (governado pela matriz de telas)
    Route::prefix('coordenador')->name('coordenador.')->group(function () {
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

    // Coordenador Pedagógico Interno — Validação de Planos AEE (governado pela matriz)
    Route::prefix('coordenador-interno')->name('coordenador-interno.')->group(function () {
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
    // Relatório Conteúdo Ministrado — área Pedagógica (coordenação). URL/nome mantidos.
    Route::prefix('secretaria')->name('secretaria.')->middleware('role:coordenador')->group(function () {
        Route::get('conteudo-ministrado', [SecretariaConteudoMinistradoController::class, 'form'])->name('conteudo-ministrado.form');
        Route::get('conteudo-ministrado/turmas', [SecretariaConteudoMinistradoController::class, 'turmas'])->name('conteudo-ministrado.turmas');
        Route::get('conteudo-ministrado/unidades', [SecretariaConteudoMinistradoController::class, 'unidades'])->name('conteudo-ministrado.unidades');
        Route::get('conteudo-ministrado/gerar', [SecretariaConteudoMinistradoController::class, 'gerar'])->name('conteudo-ministrado.gerar');
    });

    // Secretaria Escolar — acesso governado pela matriz de telas (cada rota
    // resolve sua tela: acessos-professores, lancamento-manual, etc.).
    Route::prefix('secretaria')->name('secretaria.')->group(function () {
        Route::get('acessos-professores/export', [AcessoProfessorController::class, 'export'])->name('acessos-professores.export');
        Route::get('acessos-professores', [AcessoProfessorController::class, 'index'])->name('acessos-professores.index');
        Route::post('acessos-professores/gerar', [AcessoProfessorController::class, 'gerar'])->name('acessos-professores.gerar');

        // Lançamento manual de notas e faltas (escolas de controle manual)
        Route::get('lancamento-manual', [LancamentoManualController::class, 'form'])->name('lancamento-manual.form');
        Route::get('lancamento-manual/turmas', [LancamentoManualController::class, 'turmas'])->name('lancamento-manual.turmas');
        Route::get('lancamento-manual/disciplinas', [LancamentoManualController::class, 'disciplinas'])->name('lancamento-manual.disciplinas');
        Route::get('lancamento-manual/contexto', [LancamentoManualController::class, 'contexto'])->name('lancamento-manual.contexto');
        Route::post('lancamento-manual/salvar', [LancamentoManualController::class, 'salvar'])->name('lancamento-manual.salvar');
        Route::post('lancamento-manual/salvar-recuperacao', [LancamentoManualController::class, 'salvarRecuperacao'])->name('lancamento-manual.salvar-recuperacao');

        // Lançamento manual de histórico escolar (mesma tela do cadastro do aluno).
        // Dados via alunos/{aluno}/historico-manual (HistoricoManualController).
        Route::get('historico-escolar', [\App\Http\Controllers\Aluno\HistoricoManualController::class, 'form'])->name('historico-escolar.form');

        // Interesse de renovação de matrícula
        Route::get('renovacao-matricula', [RenovacaoMatriculaController::class, 'index'])->name('renovacao-matricula.index');
        Route::get('renovacao-matricula/turmas', [RenovacaoMatriculaController::class, 'turmas'])->name('renovacao-matricula.turmas');
        Route::get('renovacao-matricula/alunos', [RenovacaoMatriculaController::class, 'alunos'])->name('renovacao-matricula.alunos');
        Route::post('renovacao-matricula/salvar', [RenovacaoMatriculaController::class, 'salvar'])->name('renovacao-matricula.salvar');

        // Cadastro: Motivos de baixa frequência (justificativa de falta)
        Route::get('motivos-baixa-frequencia', [MotivoBaixaFrequenciaController::class, 'index'])->name('motivos-baixa-frequencia.index');
        Route::post('motivos-baixa-frequencia', [MotivoBaixaFrequenciaController::class, 'store'])->name('motivos-baixa-frequencia.store');
        Route::put('motivos-baixa-frequencia/{motivo}', [MotivoBaixaFrequenciaController::class, 'update'])->name('motivos-baixa-frequencia.update');
        Route::delete('motivos-baixa-frequencia/{motivo}', [MotivoBaixaFrequenciaController::class, 'destroy'])->name('motivos-baixa-frequencia.destroy');

        // Justificativa de falta (por período)
        Route::get('justificativas-falta', [JustificativaFaltaController::class, 'index'])->name('justificativas-falta.index');
        Route::get('justificativas-falta/turmas', [JustificativaFaltaController::class, 'turmas'])->name('justificativas-falta.turmas');
        Route::get('justificativas-falta/alunos', [JustificativaFaltaController::class, 'alunos'])->name('justificativas-falta.alunos');
        Route::post('justificativas-falta', [JustificativaFaltaController::class, 'store'])->name('justificativas-falta.store');
        Route::put('justificativas-falta/{justificativa}', [JustificativaFaltaController::class, 'update'])->name('justificativas-falta.update');
        Route::delete('justificativas-falta/{justificativa}', [JustificativaFaltaController::class, 'destroy'])->name('justificativas-falta.destroy');
    });

    // Configuração/Parâmetros — governado pela matriz de telas. As rotas
    // parametros/* resolvem para a tela /parametros (somente admin); telas
    // próprias (sabados-letivos, grade-disciplinar, atividades...) seguem a matriz.
    Route::group([], function () {
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

        Route::post('parametros/dias-letivos', [DiasLetivosController::class, 'salvar'])->name('parametros.dias-letivos.salvar');

        Route::post('parametros/grade-horarios', [GradeHorarioController::class, 'store'])->name('parametros.grade-horarios.store');
        Route::put('parametros/grade-horarios/{gradeHorario}', [GradeHorarioController::class, 'update'])->name('parametros.grade-horarios.update');
        Route::delete('parametros/grade-horarios/{gradeHorario}', [GradeHorarioController::class, 'destroy'])->name('parametros.grade-horarios.destroy');

        Route::get('sabados-letivos', [SabadoLetivoController::class, 'index'])->name('sabados-letivos.index');
        Route::post('sabados-letivos', [SabadoLetivoController::class, 'store'])->name('sabados-letivos.store');
        Route::put('sabados-letivos/{sabadoLetivo}', [SabadoLetivoController::class, 'update'])->name('sabados-letivos.update');
        Route::delete('sabados-letivos/{sabadoLetivo}', [SabadoLetivoController::class, 'destroy'])->name('sabados-letivos.destroy');

        Route::get('grade-disciplinar', [GradeDisciplinarController::class, 'page'])->name('grade-disciplinar.index');
        Route::post('parametros/grade-disciplinar', [GradeDisciplinarController::class, 'store'])->name('parametros.grade-disciplinar.store');
        Route::post('parametros/grade-disciplinar/clonar', [GradeDisciplinarController::class, 'clonar'])->name('parametros.grade-disciplinar.clonar');
        Route::put('parametros/grade-disciplinar/{grade}', [GradeDisciplinarController::class, 'update'])->name('parametros.grade-disciplinar.update');
        Route::patch('parametros/grade-disciplinar/{grade}/ordem', [GradeDisciplinarController::class, 'reordenar'])->name('parametros.grade-disciplinar.reordenar');
        Route::delete('parametros/grade-disciplinar/{grade}', [GradeDisciplinarController::class, 'destroy'])->name('parametros.grade-disciplinar.destroy');
    });
});

require __DIR__.'/alimentacao.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
