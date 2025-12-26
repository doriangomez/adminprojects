<?php

declare(strict_types=1);

class ConfigService
{
    private string $filePath;

    private array $defaults = [
        'theme' => [
            'logo' => '/project/public/uploads/logos/default.svg',
            'primary' => '#2563eb',
            'secondary' => '#111827',
            'accent' => '#f59e0b',
            'background' => '#f3f4f6',
            'surface' => '#ffffff',
            'font_family' => "'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
            'login_hero' => 'Orquesta tus operaciones críticas',
            'login_message' => 'Diseña flujos, controla riesgos y haz visible el valor de tu PMO.',
        ],
        'master_files' => [
            'data_file' => 'data/data.json',
            'schema_file' => 'data/schema.sql',
        ],
        'delivery' => [
            'methodologies' => ['scrum', 'cascada', 'kanban'],
            'phases' => [
                'scrum' => ['descubrimiento', 'backlog listo', 'sprint', 'deploy'],
                'cascada' => ['inicio', 'planificación', 'ejecución', 'cierre'],
                'kanban' => ['por hacer', 'en curso', 'en revisión', 'hecho'],
            ],
            'risks' => [
                ['code' => 'scope_creep', 'label' => 'Desviación de alcance'],
                ['code' => 'budget_overrun', 'label' => 'Sobrepaso de presupuesto'],
                ['code' => 'timeline_slip', 'label' => 'Desviación en cronograma'],
            ],
        ],
        'access' => [
            'roles' => ['Administrador', 'PMO', 'Talento'],
            'user_management' => [
                'allow_self_registration' => false,
                'require_approval' => true,
            ],
        ],
        'operational_rules' => [
            'semaforization' => [
                'progress' => [
                    'yellow_below' => 50.0,
                    'red_below' => 25.0,
                ],
                'hours' => [
                    'yellow_above' => 0.05,
                    'red_above' => 0.10,
                ],
                'cost' => [
                    'yellow_above' => 0.05,
                    'red_above' => 0.10,
                ],
            ],
            'approvals' => [
                'external_talent_requires_approval' => true,
                'budget_change_requires_approval' => true,
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
            'delivery' => [
                'methodologies' => $stored['delivery']['methodologies'] ?? $this->defaults['delivery']['methodologies'],
                'phases' => array_merge($this->defaults['delivery']['phases'], $stored['delivery']['phases'] ?? []),
                'risks' => $stored['delivery']['risks'] ?? $this->defaults['delivery']['risks'],
            ],
            'access' => [
                'roles' => $stored['access']['roles'] ?? $this->defaults['access']['roles'],
                'user_management' => array_merge(
                    $this->defaults['access']['user_management'],
                    $stored['access']['user_management'] ?? []
                ),
            ],
            'operational_rules' => [
                'semaforization' => [
                    'progress' => array_merge(
                        $this->defaults['operational_rules']['semaforization']['progress'],
                        $stored['operational_rules']['semaforization']['progress'] ?? []
                    ),
                    'hours' => array_merge(
                        $this->defaults['operational_rules']['semaforization']['hours'],
                        $stored['operational_rules']['semaforization']['hours'] ?? []
                    ),
                    'cost' => array_merge(
                        $this->defaults['operational_rules']['semaforization']['cost'],
                        $stored['operational_rules']['semaforization']['cost'] ?? []
                    ),
                ],
                'approvals' => array_merge(
                    $this->defaults['operational_rules']['approvals'],
                    $stored['operational_rules']['approvals'] ?? []
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
            'delivery' => [
                'methodologies' => $payload['delivery']['methodologies'] ?? $current['delivery']['methodologies'],
                'phases' => array_merge($current['delivery']['phases'], $payload['delivery']['phases'] ?? []),
                'risks' => $payload['delivery']['risks'] ?? $current['delivery']['risks'],
            ],
            'access' => [
                'roles' => $payload['access']['roles'] ?? $current['access']['roles'],
                'user_management' => array_merge(
                    $current['access']['user_management'],
                    $payload['access']['user_management'] ?? []
                ),
            ],
            'operational_rules' => [
                'semaforization' => [
                    'progress' => array_merge(
                        $current['operational_rules']['semaforization']['progress'],
                        $payload['operational_rules']['semaforization']['progress'] ?? []
                    ),
                    'hours' => array_merge(
                        $current['operational_rules']['semaforization']['hours'],
                        $payload['operational_rules']['semaforization']['hours'] ?? []
                    ),
                    'cost' => array_merge(
                        $current['operational_rules']['semaforization']['cost'],
                        $payload['operational_rules']['semaforization']['cost'] ?? []
                    ),
                ],
                'approvals' => array_merge(
                    $current['operational_rules']['approvals'],
                    $payload['operational_rules']['approvals'] ?? []
                ),
            ],
        ];

        $this->writeConfigFile($updated);

        return $updated;
    }

    public function getBranding(): array
    {
        $config = $this->getConfig();
        $theme = $config['theme'] ?? [];

        $basePublicPath = '/project/public';
        $uploadsPrefix = $basePublicPath . '/uploads/logos/';
        $defaultLogo = $this->defaults['theme']['logo'];

        $logoUrl = is_string($theme['logo'] ?? null) ? trim((string) $theme['logo']) : '';
        if (!str_starts_with($logoUrl, $uploadsPrefix)) {
            $logoUrl = $defaultLogo;
        }

        $logoPath = $this->publicPathFromUrl($logoUrl, $basePublicPath);
        if (!$logoPath || !is_file($logoPath)) {
            $logoUrl = $defaultLogo;
        }

        return [
            'theme' => array_merge($this->defaults['theme'], $theme, ['logo' => $logoUrl]),
        ];
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

    private function publicPathFromUrl(string $url, string $basePublicPath): ?string
    {
        if (!str_starts_with($url, $basePublicPath)) {
            return null;
        }

        $relativePath = substr($url, strlen($basePublicPath));
        $fullPath = __DIR__ . '/../../public' . $relativePath;

        return $fullPath;
    }
}
