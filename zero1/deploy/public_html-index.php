<?php

/*
 * ALTERNATIVA para quando não é possível apontar public_html para zero1/public via symlink.
 * Copie o conteúdo de zero1/public/ para public_html/ e substitua o index.php por este arquivo.
 * O código, o .env e o storage continuam FORA da pasta pública (~/zero1).
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$app_path = __DIR__.'/../zero1';

if (file_exists($maintenance = $app_path.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $app_path.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $app_path.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
