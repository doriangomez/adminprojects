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
            'surface' => '#0f172a',
            'font_family' => "'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
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

    public function storeLogo(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowedMime = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg'];
        $mime = mime_content_type($file['tmp_name']);
        if (!isset($allowedMime[$mime])) {
            return null;
        }

        $targetDir = __DIR__ . '/../../public/uploads/logos';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $safeName = 'logo_' . time() . '.' . $allowedMime[$mime];
        $targetPath = $targetDir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return null;
        }

        return '/project/public/uploads/logos/' . $safeName;
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
