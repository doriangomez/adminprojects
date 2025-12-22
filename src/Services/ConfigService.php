<?php

declare(strict_types=1);

class ConfigService
{
    private string $filePath;

    private array $defaults = [
        'theme' => [
            'logo' => '/project/public/logo.png',
            'primary' => '#2563eb',
            'secondary' => '#0f172a',
            'accent' => '#f97316',
            'background' => '#0b1224',
            'login_hero' => 'Orquesta tus operaciones críticas',
            'login_message' => 'Diseña flujos, controla riesgos y haz visible el valor de tu PMO.',
        ],
        'master_files' => [
            'data_file' => 'data/data.json',
            'schema_file' => 'data/schema.sql',
        ],
        'access' => [
            'roles' => ['Administrador', 'PMO', 'Talento'],
            'user_management' => [
                'allow_self_registration' => false,
                'require_approval' => true,
            ],
        ],
    ];

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?: __DIR__ . '/../../data/config.json';
    }

    public function getConfig(): array
    {
        $stored = $this->readConfigFile();

        return [
            'theme' => array_merge($this->defaults['theme'], $stored['theme'] ?? []),
            'master_files' => array_merge($this->defaults['master_files'], $stored['master_files'] ?? []),
            'access' => [
                'roles' => $stored['access']['roles'] ?? $this->defaults['access']['roles'],
                'user_management' => array_merge(
                    $this->defaults['access']['user_management'],
                    $stored['access']['user_management'] ?? []
                ),
            ],
        ];
    }

    public function updateConfig(array $payload): array
    {
        $current = $this->getConfig();

        $updated = [
            'theme' => array_merge($current['theme'], $payload['theme'] ?? []),
            'master_files' => array_merge($current['master_files'], $payload['master_files'] ?? []),
            'access' => [
                'roles' => $payload['access']['roles'] ?? $current['access']['roles'],
                'user_management' => array_merge(
                    $current['access']['user_management'],
                    $payload['access']['user_management'] ?? []
                ),
            ],
        ];

        $this->writeConfigFile($updated);

        return $updated;
    }

    private function readConfigFile(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $contents = file_get_contents($this->filePath);
        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeConfigFile(array $config): void
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->filePath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
