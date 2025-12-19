<?php
// Basic configuration for the PMO system
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'pmo',
        'username' => getenv('DB_USER') ?: 'pmo_user',
        'password' => getenv('DB_PASSWORD') ?: 'secret',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Prompt Maestro - PMO',
        'key' => getenv('APP_KEY') ?: 'change-me',
    ],
];
