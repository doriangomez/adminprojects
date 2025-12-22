<?php

declare(strict_types=1);

class ConfigController extends Controller
{
    public function index(): void
    {
        $service = new ConfigService();
        $config = $service->getConfig();

        $this->ensureConfigAccess();

        $this->render('config/index', [
            'title' => 'Configuración',
            'configData' => $config,
            'savedMessage' => !empty($_GET['saved']) ? 'Preferencias actualizadas y aplicadas en la interfaz.' : null,
        ]);
    }

    public function update(): void
    {
        $this->ensureConfigAccess();

        $payload = [
            'theme' => [
                'logo' => trim($_POST['logo'] ?? ''),
                'primary' => $_POST['primary'] ?? '#2563eb',
                'secondary' => $_POST['secondary'] ?? '#0f172a',
                'accent' => $_POST['accent'] ?? '#f97316',
                'background' => $_POST['background'] ?? '#0b1224',
                'login_hero' => trim($_POST['login_hero'] ?? ''),
                'login_message' => trim($_POST['login_message'] ?? ''),
            ],
            'master_files' => [
                'data_file' => trim($_POST['data_file'] ?? 'data/data.json'),
                'schema_file' => trim($_POST['schema_file'] ?? 'data/schema.sql'),
            ],
            'access' => [
                'roles' => $this->parseList($_POST['roles'] ?? ''),
                'user_management' => [
                    'allow_self_registration' => isset($_POST['allow_self_registration']),
                    'require_approval' => isset($_POST['require_approval']),
                ],
            ],
        ];

        $service = new ConfigService();
        $service->updateConfig($payload);

        header('Location: /project/public/config?saved=1');
    }

    private function parseList(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, fn ($part) => $part !== '');

        return array_values($parts);
    }

    private function ensureConfigAccess(): void
    {
        $user = $this->auth->user();
        $allowedRoles = ['Administrador', 'PMO'];
        if (!$user || !in_array($user['role'] ?? '', $allowedRoles, true)) {
            http_response_code(403);
            exit('Acceso denegado');
        }
    }
}
