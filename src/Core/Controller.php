<?php

declare(strict_types=1);

abstract class Controller
{
    public function __construct(protected Database $db, protected Auth $auth)
    {
    }

    protected function render(string $view, array $data = []): void
    {
        extract($data);
        $auth = $this->auth;
        $user = $this->auth->user();
        $approvalBadgeCount = 0;
        $timesheetPendingCount = 0;
        if (!empty($user)) {
            $approvalBadgeCount = $this->approvalInboxCount((int) ($user['id'] ?? 0));
            if ($this->auth->canApproveTimesheets()) {
                $timesheetPendingCount = (new TimesheetsRepository($this->db))->countPendingApprovals();
            }
        }
        $appName = $data['appName'] ?? $this->getAppName();
        $title = $data['title'] ?? $appName;
        $theme = (new ThemeRepository($this->db))->getActiveTheme();
        $configData = (new ConfigService($this->db))->getConfig();
        $timesheetsEnabled = (bool) ($configData['operational_rules']['timesheets']['enabled'] ?? false);
        $absencesEnabled = (bool) ($configData['operational_rules']['absences']['enabled'] ?? true);
        include __DIR__ . '/../Views/layout/header.php';
        include __DIR__ . '/../Views/' . $view . '.php';
        include __DIR__ . '/../Views/layout/footer.php';
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }

    protected function requirePermission(string $permission): void
    {
        if (!$this->auth->can($permission)) {
            $this->denyAccess();
        }
    }

    protected function denyAccess(string $message = 'No tienes permisos para acceder a este módulo.'): void
    {
        http_response_code(403);

        if ($this->expectsJsonResponse()) {
            exit('Acceso denegado');
        }

        $this->render('errors/forbidden', [
            'title' => 'Acceso restringido',
            'errorTitle' => 'Acceso denegado',
            'errorMessage' => $message,
        ]);
        exit;
    }

    private function expectsJsonResponse(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        return str_contains($accept, 'application/json')
            || str_contains($accept, 'text/json')
            || $requestedWith === 'xmlhttprequest'
            || in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    protected function getAppName(): string
    {
        $configPath = __DIR__ . '/../config.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            if (isset($config['app']['name'])) {
                return (string) $config['app']['name'];
            }
        }

        return 'Sistema de Gestión de Proyectos';
    }

    protected function approvalInboxCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $roleFlags = $this->documentRoleFlags($userId);
        $repo = new ProjectNodesRepository($this->db);
        $count = 0;

        if ($roleFlags['can_review']) {
            $count += count($repo->inboxDocumentsForUser('en_revision', 'reviewer_id', $userId));
        }

        if ($roleFlags['can_validate']) {
            $count += count($repo->inboxDocumentsForUser('en_validacion', 'validator_id', $userId));
        }

        if ($roleFlags['can_approve']) {
            $count += count($repo->inboxDocumentsForUser('en_aprobacion', 'approver_id', $userId));
        }

        if ($this->auth->canApproveTimesheets()) {
            $count += (new TimesheetsRepository($this->db))->countPendingApprovals();
        }

        return $count;
    }

    protected function documentRoleFlags(int $userId): array
    {
        $row = $this->db->fetchOne(
            'SELECT can_review_documents, can_validate_documents, can_approve_documents FROM users WHERE id = :id LIMIT 1',
            [':id' => $userId]
        ) ?: [];

        return [
            'can_review' => ((int) ($row['can_review_documents'] ?? 0)) === 1,
            'can_validate' => ((int) ($row['can_validate_documents'] ?? 0)) === 1,
            'can_approve' => ((int) ($row['can_approve_documents'] ?? 0)) === 1,
            'can_manage' => $this->auth->can('projects.manage'),
        ];
    }
}
