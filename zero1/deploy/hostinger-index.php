<?php

/*
 * index.php para public_html na Hostinger (hospedagem de sites, sem VPS).
 * O sistema fica numa pasta PRIVADA chamada "zero1" ao lado de public_html (ou um nível acima,
 * no caso de subdomínio dentro de public_html). Assim .env, código e dados nunca ficam acessíveis pela web.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$candidates = [__DIR__.'/../zero1', __DIR__.'/../../zero1', __DIR__.'/../../../zero1'];
$app_path = null;
foreach ($candidates as $dir) {
    if (is_file($dir.'/vendor/autoload.php')) {
        $app_path = realpath($dir);
        break;
    }
}
if (! $app_path) {
    http_response_code(500);
    exit('Pasta "zero1" não encontrada. Envie-a para fora de public_html (veja LEIA-ME).');
}

if (file_exists($maintenance = $app_path.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $app_path.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $app_path.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
