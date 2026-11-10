<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CarteiraController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\CursoFileController;
use App\Http\Controllers\FinanceiroContaController;
use App\Http\Controllers\FinanceiroDashboardController;
use App\Http\Controllers\InvestimentoAporteController;
use App\Http\Controllers\InvestimentoController;
use App\Http\Controllers\LancamentoController;
use App\Http\Controllers\LancamentoFileController;
use App\Http\Controllers\ParticipacaoController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ProjetoCollaborationController;
use App\Http\Controllers\ProjetoController;
use App\Http\Controllers\ProjetoTaskController;
use App\Http\Controllers\SonhoController;
use App\Http\Controllers\SonhoTaskController;
use App\Http\Controllers\TarefaController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::middleware('can:manage-users')->prefix('admin/usuarios')->name('admin.usuarios.')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::post('/', [AdminUserController::class, 'store'])->name('store');
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
    Route::patch('/projetos/{projeto}/tarefas/{tarefa}', [ProjetoTaskController::class, 'update'])->name('projetos.tarefas.update');
    Route::delete('/projetos/{projeto}/tarefas/{tarefa}', [ProjetoTaskController::class, 'destroy'])->name('projetos.tarefas.destroy');

    Route::post('/projetos/{projeto}/comentarios', [ProjetoCollaborationController::class, 'comment'])->name('projetos.comentarios.store');
    Route::delete('/projetos/{projeto}/comentarios/{comentario}', [ProjetoCollaborationController::class, 'destroyComment'])->name('projetos.comentarios.destroy');
    Route::post('/projetos/{projeto}/arquivos', [ProjetoCollaborationController::class, 'upload'])->name('projetos.arquivos.store');
    Route::get('/projetos/{projeto}/arquivos/{arquivo}', [ProjetoCollaborationController::class, 'download'])->name('projetos.arquivos.download');
    Route::delete('/projetos/{projeto}/arquivos/{arquivo}', [ProjetoCollaborationController::class, 'destroyFile'])->name('projetos.arquivos.destroy');

    Route::get('/cursos', [CursoController::class, 'index'])->name('cursos');
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
        Route::post('/transferencias', [FinanceiroContaController::class, 'transfer'])->name('transferencias.store')->middleware('carteira:lancamentos');
        Route::post('/categorias', [FinanceiroContaController::class, 'category'])->name('categorias.store')->middleware('carteira:lancamentos');
        Route::put('/categorias/{categoria}', [FinanceiroContaController::class, 'updateCategory'])->name('categorias.update')->middleware('carteira:lancamentos');
        Route::get('/participantes', [CarteiraController::class, 'participantes'])->name('participantes');
        Route::post('/carteira', [CarteiraController::class, 'trocar'])->name('carteira');

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

    Route::get('/tarefas', [TarefaController::class, 'index'])->name('tarefas');
    Route::get('/tarefas/buscar', [TarefaController::class, 'buscar'])->name('tarefas.buscar');
    Route::post('/tarefas', [TarefaController::class, 'store'])->name('tarefas.store');
    Route::patch('/tarefas/{tarefa}', [TarefaController::class, 'update'])->name('tarefas.update');
    Route::delete('/tarefas/{tarefa}', [TarefaController::class, 'destroy'])->name('tarefas.destroy');

    Route::get('/perfil', [PerfilController::class, 'show'])->name('perfil');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/senha', [PerfilController::class, 'updatePassword'])->name('perfil.senha');
    Route::get('/usuarios/buscar', [PerfilController::class, 'buscar'])->name('usuarios.buscar');

    Route::post('/participacoes', [ParticipacaoController::class, 'store'])->name('participacoes.store');
    Route::put('/participacoes/{participacao}/financeiro', [ParticipacaoController::class, 'updateFinancialAccess'])->name('participacoes.financeiro.update');
    Route::post('/participacoes/{participacao}/reenviar', [ParticipacaoController::class, 'reenviar'])->name('participacoes.reenviar');
    Route::post('/participacoes/{participacao}/aceitar', [ParticipacaoController::class, 'aceitar'])->name('participacoes.aceitar');
    Route::post('/participacoes/{participacao}/recusar', [ParticipacaoController::class, 'recusar'])->name('participacoes.recusar');
    Route::delete('/participacoes/{participacao}', [ParticipacaoController::class, 'destroy'])->name('participacoes.destroy');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
