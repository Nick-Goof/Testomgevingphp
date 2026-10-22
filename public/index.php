<?php

declare(strict_types=1);

use App\ItemStore;
use App\Request;
use App\Router;

// Laat de built-in server bestaande statische bestanden zelf serveren
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!str_starts_with($path, '/api')) {
    readfile(__DIR__ . '/ui.html');
    return;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: *');

// PHP 8.4: method chaining op `new` zonder extra haakjes
new Router(new ItemStore(__DIR__ . '/../data/items.json'))
    ->handle(Request::fromGlobals())
    ->send();
