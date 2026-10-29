<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LessonEditController;
use App\Http\Controllers\LessonUploadController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SubjectController;
use Illuminate\Support\Facades\Route;

/*
| O app roda em lectta.online/aulas -- as rotas abaixo são relativas a essa
| pasta (o Laravel detecta o subdiretório sozinho).
*/

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'show'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/criar-conta', [RegisterController::class, 'show'])->name('register');
    Route::post('/criar-conta', [RegisterController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/sair', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::delete('/aula/{lesson}', [DashboardController::class, 'destroy'])->whereNumber('lesson')->name('lessons.destroy');
    Route::post('/aula/{lesson}/tentar-novamente', [DashboardController::class, 'retry'])->whereNumber('lesson')->name('lessons.retry');

    Route::get('/enviar', [LessonUploadController::class, 'create'])->name('lessons.create');
    Route::post('/enviar', [LessonUploadController::class, 'store'])->name('lessons.store');

    Route::prefix('/aula/{lesson}')->whereNumber('lesson')->controller(LessonEditController::class)->group(function () {
        Route::get('/editar', 'edit')->name('lessons.edit');
        Route::put('/conteudo', 'updateContent')->name('lessons.content');
        Route::put('/materia', 'updateSubject')->name('lessons.subject');
        Route::post('/flashcards', 'storeFlashcard')->name('flashcards.store');
        Route::post('/flashcards/gerar', 'generateFlashcards')->name('flashcards.generate');
        Route::put('/flashcards/{flashcard}', 'updateFlashcard')->whereNumber('flashcard')->name('flashcards.update');
        Route::delete('/flashcards/{flashcard}', 'destroyFlashcard')->whereNumber('flashcard')->name('flashcards.destroy');
    });

    Route::get('/revisao', [ReviewController::class, 'index'])->name('review');
    Route::post('/revisao/avaliar', [ReviewController::class, 'grade'])->name('review.grade');

    Route::get('/progresso', [ProgressController::class, 'index'])->name('progress');

    Route::get('/materias', [SubjectController::class, 'index'])->name('subjects.index');
    Route::post('/materias', [SubjectController::class, 'store'])->name('subjects.store');
    Route::delete('/materias/{subject}', [SubjectController::class, 'destroy'])->whereNumber('subject')->name('subjects.destroy');
    Route::post('/materias/{subject}/assuntos', [SubjectController::class, 'storeTopic'])->whereNumber('subject')->name('topics.store');
    Route::delete('/assuntos/{topic}', [SubjectController::class, 'destroyTopic'])->whereNumber('topic')->name('topics.destroy');
});
