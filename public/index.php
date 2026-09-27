<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// The big PDF exports (Finalizing / Reports / Gem Register) render the
// whole history into memory. By default the built-in server runs at the
// php.ini memory_limit (often 128M), which is too small once data grows.
// `php -d memory_limit=... artisan serve` does NOT reach this request
// process, so we raise it here — inside the process that actually serves
// your pages. Laravel's built-in server boots this file on every request,
// so this protects every PDF export automatically.
if (ini_get('memory_limit') !== '-1') {
    @ini_set('memory_limit', '1024M');
}
// Never let timeouts kill a long report render.
@set_time_limit(0);

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
