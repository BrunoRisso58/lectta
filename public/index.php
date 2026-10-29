<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Onde está o resto do Laravel. Localmente este arquivo fica em <app>/public;
// na Hostinger ele fica em public_html/aulas e o app em
// domains/<dominio>/lectta-app (fora do alcance da web).
$appRoot = is_file(__DIR__.'/../vendor/autoload.php')
    ? __DIR__.'/..'
    : __DIR__.'/../../lectta-app';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appRoot.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
