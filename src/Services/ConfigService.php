<?php

declare(strict_types=1);

class ConfigService
{
    private string $filePath;
    private ?Database $db;

    private array $defaults = [
        'debug' => false,
        'theme' => [
            'logo' => '/project/public/uploads/logos/default.svg',
            'primary' => '#2563eb',
            'secondary' => '#111827',
            'accent' => '#f59e0b',
            'background' => '#f3f4f6',
            'surface' => '#ffffff',
            'text_main' => '#0f172a',
            'text_muted' => '#475569',
            'border' => '#e5e7eb',
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
                'scrum' => ['01-INICIO', '02-BACKLOG', '03-SPRINTS', '04-CIERRE'],
                'cascada' => ['01-INICIO', '02-PLANIFICACION', '03-DISEÑO', '04-EJECUCION', '05-SEGUIMIENTO_Y_CONTROL', '06-CIERRE'],
                'kanban' => ['01-BACKLOG', '02-EN-CURSO', '03-EN-REVISION', '04-HECHO'],
            ],
            'risks' => [],
        ],
        'document_flow' => [
            'default' => [
                'reviewer_roles' => ['Administrador', 'PMO'],
                'validator_roles' => ['Administrador', 'PMO'],
                'approver_roles' => ['Administrador'],
            ],
            'phases' => [],
            'expected_docs' => [
                'cascada' => [
                    'default' => [
                        '01-ENTRADAS' => [
                            'Acta de inicio',
                            'Stakeholders iniciales',
                            'Requerimientos base',
                        ],
                        '02-PLANIFICACION' => [
                            'Plan del proyecto',
                            'Cronograma detallado',
                            'Presupuesto preliminar',
                        ],
                        '03-CONTROLES' => [
                            'Matriz de riesgos',
                            'Plan de comunicaciones',
                            'Plan de calidad',
                        ],
                        '04-EVIDENCIAS' => [
                            'Reporte de avances',
                            'Actas de seguimiento',
                            'Registro de entregables',
                        ],
                        '05-CAMBIOS' => [
                            'Solicitud de cambio',
                            'Evaluación de impacto',
                            'Acta de aprobación de cambio',
                        ],
                    ],
                    '02-PLANIFICACION' => [
                        '01-ENTRADAS' => [
                            'Propuesta comercial',
                            'Cotización',
                            'Alcance técnico inicial',
                        ],
                        '02-PLANIFICACION' => [
                            'WBS y cronograma',
                            'Plan de recursos',
                            'Plan de comunicaciones',
                        ],
                        '03-CONTROLES' => [
                            'Matriz de riesgos',
                            'Plan de calidad',
                            'Checklist de control',
                        ],
                        '04-EVIDENCIAS' => [
                            'Acta de aprobación',
                            'Registro de decisiones',
                            'Reporte de kickoff',
                        ],
                        '05-CAMBIOS' => [
                            'Solicitud de ajuste',
                            'Evaluación de impacto',
                            'Acta de comité de cambios',
                        ],
                    ],
                ],
                'scrum' => [
                    'default' => [
                        '01-ENTRADAS' => [
                            'Visión del producto',
                            'Backlog inicial',
                            'Mapa de stakeholders',
                        ],
                        '02-PLANIFICACION' => [
                            'Sprint backlog',
                            'Definición de listo',
                            'Objetivo del sprint',
                        ],
                        '03-CONTROLES' => [
                            'Burndown chart',
                            'Tablero de tareas',
                            'Checklist de calidad',
                        ],
                        '04-EVIDENCIAS' => [
                            'Demo y feedback',
                            'Reporte de sprint',
                            'Retrospectiva',
                        ],
                        '05-CAMBIOS' => [
                            'Ajustes de backlog',
                            'Registro de impedimentos',
                            'Decisiones de refinamiento',
                        ],
                    ],
                ],
                'kanban' => [
                    'default' => [
                        '01-ENTRADAS' => [
                            'Backlog de demandas',
                            'Definición de servicio',
                            'Políticas iniciales',
                        ],
                        '02-PLANIFICACION' => [
                            'WIP permitido',
                            'Acuerdos de capacidad',
                            'Criterios de priorización',
                        ],
                        '03-CONTROLES' => [
                            'Métricas de flujo',
                            'Revisión de bloqueos',
                            'Checklist de calidad',
                        ],
                        '04-EVIDENCIAS' => [
                            'Reporte de throughput',
                            'Retro semanal',
                            'Registro de mejoras',
                        ],
                        '05-CAMBIOS' => [
                            'Actualización de políticas',
                            'Solicitudes de ajuste',
                            'Decisiones de proceso',
                        ],
                    ],
                ],
            ],
            'tag_options' => [
                'Propuesta comercial',
                'Cotización',
                'Alcance técnico',
                'Requerimientos',
                'Documento libre',
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

    public function __construct(?Database $db = null, ?string $filePath = null)
    {
        $this->db = $db;
        $this->filePath = $filePath ?: __DIR__ . '/../../data/config.json';
    }

    public function getConfig(): array
    {
        $stored = $this->readConfigStorage();
        $expectedDocs = $this->normalizeExpectedDocs($stored['document_flow']['expected_docs'] ?? $this->defaults['document_flow']['expected_docs']);

        return [
            'debug' => (bool) ($stored['debug'] ?? false),
            'theme' => array_merge($this->defaults['theme'], $stored['theme'] ?? []),
            'master_files' => array_merge($this->defaults['master_files'], $stored['master_files'] ?? []),
            'delivery' => [
                'methodologies' => $stored['delivery']['methodologies'] ?? $this->defaults['delivery']['methodologies'],
                'phases' => array_merge($this->defaults['delivery']['phases'], $stored['delivery']['phases'] ?? []),
                'risks' => $this->loadRiskCatalog(),
            ],
            'document_flow' => [
                'default' => array_merge(
                    $this->defaults['document_flow']['default'],
                    $stored['document_flow']['default'] ?? []
                ),
                'phases' => $stored['document_flow']['phases'] ?? $this->defaults['document_flow']['phases'],
                'expected_docs' => $expectedDocs,
                'tag_options' => $stored['document_flow']['tag_options'] ?? $this->defaults['document_flow']['tag_options'],
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
            'debug' => array_key_exists('debug', $payload) ? (bool) $payload['debug'] : ($current['debug'] ?? false),
            'theme' => array_merge($current['theme'], $payload['theme'] ?? []),
            'master_files' => array_merge($current['master_files'], $payload['master_files'] ?? []),
            'delivery' => [
                'methodologies' => $payload['delivery']['methodologies'] ?? $current['delivery']['methodologies'],
                'phases' => array_merge($current['delivery']['phases'], $payload['delivery']['phases'] ?? []),
                'risks' => $current['delivery']['risks'],
            ],
            'document_flow' => [
                'default' => array_merge(
                    $current['document_flow']['default'] ?? [],
                    $payload['document_flow']['default'] ?? []
                ),
                'phases' => $payload['document_flow']['phases'] ?? ($current['document_flow']['phases'] ?? []),
                'expected_docs' => $this->normalizeExpectedDocs(
                    $payload['document_flow']['expected_docs'] ?? ($current['document_flow']['expected_docs'] ?? [])
                ),
                'tag_options' => $payload['document_flow']['tag_options'] ?? ($current['document_flow']['tag_options'] ?? []),
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

        $this->writeConfigStorage($updated);

        return $updated;
    }

    public function getBranding(): array
    {
        $theme = (new ThemeRepository($this->db))->getActiveTheme();

        return ['theme' => $theme];
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

    public function getDefaults(): array
    {
        return $this->defaults;
    }

    private function loadRiskCatalog(): array
    {
        if ($this->db === null) {
            return $this->defaults['delivery']['risks'];
        }

        try {
            $repo = new RiskCatalogRepository($this->db);
            return $repo->listAll(true);
        } catch (\Throwable) {
            return $this->defaults['delivery']['risks'];
        }
    }

    private function normalizeExpectedDocs(array $expectedDocs): array
    {
        foreach ($expectedDocs as $key => $value) {
            if (!is_int($key) && is_array($value)) {
                return $expectedDocs;
            }
        }

        return $this->defaults['document_flow']['expected_docs'];
    }

    private function readConfigStorage(): array
    {
        $dbConfig = $this->readConfigFromDatabase();
        if ($dbConfig !== null) {
            return $dbConfig;
        }

        return $this->readConfigFile();
    }

    private function writeConfigStorage(array $config): void
    {
        if ($this->writeConfigToDatabase($config)) {
            return;
        }

        $this->writeConfigFile($config);
    }

    private function readConfigFromDatabase(): ?array
    {
        if (!$this->db || !$this->db->tableExists('config_settings')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT config_value FROM config_settings WHERE config_key = :key LIMIT 1',
            [':key' => 'app']
        );

        if (!$row || !isset($row['config_value'])) {
            return null;
        }

        $decoded = json_decode((string) $row['config_value'], true);

        return is_array($decoded) ? $decoded : null;
    }

    private function writeConfigToDatabase(array $config): bool
    {
        if (!$this->db || !$this->db->tableExists('config_settings')) {
            return false;
        }

        $this->db->execute(
            'INSERT INTO config_settings (config_key, config_value, updated_at)
             VALUES (:key, :value, NOW())
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()',
            [
                ':key' => 'app',
                ':value' => json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]
        );

        return true;
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
