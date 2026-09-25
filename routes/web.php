<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::view('/', 'pages.sonhos')->name('sonhos');
    Route::view('/projetos', 'pages.projetos')->name('projetos');
    Route::view('/cursos', 'pages.cursos')->name('cursos');
    Route::view('/financeiro', 'pages.financeiro')->name('financeiro');
    Route::view('/tarefas', 'pages.tarefas')->name('tarefas');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
