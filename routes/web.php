<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AmizadeController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\CursoFileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceiroContaController;
use App\Http\Controllers\FinanceiroDashboardController;
use App\Http\Controllers\InvestimentoAporteController;
use App\Http\Controllers\InvestimentoController;
use App\Http\Controllers\LancamentoController;
use App\Http\Controllers\LancamentoFileController;
use App\Http\Controllers\NotaController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\ParticipacaoController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ProjetoCollaborationController;
use App\Http\Controllers\ProjetoController;
use App\Http\Controllers\ProjetoEtiquetaController;
use App\Http\Controllers\ProjetoTaskController;
use App\Http\Controllers\QuadroCartaoActionController;
use App\Http\Controllers\QuadroCartaoArquivoController;
use App\Http\Controllers\QuadroCartaoController;
use App\Http\Controllers\QuadroController;
use App\Http\Controllers\QuadroEtiquetaController;
use App\Http\Controllers\QuadroListaController;
use App\Http\Controllers\QuadroMembroController;
use App\Http\Controllers\SonhoController;
use App\Http\Controllers\SonhoTaskController;
use App\Http\Controllers\TarefaController;
use App\Http\Controllers\TarefaFileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::put('/dashboard/preferences', [DashboardController::class, 'update'])->name('dashboard.preferences');

    Route::middleware('can:manage-users')->prefix('admin/usuarios')->name('admin.usuarios.')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::post('/', [AdminUserController::class, 'store'])->name('store');
        Route::get('/novo', [AdminUserController::class, 'create'])->name('create');
        Route::get('/{user}/editar', [AdminUserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [AdminUserController::class, 'update'])->name('update');
        Route::get('/{user}/excluir', [AdminUserController::class, 'confirmDeletion'])->name('confirm-deletion');
        Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('destroy');
    });

    Route::get('/', [SonhoController::class, 'index'])->name('sonhos');
    Route::post('/sonhos', [SonhoController::class, 'store'])->name('sonhos.store');
    Route::put('/sonhos/{sonho}', [SonhoController::class, 'update'])->name('sonhos.update');
    Route::delete('/sonhos/{sonho}', [SonhoController::class, 'destroy'])->name('sonhos.destroy');
    Route::post('/sonhos/{sonho}/tarefas', [SonhoTaskController::class, 'store'])->name('sonhos.tarefas.store');
    Route::patch('/sonhos/{sonho}/tarefas/{tarefa}', [SonhoTaskController::class, 'update'])->name('sonhos.tarefas.update');
    Route::delete('/sonhos/{sonho}/tarefas/{tarefa}', [SonhoTaskController::class, 'destroy'])->name('sonhos.tarefas.destroy');

    Route::get('/projetos', [ProjetoController::class, 'index'])->name('projetos');
    Route::post('/projetos', [ProjetoController::class, 'store'])->name('projetos.store');
    Route::put('/projetos/{projeto}', [ProjetoController::class, 'update'])->name('projetos.update');
    Route::delete('/projetos/{projeto}', [ProjetoController::class, 'destroy'])->name('projetos.destroy');
    Route::post('/projetos/{projeto}/tarefas', [ProjetoTaskController::class, 'store'])->name('projetos.tarefas.store');
    Route::post('/projetos/{projeto}/tarefas/{tarefa}/agendar', [ProjetoTaskController::class, 'schedule'])->name('projetos.tarefas.agendar');
    Route::patch('/projetos/{projeto}/tarefas/{tarefa}', [ProjetoTaskController::class, 'update'])->name('projetos.tarefas.update');
    Route::delete('/projetos/{projeto}/tarefas/{tarefa}', [ProjetoTaskController::class, 'destroy'])->name('projetos.tarefas.destroy');

    Route::get('/projetos/{projeto}/etiquetas', [ProjetoEtiquetaController::class, 'index'])->name('projetos.etiquetas.index');
    Route::post('/projetos/{projeto}/etiquetas', [ProjetoEtiquetaController::class, 'store'])->name('projetos.etiquetas.store');
    Route::patch('/projetos/{projeto}/etiquetas/{etiqueta}', [ProjetoEtiquetaController::class, 'update'])->name('projetos.etiquetas.update');
    Route::delete('/projetos/{projeto}/etiquetas/{etiqueta}', [ProjetoEtiquetaController::class, 'destroy'])->name('projetos.etiquetas.destroy');

    Route::get('/projetos/{projeto}/comentarios', [ProjetoCollaborationController::class, 'comments'])->name('projetos.comentarios.index');
    Route::patch('/projetos/{projeto}/comentarios/{comentario}', [ProjetoCollaborationController::class, 'updateComment'])->name('projetos.comentarios.update');
    Route::post('/projetos/{projeto}/comentarios', [ProjetoCollaborationController::class, 'comment'])->name('projetos.comentarios.store');
    Route::delete('/projetos/{projeto}/comentarios/{comentario}', [ProjetoCollaborationController::class, 'destroyComment'])->name('projetos.comentarios.destroy');
    Route::post('/projetos/{projeto}/arquivos', [ProjetoCollaborationController::class, 'upload'])->name('projetos.arquivos.store');
    Route::get('/projetos/{projeto}/arquivos/{arquivo}/preview', [ProjetoCollaborationController::class, 'preview'])->name('projetos.arquivos.preview');
    Route::get('/projetos/{projeto}/arquivos/{arquivo}', [ProjetoCollaborationController::class, 'download'])->name('projetos.arquivos.download');
    Route::delete('/projetos/{projeto}/arquivos/{arquivo}', [ProjetoCollaborationController::class, 'destroyFile'])->name('projetos.arquivos.destroy');

    Route::get('/cursos', [QuadroController::class, 'index'])->name('cursos');
    Route::post('/quadros', [QuadroController::class, 'store'])->name('quadros.store');
    Route::get('/quadros/{quadro}', [QuadroController::class, 'show'])->name('quadros.show');
    Route::get('/quadros/{quadro}/editar', [QuadroController::class, 'edit'])->name('quadros.edit');
    Route::post('/quadros/{quadro}', [QuadroController::class, 'update'])->name('quadros.update');
    Route::delete('/quadros/{quadro}', [QuadroController::class, 'destroy'])->name('quadros.destroy');
    Route::get('/quadros/{quadro}/capa', [QuadroController::class, 'cover'])->name('quadros.cover');
    Route::post('/quadros/{quadro}/listas', [QuadroListaController::class, 'store'])->name('quadros.listas.store');
    Route::put('/quadros/{quadro}/listas/ordem', [QuadroListaController::class, 'reorder'])->name('quadros.listas.ordem');
    Route::patch('/quadros/{quadro}/listas/{lista}', [QuadroListaController::class, 'update'])->name('quadros.listas.update');
    Route::delete('/quadros/{quadro}/listas/{lista}', [QuadroListaController::class, 'destroy'])->name('quadros.listas.destroy');
    Route::get('/quadros/{quadro}/novo-cartao', [QuadroCartaoController::class, 'create'])->name('quadros.cartoes.create');
    Route::post('/quadros/{quadro}/cartoes', [QuadroCartaoController::class, 'store'])->name('quadros.cartoes.store');
    Route::get('/quadros/{quadro}/cartoes/{cartao}', [QuadroCartaoController::class, 'show'])->name('quadros.cartoes.show');
    Route::patch('/quadros/{quadro}/cartoes/{cartao}', [QuadroCartaoController::class, 'update'])->name('quadros.cartoes.update');
    Route::patch('/quadros/{quadro}/cartoes/{cartao}/mover', [QuadroCartaoController::class, 'move'])->name('quadros.cartoes.move');
    Route::post('/quadros/{quadro}/cartoes/{cartao}/copiar', [QuadroCartaoActionController::class, 'store'])->name('quadros.cartoes.copy');
    Route::post('/quadros/{quadro}/etiquetas', [QuadroEtiquetaController::class, 'store'])->name('quadros.etiquetas.store');
    Route::patch('/quadros/{quadro}/etiquetas/{etiqueta}', [QuadroEtiquetaController::class, 'update'])->name('quadros.etiquetas.update');
    Route::delete('/quadros/{quadro}/etiquetas/{etiqueta}', [QuadroEtiquetaController::class, 'destroy'])->name('quadros.etiquetas.destroy');
    Route::delete('/quadros/{quadro}/cartoes/{cartao}', [QuadroCartaoController::class, 'destroy'])->name('quadros.cartoes.destroy');
    Route::get('/quadros/{quadro}/cartoes/{cartao}/comentarios', [QuadroCartaoController::class, 'comments'])->name('quadros.comentarios.index');
    Route::post('/quadros/{quadro}/cartoes/{cartao}/comentarios/{comentario}/visualizar', [QuadroCartaoController::class, 'readMention'])->name('quadros.comentarios.read');
    Route::post('/quadros/{quadro}/cartoes/{cartao}/comentarios', [QuadroCartaoController::class, 'comment'])->name('quadros.comentarios.store');
    Route::patch('/quadros/{quadro}/cartoes/{cartao}/comentarios/{comentario}', [QuadroCartaoController::class, 'updateComment'])->name('quadros.comentarios.update');
    Route::delete('/quadros/{quadro}/cartoes/{cartao}/comentarios/{comentario}', [QuadroCartaoController::class, 'destroyComment'])->name('quadros.comentarios.destroy');
    Route::patch('/quadros/{quadro}/cartoes/{cartao}/comentarios/{comentario}/fixar', [QuadroCartaoController::class, 'pinComment'])->name('quadros.comentarios.pin');
    Route::post('/quadros/{quadro}/cartoes/{cartao}/comentarios/{comentario}/reagir', [QuadroCartaoController::class, 'reactComment'])->name('quadros.comentarios.react');
    Route::get('/quadros/{quadro}/cartoes/{cartao}/arquivos/{arquivo}', [QuadroCartaoController::class, 'legacyFile'])->name('quadros.legacy-file');
    Route::post('/quadros/{quadro}/cartoes/{cartao}/anexos', [QuadroCartaoArquivoController::class, 'store'])->name('quadros.arquivos.store');
    Route::get('/quadros/{quadro}/cartoes/{cartao}/anexos/{arquivo}', [QuadroCartaoArquivoController::class, 'show'])->name('quadros.arquivos.show');
    Route::delete('/quadros/{quadro}/cartoes/{cartao}/anexos/{arquivo}', [QuadroCartaoArquivoController::class, 'destroy'])->name('quadros.arquivos.destroy');
    Route::get('/quadros/{quadro}/membros', [QuadroMembroController::class, 'index'])->name('quadros.membros.index');
    Route::post('/quadros/{quadro}/membros', [QuadroMembroController::class, 'store'])->name('quadros.membros.store');
    Route::delete('/quadros/{quadro}/membros/{membro}', [QuadroMembroController::class, 'destroy'])->name('quadros.membros.destroy');

    Route::post('/cursos', [CursoController::class, 'store'])->name('cursos.store');
    Route::put('/cursos/{curso}', [CursoController::class, 'update'])->name('cursos.update');
    Route::patch('/cursos/{curso}', [CursoController::class, 'move'])->name('cursos.move');
    Route::delete('/cursos/{curso}', [CursoController::class, 'destroy'])->name('cursos.destroy');
    Route::post('/cursos/{curso}/arquivos', [CursoFileController::class, 'store'])->name('cursos.arquivos.store');
    Route::patch('/cursos/{curso}/arquivos/{arquivo}', [CursoFileController::class, 'update'])->name('cursos.arquivos.update');
    Route::delete('/cursos/{curso}/arquivos/{arquivo}', [CursoFileController::class, 'destroy'])->name('cursos.arquivos.destroy');

    Route::get('/financeiro', [FinanceiroDashboardController::class, 'index'])->name('financeiro')->middleware('carteira:dashboard');

    Route::get('/financeiro/custos-fixos', fn () => redirect()->route('financeiro'));

    Route::prefix('financeiro')->name('financeiro.')->group(function () {
        Route::get('/contas', [FinanceiroContaController::class, 'index'])->name('contas')->middleware('carteira:lancamentos');
        Route::post('/contas', [FinanceiroContaController::class, 'store'])->name('contas.store')->middleware('carteira:lancamentos');
        Route::put('/contas/{conta}', [FinanceiroContaController::class, 'update'])->name('contas.update')->middleware('carteira:lancamentos');
        Route::delete('/contas/{conta}', [FinanceiroContaController::class, 'destroy'])->name('contas.destroy')->middleware('carteira:lancamentos');
        Route::get('/contas/{conta}/exclusao', [FinanceiroContaController::class, 'deletionImpact'])->name('contas.deletion-impact')->middleware('carteira:lancamentos');
        Route::post('/transferencias', [FinanceiroContaController::class, 'transfer'])->name('transferencias.store')->middleware('carteira:lancamentos');
        Route::get('/categorias', [FinanceiroContaController::class, 'categories'])->name('categorias.index')->middleware('carteira:lancamentos');
        Route::delete('/categorias', [FinanceiroContaController::class, 'destroyCategory'])->name('categorias.destroy')->middleware('carteira:lancamentos');
        Route::post('/categorias', [FinanceiroContaController::class, 'category'])->name('categorias.store')->middleware('carteira:lancamentos');
        Route::put('/categorias/{categoria}', [FinanceiroContaController::class, 'updateCategory'])->name('categorias.update')->middleware('carteira:lancamentos');

        Route::get('/relatorio', [LancamentoController::class, 'relatorio'])->name('relatorio')->middleware('carteira:lancamentos');

        Route::get('/entradas', [LancamentoController::class, 'entradas'])->name('entradas')->middleware('carteira:entradas');
        Route::get('/saidas', [LancamentoController::class, 'saidas'])->name('saidas')->middleware('carteira:saidas');

        Route::middleware('carteira:lancamentos')->group(function () {
            Route::post('/lancamentos/{lancamento}/arquivos', [LancamentoFileController::class, 'store'])->name('comprovantes.store');
            Route::get('/lancamentos/{lancamento}/arquivos/{arquivo}', [LancamentoFileController::class, 'download'])->name('comprovantes.download');
            Route::delete('/lancamentos/{lancamento}/arquivos/{arquivo}', [LancamentoFileController::class, 'destroy'])->name('comprovantes.destroy');
            Route::post('/lancamentos', [LancamentoController::class, 'store'])->name('lancamentos.store');
            Route::patch('/lancamentos/{lancamento}', [LancamentoController::class, 'update'])->name('lancamentos.update');
            Route::delete('/lancamentos/{lancamento}', [LancamentoController::class, 'destroy'])->name('lancamentos.destroy');
        });

        Route::middleware('carteira:investimentos')->group(function () {
            Route::get('/investimentos', [InvestimentoController::class, 'index'])->name('investimentos');
            Route::post('/investimentos', [InvestimentoController::class, 'store'])->name('investimentos.store');
            Route::put('/investimentos/{investimento}', [InvestimentoController::class, 'update'])->name('investimentos.update');
            Route::delete('/investimentos/{investimento}', [InvestimentoController::class, 'destroy'])->name('investimentos.destroy');
            Route::post('/investimentos/{investimento}/aportes', [InvestimentoAporteController::class, 'store'])->name('investimentos.aportes.store');
            Route::delete('/investimentos/{investimento}/aportes/{aporte}', [InvestimentoAporteController::class, 'destroy'])->name('investimentos.aportes.destroy');
        });
    });

    Route::get('/notas', [NotaController::class, 'index'])->name('notas');
    Route::get('/notas/opcoes', [NotaController::class, 'options'])->name('notas.opcoes');
    Route::get('/notas/tarefas', [NotaController::class, 'tasks'])->name('notas.tarefas');
    Route::post('/notas', [NotaController::class, 'store'])->name('notas.store');
    Route::get('/notas/{nota}', [NotaController::class, 'show'])->name('notas.show');
    Route::patch('/notas/{nota}', [NotaController::class, 'update'])->name('notas.update');
    Route::delete('/notas/{nota}', [NotaController::class, 'destroy'])->name('notas.destroy');
    Route::patch('/notas/{nota}/itens/{item}', [NotaController::class, 'updateItem'])->whereNumber('item')->name('notas.itens.update');
    Route::put('/notas/{nota}/tarefa', [NotaController::class, 'link'])->name('notas.tarefa');
    Route::post('/notas/{nota}/agendar', [NotaController::class, 'schedule'])->name('notas.agendar');

    Route::get('/tarefas', [TarefaController::class, 'index'])->name('tarefas');
    Route::get('/tarefas/buscar', [TarefaController::class, 'buscar'])->name('tarefas.buscar');
    Route::post('/tarefas', [TarefaController::class, 'store'])->name('tarefas.store');
    Route::post('/tarefas/categorias', [TarefaController::class, 'storeCategory'])->name('tarefas.categorias.store');
    Route::get('/tarefas/{tarefa}', [TarefaController::class, 'show'])->name('tarefas.show');
    Route::post('/tarefas/{tarefa}/arquivos', [TarefaFileController::class, 'store'])->name('tarefas.arquivos.store');
    Route::get('/tarefas/{tarefa}/arquivos/{arquivo}', [TarefaFileController::class, 'show'])->name('tarefas.arquivos.show');
    Route::delete('/tarefas/{tarefa}/arquivos/{arquivo}', [TarefaFileController::class, 'destroy'])->name('tarefas.arquivos.destroy');
    Route::patch('/tarefas/{tarefa}', [TarefaController::class, 'update'])->name('tarefas.update');
    Route::delete('/tarefas/{tarefa}', [TarefaController::class, 'destroy'])->name('tarefas.destroy');

    Route::get('/perfil', [PerfilController::class, 'show'])->name('perfil');
    Route::get('/perfil/notificacoes/contador', [PerfilController::class, 'notificationCount'])->name('perfil.notificacoes.count');
    Route::post('/perfil/lembretes/{notification}', [PerfilController::class, 'readReminder'])->name('perfil.lembretes.read');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/senha', [PerfilController::class, 'updatePassword'])->name('perfil.senha');

    Route::post('/amigos', [AmizadeController::class, 'store'])->middleware('throttle:10,1')->name('amigos.store');
    Route::post('/amigos/{amizade}/responder', [AmizadeController::class, 'respond'])->name('amigos.respond');
    Route::delete('/amigos/{amizade}', [AmizadeController::class, 'destroy'])->name('amigos.destroy');
    Route::get('/notificacoes', [NotificacaoController::class, 'index'])->name('notificacoes.index');
    Route::post('/notificacoes/{notification}/abrir', [NotificacaoController::class, 'open'])->name('notificacoes.open');
    Route::post('/participacoes', [ParticipacaoController::class, 'store'])->name('participacoes.store');
    Route::delete('/participacoes/{participacao}', [ParticipacaoController::class, 'destroy'])->name('participacoes.destroy');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
