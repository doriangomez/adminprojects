<?php
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Core/Auth.php';
require_once __DIR__ . '/../src/Core/Controller.php';
require_once __DIR__ . '/../src/Core/App.php';

foreach ([
    'Controllers',
    'Repositories',
    'Services',
] as $dir) {
    foreach (glob(__DIR__ . '/../src/' . $dir . '/*.php') as $file) {
        require_once $file;
    }
}

$app = new App();
$app->handle();
