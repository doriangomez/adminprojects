<?php
require_once __DIR__ . '/../src/Core/Database.php';
require_once __DIR__ . '/../src/Core/DatabaseMigrator.php';
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

foreach (glob(__DIR__ . '/../src/Repositories/*.php') as $file) {
    $repository = pathinfo($file, PATHINFO_FILENAME);
    $fqcn = 'App\\Repositories\\' . $repository;

    if (class_exists($fqcn) && !class_exists($repository)) {
        class_alias($fqcn, $repository);
    }
}

$app = new App();
$app->handle();
