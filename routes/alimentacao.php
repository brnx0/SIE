<?php

use App\Http\Controllers\Alimentacao\CardapioController;
use App\Http\Controllers\Alimentacao\CardapioDiaController;
use App\Http\Controllers\Alimentacao\CategoriaAlimentoController;
use App\Http\Controllers\Alimentacao\ContratoAditivoController;
use App\Http\Controllers\Alimentacao\ContratoController;
use App\Http\Controllers\Alimentacao\ContratoItemController;
use App\Http\Controllers\Alimentacao\ConversaoUnidadeController;
use App\Http\Controllers\Alimentacao\DemandaController;
use App\Http\Controllers\Alimentacao\EmpenhoController;
use App\Http\Controllers\Alimentacao\EstoqueController;
use App\Http\Controllers\Alimentacao\FornecedorController;
use App\Http\Controllers\Alimentacao\RecebimentoController;
use App\Http\Controllers\Alimentacao\RetiradaController;
use App\Http\Controllers\Alimentacao\TransferenciaController;
use App\Http\Controllers\Alimentacao\ItemConversaoController;
use App\Http\Controllers\Alimentacao\ItemController;
use App\Http\Controllers\Alimentacao\MarcaController;
use App\Http\Controllers\Alimentacao\OrdemFornecimentoController;
use App\Http\Controllers\Alimentacao\OrdemFornecimentoItemController;
use App\Http\Controllers\Alimentacao\PerguntaController;
use App\Http\Controllers\Alimentacao\PratoController;
use App\Http\Controllers\Alimentacao\PratoItemController;
use App\Http\Controllers\Alimentacao\PreparacaoController;
use App\Http\Controllers\Alimentacao\TipoItemController;
use App\Http\Controllers\Alimentacao\TipoQuestionarioController;
use App\Http\Controllers\Alimentacao\TipoVisitaController;
use App\Http\Controllers\Alimentacao\VisitaController;
use App\Http\Controllers\Alimentacao\TipoRetiradaController;
use App\Http\Controllers\Alimentacao\UnidadeMedidaController;
use App\Http\Controllers\Relatorio\Alimentacao\ItensVencimentoController;
use App\Http\Controllers\Relatorio\Alimentacao\ProtocoloDistribuicaoController;
use App\Http\Controllers\Relatorio\Alimentacao\RetiradaAlimentosController;
use App\Http\Controllers\Api\Alimentacao\ContratoController as ContratoApiController;
use App\Http\Controllers\Api\Alimentacao\FornecedorController as FornecedorApiController;
use App\Http\Controllers\Api\Alimentacao\ItemController as ItemApiController;
use App\Http\Controllers\Api\Alimentacao\PratoController as PratoApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Alimentação Escolar
|--------------------------------------------------------------------------
|
| Módulo de alimentação escolar (PNAE): cadastros, cardápio, contratos,
| estoque e visitas técnicas. Acesso governado pela matriz `cfg_tela`
| (middleware `tela`), como o resto do sistema.
|
*/

Route::middleware(['auth', 'verified', 'tela'])
    ->prefix('alimentacao')
    ->name('alimentacao.')
    ->group(function () {
        // Visitas técnicas: programação, questionário e ateste.
        Route::prefix('visitas')->name('visitas.')->group(function () {
            Route::get('export', [VisitaController::class, 'export'])->name('export');
            Route::get('/', [VisitaController::class, 'index'])->name('index');
            Route::get('create', [VisitaController::class, 'create'])->name('create');
            Route::post('/', [VisitaController::class, 'store'])->name('store');
            Route::get('{id}/edit', [VisitaController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [VisitaController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [VisitaController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::put('{id}/respostas/{resposta}', [VisitaController::class, 'responder'])->name('responder');
            Route::post('{id}/realizar', [VisitaController::class, 'realizar'])->name('realizar');
            Route::post('{id}/atestar', [VisitaController::class, 'atestar'])->name('atestar');
            Route::post('{id}/cancelar', [VisitaController::class, 'cancelar'])->name('cancelar');

            // Anexos (evidências/documentos: PDF ou imagem).
            Route::prefix('{id}/anexos')->name('anexos.')->group(function () {
                Route::post('/', [VisitaController::class, 'anexosStore'])->whereNumber('id')->name('store');
                Route::get('{anexo}', [VisitaController::class, 'anexoDownload'])->whereNumber(['id', 'anexo'])->name('download');
                Route::delete('{anexo}', [VisitaController::class, 'anexoDestroy'])->whereNumber(['id', 'anexo'])->name('destroy');
            });
        });

        // Perguntas do questionário (+ opções da múltipla escolha).
        Route::prefix('perguntas')->name('perguntas.')->group(function () {
            Route::get('/', [PerguntaController::class, 'index'])->name('index');
            Route::post('/', [PerguntaController::class, 'store'])->name('store');
            Route::put('{id}', [PerguntaController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [PerguntaController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        // Controle diário da preparação (confirmar baixa o estoque da escola).
        Route::prefix('preparacoes')->name('preparacoes.')->group(function () {
            Route::get('export', [PreparacaoController::class, 'export'])->name('export');
            Route::get('/', [PreparacaoController::class, 'index'])->name('index');
            Route::get('create', [PreparacaoController::class, 'create'])->name('create');
            Route::post('/', [PreparacaoController::class, 'store'])->name('store');
            Route::get('{id}/edit', [PreparacaoController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::delete('{id}', [PreparacaoController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::put('{id}/itens/{item}', [PreparacaoController::class, 'ajustarItem'])->name('itens.ajustar');
            Route::post('{id}/confirmar', [PreparacaoController::class, 'confirmar'])->name('confirmar');
        });

        // Demanda mensal de merenda (cardápio × calendário letivo × alunos).
        Route::prefix('demandas')->name('demandas.')->group(function () {
            Route::get('/', [DemandaController::class, 'index'])->name('index');
            Route::get('create', [DemandaController::class, 'create'])->name('create');
            Route::post('/', [DemandaController::class, 'store'])->name('store');
            Route::get('{id}/edit', [DemandaController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::get('{id}/export', [DemandaController::class, 'export'])->whereNumber('id')->name('export');
            Route::delete('{id}', [DemandaController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::put('{id}/itens/{item}', [DemandaController::class, 'ajustarItem'])->name('itens.ajustar');
            Route::post('{id}/calcular', [DemandaController::class, 'calcular'])->name('calcular');
            Route::post('{id}/fechar', [DemandaController::class, 'fechar'])->name('fechar');
            Route::post('{id}/reabrir', [DemandaController::class, 'reabrir'])->name('reabrir');
        });

        // Estoque: consulta de saldo e histórico de movimentação (o razão).
        Route::prefix('estoque')->name('estoque.')->group(function () {
            Route::get('export', [EstoqueController::class, 'export'])->name('export');
            Route::get('movimentos/export', [EstoqueController::class, 'exportMovimentos'])->name('movimentos.export');
            Route::get('movimentos', [EstoqueController::class, 'movimentos'])->name('movimentos');
            Route::get('/', [EstoqueController::class, 'index'])->name('index');
        });

        // Transferência do central para a escola (par de movimentos via FEFO).
        Route::prefix('transferencias')->name('transferencias.')->group(function () {
            Route::get('create', [TransferenciaController::class, 'create'])->name('create');
            Route::get('{transferencia}/pdf', [TransferenciaController::class, 'pdf'])
                ->whereUuid('transferencia')->name('pdf');
            Route::get('/', fn () => redirect()->route('alimentacao.transferencias.create'))->name('index');
            Route::post('/', [TransferenciaController::class, 'store'])->name('store');
        });

        // Retirada do estoque da unidade (uso na cozinha, descarte, vencimento).
        Route::prefix('retiradas')->name('retiradas.')->group(function () {
            Route::get('create', [RetiradaController::class, 'create'])->name('create');
            Route::get('/', fn () => redirect()->route('alimentacao.retiradas.create'))->name('index');
            Route::post('/', [RetiradaController::class, 'store'])->name('store');
        });

        // Recebimento: confere a entrega e, no aceite, dá entrada no estoque.
        Route::prefix('recebimentos')->name('recebimentos.')->group(function () {
            Route::get('/', [RecebimentoController::class, 'index'])->name('index');
            Route::get('create', [RecebimentoController::class, 'create'])->name('create');
            Route::post('/', [RecebimentoController::class, 'store'])->name('store');
            Route::get('{id}/edit', [RecebimentoController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::delete('{id}', [RecebimentoController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::put('{id}/itens/{item}', [RecebimentoController::class, 'conferir'])->name('itens.conferir');
            Route::post('{id}/aceitar', [RecebimentoController::class, 'aceitar'])->name('aceitar');
        });

        // Contratos + itens contratados + aditivos (log de alteração).
        Route::prefix('contratos')->name('contratos.')->group(function () {
            Route::get('export', [ContratoController::class, 'export'])->name('export');
            Route::get('/', [ContratoController::class, 'index'])->name('index');
            Route::get('create', [ContratoController::class, 'create'])->name('create');
            Route::post('/', [ContratoController::class, 'store'])->name('store');
            Route::get('{id}/edit', [ContratoController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [ContratoController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [ContratoController::class, 'destroy'])->whereNumber('id')->name('destroy');

            Route::prefix('{id}/itens')->name('itens.')->group(function () {
                Route::post('/', [ContratoItemController::class, 'store'])->name('store');
                Route::put('{contratoItem}', [ContratoItemController::class, 'update'])->name('update');
                Route::delete('{contratoItem}', [ContratoItemController::class, 'destroy'])->name('destroy');
            });

            // Aditivo é log: sem update/destroy. Desfazer é cancelar — o que
            // reverte o contrato e fica registrado com autor e justificativa.
            Route::post('{id}/aditivos', [ContratoAditivoController::class, 'store'])->name('aditivos.store');
            Route::post('{id}/aditivos/{aditivo}/cancelar', [ContratoAditivoController::class, 'cancelar'])->name('aditivos.cancelar');

            // Anexos (PDF/imagem). Aninhados sob {id} para herdar a permissão da tela.
            Route::prefix('{id}/anexos')->name('anexos.')->group(function () {
                Route::post('/', [ContratoController::class, 'anexosStore'])->whereNumber('id')->name('store');
                Route::get('{anexo}', [ContratoController::class, 'anexoDownload'])->whereNumber(['id', 'anexo'])->name('download');
                Route::delete('{anexo}', [ContratoController::class, 'anexoDestroy'])->whereNumber(['id', 'anexo'])->name('destroy');
            });
        });

        // Empenhos (reserva orçamentária que custeia as ordens).
        Route::prefix('empenhos')->name('empenhos.')->group(function () {
            Route::get('export', [EmpenhoController::class, 'export'])->name('export');
            Route::get('/', [EmpenhoController::class, 'index'])->name('index');
            Route::get('create', [EmpenhoController::class, 'create'])->name('create');
            Route::post('/', [EmpenhoController::class, 'store'])->name('store');
            Route::get('{id}/edit', [EmpenhoController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [EmpenhoController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [EmpenhoController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        // Ordens de fornecimento + itens pedidos.
        Route::prefix('ordens-fornecimento')->name('ordens-fornecimento.')->group(function () {
            Route::get('export', [OrdemFornecimentoController::class, 'export'])->name('export');
            Route::get('/', [OrdemFornecimentoController::class, 'index'])->name('index');
            Route::get('create', [OrdemFornecimentoController::class, 'create'])->name('create');
            Route::post('/', [OrdemFornecimentoController::class, 'store'])->name('store');
            Route::get('{id}/edit', [OrdemFornecimentoController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [OrdemFornecimentoController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('{id}/cancelar', [OrdemFornecimentoController::class, 'cancelar'])->whereNumber('id')->name('cancelar');
            Route::delete('{id}', [OrdemFornecimentoController::class, 'destroy'])->whereNumber('id')->name('destroy');

            Route::prefix('{id}/itens')->name('itens.')->group(function () {
                Route::post('/', [OrdemFornecimentoItemController::class, 'store'])->name('store');
                Route::put('{ordemItem}', [OrdemFornecimentoItemController::class, 'update'])->name('update');
                Route::delete('{ordemItem}', [OrdemFornecimentoItemController::class, 'destroy'])->name('destroy');
            });
        });

        // Cardápio semanal + grade (dia da semana × turno × refeição).
        Route::prefix('cardapios')->name('cardapios.')->group(function () {
            Route::get('export', [CardapioController::class, 'export'])->name('export');
            Route::get('/', [CardapioController::class, 'index'])->name('index');
            Route::get('create', [CardapioController::class, 'create'])->name('create');
            Route::post('/', [CardapioController::class, 'store'])->name('store');
            Route::get('{id}/edit', [CardapioController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [CardapioController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [CardapioController::class, 'destroy'])->whereNumber('id')->name('destroy');

            Route::prefix('{id}/dias')->name('dias.')->group(function () {
                Route::post('/', [CardapioDiaController::class, 'store'])->name('store');
                Route::put('{dia}', [CardapioDiaController::class, 'update'])->name('update');
                Route::delete('{dia}', [CardapioDiaController::class, 'destroy'])->name('destroy');
            });
        });

        // Pratos + ficha técnica (itens e quantidade por aluno).
        Route::prefix('pratos')->name('pratos.')->group(function () {
            Route::get('export', [PratoController::class, 'export'])->name('export');
            Route::get('/', [PratoController::class, 'index'])->name('index');
            Route::get('create', [PratoController::class, 'create'])->name('create');
            Route::post('/', [PratoController::class, 'store'])->name('store');
            Route::get('{id}/edit', [PratoController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [PratoController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [PratoController::class, 'destroy'])->whereNumber('id')->name('destroy');

            Route::prefix('{id}/itens')->name('itens.')->group(function () {
                Route::post('/', [PratoItemController::class, 'store'])->name('store');
                Route::put('{pratoItem}', [PratoItemController::class, 'update'])->name('update');
                Route::delete('{pratoItem}', [PratoItemController::class, 'destroy'])->name('destroy');
            });
        });

        // Fornecedores de gêneros alimentícios (inclui agricultura familiar).
        Route::prefix('fornecedores')->name('fornecedores.')->group(function () {
            Route::get('export', [FornecedorController::class, 'export'])->name('export');
            Route::get('/', [FornecedorController::class, 'index'])->name('index');
            Route::get('create', [FornecedorController::class, 'create'])->name('create');
            Route::post('/', [FornecedorController::class, 'store'])->name('store');
            Route::get('{id}/edit', [FornecedorController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [FornecedorController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [FornecedorController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        // Itens (gêneros alimentícios) + conversões específicas do item.
        Route::prefix('itens')->name('itens.')->group(function () {
            Route::get('export', [ItemController::class, 'export'])->name('export');
            Route::get('/', [ItemController::class, 'index'])->name('index');
            Route::get('create', [ItemController::class, 'create'])->name('create');
            Route::post('/', [ItemController::class, 'store'])->name('store');
            Route::get('{id}/edit', [ItemController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [ItemController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [ItemController::class, 'destroy'])->whereNumber('id')->name('destroy');

            Route::prefix('{id}/conversoes')->name('conversoes.')->group(function () {
                Route::post('/', [ItemConversaoController::class, 'store'])->name('store');
                Route::put('{conversao}', [ItemConversaoController::class, 'update'])->name('update');
                Route::delete('{conversao}', [ItemConversaoController::class, 'destroy'])->name('destroy');
            });
        });

        // Unidades de medida + suas conversões globais (sub-lista da tela de edição).
        Route::prefix('unidades-medida')->name('unidades-medida.')->group(function () {
            Route::get('export', [UnidadeMedidaController::class, 'export'])->name('export');
            Route::get('/', [UnidadeMedidaController::class, 'index'])->name('index');
            Route::get('create', [UnidadeMedidaController::class, 'create'])->name('create');
            Route::post('/', [UnidadeMedidaController::class, 'store'])->name('store');
            Route::get('{id}/edit', [UnidadeMedidaController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [UnidadeMedidaController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [UnidadeMedidaController::class, 'destroy'])->whereNumber('id')->name('destroy');

            Route::prefix('{id}/conversoes')->name('conversoes.')->group(function () {
                Route::post('/', [ConversaoUnidadeController::class, 'store'])->name('store');
                Route::put('{conversao}', [ConversaoUnidadeController::class, 'update'])->name('update');
                Route::delete('{conversao}', [ConversaoUnidadeController::class, 'destroy'])->name('destroy');
            });
        });

        // Catálogos simples (nome + situação) — todos servidos por CatalogoSimplesController.
        $catalogos = [
            'categorias-alimento' => CategoriaAlimentoController::class,
            'tipos-item'          => TipoItemController::class,
            'marcas'              => MarcaController::class,
            'tipos-retirada'      => TipoRetiradaController::class,
            'tipos-visita'        => TipoVisitaController::class,
            'tipos-questionario'  => TipoQuestionarioController::class,
        ];

        foreach ($catalogos as $slug => $controller) {
            Route::prefix($slug)->name($slug.'.')->group(function () use ($controller) {
                Route::get('export', [$controller, 'export'])->name('export');
                Route::get('/', [$controller, 'index'])->name('index');
                Route::get('create', [$controller, 'create'])->name('create');
                Route::post('/', [$controller, 'store'])->name('store');
                Route::get('{id}/edit', [$controller, 'edit'])->whereNumber('id')->name('edit');
                Route::put('{id}', [$controller, 'update'])->whereNumber('id')->name('update');
                Route::delete('{id}', [$controller, 'destroy'])->whereNumber('id')->name('destroy');
            });
        }
    });

/*
| Relatórios da alimentação.
|
| Moram sob `/relatorios/*` (fora do prefixo `alimentacao`) porque saem pelos
| cards da central, como o resto dos relatórios do sistema — o card de cada um
| está em `config/relatorios.php`, no grupo `alimentacao`.
|
| Cada rota tem linha própria na matriz `cfg_tela`: sem isso o middleware `tela`
| resolveria por prefixo para a tela `/relatorios`, onde nutricionista e
| almoxarife não têm nível e levariam 403.
*/
Route::middleware(['auth', 'verified', 'tela'])
    ->prefix('relatorios')
    ->name('relatorios.')
    ->group(function () {
        Route::get('protocolo-distribuicao', [ProtocoloDistribuicaoController::class, 'form'])->name('protocolo-distribuicao.form');
        Route::get('protocolo-distribuicao/gerar', [ProtocoloDistribuicaoController::class, 'gerar'])->name('protocolo-distribuicao.gerar');

        Route::get('itens-vencimento', [ItensVencimentoController::class, 'form'])->name('itens-vencimento.form');
        Route::get('itens-vencimento/gerar', [ItensVencimentoController::class, 'gerar'])->name('itens-vencimento.gerar');

        Route::get('retirada-alimentos', [RetiradaAlimentosController::class, 'form'])->name('retirada-alimentos.form');
        Route::get('retirada-alimentos/gerar', [RetiradaAlimentosController::class, 'gerar'])->name('retirada-alimentos.gerar');
    });

// Lookups da alimentação. Ficam fora do prefixo `alimentacao` (e da matriz de
// telas) porque são consumidos por várias telas do módulo, como o resto das
// rotas `api/*` do sistema.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('api/alimentacao/itens/search', [ItemApiController::class, 'search'])->name('api.alimentacao.itens.search');
    Route::get('api/alimentacao/fornecedores/search', [FornecedorApiController::class, 'search'])->name('api.alimentacao.fornecedores.search');
    Route::get('api/alimentacao/pratos/search', [PratoApiController::class, 'search'])->name('api.alimentacao.pratos.search');
    Route::get('api/alimentacao/contratos/search', [ContratoApiController::class, 'search'])->name('api.alimentacao.contratos.search');
});
