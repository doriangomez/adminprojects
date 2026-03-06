<?php

declare(strict_types=1);

$isDebug = in_array(strtolower((string) (getenv('APP_DEBUG') ?? '')), ['1', 'true', 'on', 'yes'], true);

set_exception_handler(static function (Throwable $e) use ($isDebug): void {
    error_log(sprintf(
        '[PMO Exception] %s in %s:%d - %s',
        get_class($e),
        $e->getFile(),
        $e->getLine(),
        $e->getMessage()
    ));
    error_log('[PMO Exception] Trace: ' . $e->getTraceAsString());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $errorMessage = 'No se puede procesar esta solicitud en este momento.';
    $errorDetail = $isDebug ? $e->getMessage() . "\n\n" . $e->getTraceAsString() : null;
    $showDetail = $isDebug && $errorDetail !== null;

    include __DIR__ . '/../src/Views/errors/500.php';
    exit(1);
});

register_shutdown_function(static function () use ($isDebug): void {
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    error_log(sprintf('[PMO Fatal] %s in %s:%d - %s', $error['message'], $error['file'], $error['line'], $error['type']));
    $errorMessage = 'No se puede procesar esta solicitud en este momento.';
    $errorDetail = $isDebug ? "{$error['message']} (en {$error['file']}:{$error['line']})" : null;
    $showDetail = $isDebug && $errorDetail !== null;
    include __DIR__ . '/../src/Views/errors/500.php';
});

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
