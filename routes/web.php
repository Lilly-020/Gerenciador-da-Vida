<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\CursoFileController;
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
    Route::get('/', [SonhoController::class, 'index'])->name('sonhos');
    Route::post('/sonhos', [SonhoController::class, 'store'])->name('sonhos.store');
    Route::post('/sonhos/{sonho}/tarefas', [SonhoTaskController::class, 'store'])->name('sonhos.tarefas.store');
    Route::patch('/sonhos/{sonho}/tarefas/{tarefa}', [SonhoTaskController::class, 'update'])->name('sonhos.tarefas.update');

    Route::get('/projetos', [ProjetoController::class, 'index'])->name('projetos');
    Route::post('/projetos', [ProjetoController::class, 'store'])->name('projetos.store');
    Route::post('/projetos/{projeto}/tarefas', [ProjetoTaskController::class, 'store'])->name('projetos.tarefas.store');
    Route::patch('/projetos/{projeto}/tarefas/{tarefa}', [ProjetoTaskController::class, 'update'])->name('projetos.tarefas.update');

    Route::get('/cursos', [CursoController::class, 'index'])->name('cursos');
    Route::post('/cursos', [CursoController::class, 'store'])->name('cursos.store');
    Route::put('/cursos/{curso}', [CursoController::class, 'update'])->name('cursos.update');
    Route::patch('/cursos/{curso}', [CursoController::class, 'move'])->name('cursos.move');
    Route::delete('/cursos/{curso}', [CursoController::class, 'destroy'])->name('cursos.destroy');
    Route::post('/cursos/{curso}/arquivos', [CursoFileController::class, 'store'])->name('cursos.arquivos.store');
    Route::delete('/cursos/{curso}/arquivos/{arquivo}', [CursoFileController::class, 'destroy'])->name('cursos.arquivos.destroy');

    Route::view('/financeiro', 'pages.financeiro')->name('financeiro');

    Route::get('/tarefas', [TarefaController::class, 'index'])->name('tarefas');
    Route::post('/tarefas', [TarefaController::class, 'store'])->name('tarefas.store');
    Route::patch('/tarefas/{tarefa}', [TarefaController::class, 'update'])->name('tarefas.update');
    Route::delete('/tarefas/{tarefa}', [TarefaController::class, 'destroy'])->name('tarefas.destroy');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
