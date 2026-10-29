<?php

use App\Http\Middleware\VerifyApiToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api.php sem Sanctum: grupo "api" é stateless e sem CSRF, prefixo /api.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A Hostinger termina o HTTPS num proxy na frente do PHP; sem isso o
        // Laravel gera URLs http:// e o navegador bloqueia (mixed content).
        $middleware->trustProxies(at: '*');
        $middleware->alias(['api.token' => VerifyApiToken::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Em produção o código do Laravel fica FORA do public_html (em
// domains/<dominio>/lectta-app) e só a pasta pública fica em
// public_html/aulas. Isso precisa valer também no artisan (cron/SSH), não só
// nas requisições web -- é lá que moram uploads/pending.
$hostingerPublic = dirname(__DIR__, 2).'/public_html/aulas';
if (! is_file(dirname(__DIR__).'/public/index.php') && is_file($hostingerPublic.'/index.php')) {
    $app->usePublicPath($hostingerPublic);
}

return $app;
