<?php

use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\YoutubeController;
use Illuminate\Support\Facades\Route;

/*
| Endpoints chamados por máquina (cron e worker do YouTube), protegidos pelo
| token compartilhado (header X-API-Token ou ?token=). Prefixo /api aplicado
| pelo Laravel.
*/

Route::middleware('api.token')->group(function () {
    Route::get('/process', PipelineController::class)->name('api.process');
    Route::get('/youtube/jobs', [YoutubeController::class, 'nextJob'])->name('api.youtube.jobs');
    Route::post('/youtube/ingest', [YoutubeController::class, 'ingest'])->name('api.youtube.ingest');
});
